<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrozasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar registros del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $estado = $request->get('estado');

            $query = TrozaHead::with(['chofer', 'transporte'])
                              ->porUsuario(auth()->id())
                              ->recientes();

            // Filtros opcionales
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('PATENTE_CAMION', 'like', "%{$search}%")
                      ->orWhereHas('chofer', function($choferQuery) use ($search) {
                          $choferQuery->where('NOMBRE_CHOFER', 'like', "%{$search}%");
                      });
                });
            }

            if ($estado) {
                $query->where('ESTADO', $estado);
            }

            $registros = $query->paginate($perPage);

            // Agregar información calculada
            $registros->getCollection()->transform(function ($registro) {
                $registro->bancos_cerrados_count = $registro->bancos_cerrados;
                $registro->total_trozas_calculado = $registro->total_trozas_calculado;
                return $registro;
            });

            return response()->json([
                'success' => true,
                'data' => $registros
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo registro
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patente_camion' => 'required|string|max:10|regex:/^[A-Z]{2}[0-9]{4}$|^[A-Z]{4}[0-9]{2}$/',
            'id_chofer' => 'required|exists:CHOFERES_PACK,ID_CHOFER',
            'id_transporte' => 'required|exists:TRANSPORTES_PACK,ID_TRANSPORTE',
            'observaciones' => 'nullable|string|max:500'
        ], [
            'patente_camion.regex' => 'El formato de patente debe ser ABCD12 o AB1234',
            'id_chofer.exists' => 'El chofer seleccionado no existe',
            'id_transporte.exists' => 'La empresa de transporte seleccionada no existe'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar que el chofer pertenece al transporte
            $chofer = \App\Models\Chofer::where('ID_CHOFER', $request->id_chofer)
                                      ->where('ID_TRANSPORTE', $request->id_transporte)
                                      ->first();

            if (!$chofer) {
                return response()->json([
                    'success' => false,
                    'message' => 'El chofer seleccionado no pertenece a la empresa de transporte'
                ], 422);
            }

            $registro = TrozaHead::create([
                'PATENTE_CAMION' => strtoupper($request->patente_camion),
                'ID_CHOFER' => $request->id_chofer,
                'ID_TRANSPORTE' => $request->id_transporte,
                'FECHA_INICIO' => now(),
                'ESTADO' => 'ABIERTO',
                'USER_ID' => auth()->id(),
                'OBSERVACIONES' => $request->observaciones,
                'CREATED_AT' => now(),
                'UPDATED_AT' => now()
            ]);

            // Log de sincronización
            SyncLog::logUpload(
                auth()->id(),
                $request->header('Device-ID', 'web'),
                SyncLog::ENTITY_TYPE_REGISTRO,
                $registro->ID_REGISTRO,
                SyncLog::STATUS_SUCCESS
            );

            DB::commit();

            // Cargar relaciones para la respuesta
            $registro->load(['chofer', 'transporte']);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $registro
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar registro específico
     */
    public function show($id): JsonResponse
    {
        try {
            $registro = TrozaHead::with([
                'chofer', 
                'transporte', 
                'detalles' => function($query) {
                    $query->orderBy('NUMERO_BANCO')->orderBy('DIAMETRO_CM');
                }
            ])
            ->where('ID_REGISTRO', $id)
            ->porUsuario(auth()->id())
            ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Agregar información calculada
            $registro->bancos_cerrados_count = $registro->bancos_cerrados;
            $registro->bancos_abiertos_count = $registro->bancos_abiertos;
            $registro->total_trozas_calculado = $registro->total_trozas_calculado;
            $registro->resumen_por_diametro = $registro->getResumenPorDiametro();

            return response()->json([
                'success' => true,
                'data' => $registro
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar trozas a un banco
     */
    public function addTrozasToBanco(Request $request, $registroId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'numero_banco' => 'required|integer|between:1,4',
            'trozas' => 'required|array|min:1',
            'trozas.*.diametro_cm' => [
                'required',
                'integer',
                Rule::in(TrozaDetail::getDiametrosDisponibles())
            ],
            'trozas.*.cantidad' => 'required|integer|min:0|max:999'
        ], [
            'trozas.*.diametro_cm.in' => 'Diámetro debe estar entre 22 y 60 cm (pares)',
            'trozas.*.cantidad.max' => 'Cantidad máxima por diámetro es 999'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registro = TrozaHead::where('ID_REGISTRO', $registroId)
                                 ->porUsuario(auth()->id())
                                 ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            if ($registro->ESTADO !== 'ABIERTO') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar un registro cerrado'
                ], 400);
            }

            // Verificar si el banco ya está cerrado
            $bancoCerrado = TrozaDetail::where('ID_REGISTRO', $registroId)
                                      ->where('NUMERO_BANCO', $request->numero_banco)
                                      ->where('BANCO_CERRADO', true)
                                      ->exists();

            if ($bancoCerrado) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar un banco cerrado'
                ], 400);
            }

            DB::beginTransaction();

            // Eliminar registros existentes del banco
            TrozaDetail::where('ID_REGISTRO', $registroId)
                       ->where('NUMERO_BANCO', $request->numero_banco)
                       ->delete();

            // Insertar nuevos registros
            $totalTrozasBanco = 0;
            foreach ($request->trozas as $troza) {
                if ($troza['cantidad'] > 0) {
                    TrozaDetail::create([
                        'ID_REGISTRO' => $registroId,
                        'NUMERO_BANCO' => $request->numero_banco,
                        'DIAMETRO_CM' => $troza['diametro_cm'],
                        'CANTIDAD_TROZAS' => $troza['cantidad'],
                        'BANCO_CERRADO' => false,
                        'CREATED_AT' => now()
                    ]);
                    $totalTrozasBanco += $troza['cantidad'];
                }
            }

            // Actualizar total de trozas del registro
            $registro->calcularTotalTrozas();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trozas registradas exitosamente en el banco ' . $request->numero_banco,
                'data' => [
                    'total_trozas_banco' => $totalTrozasBanco,
                    'total_trozas_registro' => $registro->fresh()->TOTAL_TROZAS
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar trozas: ' . $e->getMessage()
            ], 500);
        }
    }