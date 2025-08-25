<?php
// app/Http/Controllers/Api/TrozasController.php - VERSIÓN 2.0 CON LARGOS

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use App\Models\Chofer;
use App\Models\Transporte;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TrozasController extends Controller
{
    // Constantes de configuración actualizadas
    const DIAMETROS_DISPONIBLES = [22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60];
    const LARGOS_DISPONIBLES = [2.00, 2.50, 2.60, 3.80, 5.10]; // Nuevos largos en metros
    const MAX_BANCOS = 4;
    const MAX_TROZAS_POR_COMBINACION = 999;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Health check para módulo trozas
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $stats = [
                'database_connection' => true,
                'total_registros' => TrozaHead::count(),
                'registros_abiertos' => TrozaHead::where('ESTADO', 'ABIERTO')->count(),
                'registros_cerrados' => TrozaHead::where('ESTADO', 'CERRADO')->count(),
                'total_trozas' => TrozaHead::where('ESTADO', 'CERRADO')->sum('TOTAL_TROZAS'),
                'configuracion' => [
                    'diametros_disponibles' => self::DIAMETROS_DISPONIBLES,
                    'largos_disponibles' => self::LARGOS_DISPONIBLES,
                    'max_bancos' => self::MAX_BANCOS
                ],
                'server_time' => now()->toISOString(),
                'version' => '2.0.0'
            ];

            return response()->json([
                'success' => true,
                'status' => 'OK',
                'message' => 'Módulo trozas funcionando correctamente',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Error en módulo trozas: ' . $e->getMessage()
            ], 500);
        }
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
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            $query = TrozaHead::with(['chofer', 'transporte', 'usuario'])
                              ->where('USER_ID', auth()->id());

            // Filtros
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('PATENTE_CAMION', 'like', "%{$search}%")
                      ->orWhereHas('chofer', function($choferQuery) use ($search) {
                          $choferQuery->where('NOMBRE_CHOFER', 'like', "%{$search}%");
                      })
                      ->orWhereHas('transporte', function($transporteQuery) use ($search) {
                          $transporteQuery->where('NOMBRE_TRANSPORTES', 'like', "%{$search}%");
                      });
                });
            }

            if ($estado) {
                $query->where('ESTADO', $estado);
            }

            if ($fechaDesde) {
                $query->whereDate('FECHA_INICIO', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->whereDate('FECHA_INICIO', '<=', $fechaHasta);
            }

            $registros = $query->orderBy('FECHA_INICIO', 'desc')->paginate($perPage);

            // Enriquecer datos con información calculada
            $registros->getCollection()->transform(function ($registro) {
                return $this->enrichRegistroData($registro);
            });

            return response()->json([
                'success' => true,
                'data' => $registros
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo registros de trozas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo registro de trozas
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patente_camion' => [
                'required', 
                'string', 
                'max:10',
                'regex:/^[A-Z]{2}[0-9]{4}$|^[A-Z]{4}[0-9]{2}$/'
            ],
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
            $chofer = Chofer::where('ID_CHOFER', $request->id_chofer)
                           ->where('ID_TRANSPORTE', $request->id_transporte)
                           ->where('VIGENCIA', 1)
                           ->first();

            if (!$chofer) {
                return response()->json([
                    'success' => false,
                    'message' => 'El chofer seleccionado no pertenece a la empresa de transporte'
                ], 422);
            }

            // Verificar que no exista un registro abierto con la misma patente
            $registroExistente = TrozaHead::where('PATENTE_CAMION', strtoupper($request->patente_camion))
                                         ->where('ESTADO', 'ABIERTO')
                                         ->first();

            if ($registroExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un registro abierto para esta patente'
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
                'TOTAL_TROZAS' => 0,
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
            $registro->load(['chofer', 'transporte', 'usuario']);
            $enrichedRegistro = $this->enrichRegistroData($registro);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $enrichedRegistro
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando registro de trozas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar registro específico con detalles completos
     */
    public function show($id): JsonResponse
    {
        try {
            $registro = TrozaHead::with([
                'chofer', 
                'transporte', 
                'usuario',
                'detalles' => function($query) {
                    $query->orderBy('NUMERO_BANCO')
                          ->orderBy('DIAMETRO_CM')
                          ->orderBy('LARGO_M');
                }
            ])
            ->where('ID_REGISTRO', $id)
            ->where('USER_ID', auth()->id())
            ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            $enrichedRegistro = $this->enrichRegistroData($registro);
            
            // Agregar información detallada de bancos
            $enrichedRegistro->bancos_detalle = $this->getBancosDetalle($registro);
            $enrichedRegistro->resumen_por_diametro = $this->getResumenPorDiametro($registro);
            $enrichedRegistro->resumen_por_largo = $this->getResumenPorLargo($registro);

            return response()->json([
                'success' => true,
                'data' => $enrichedRegistro
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo registro de trozas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar trozas a un banco específico (ACTUALIZADO CON LARGOS)
     */
    public function addTrozasToBanco(Request $request, $registroId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'numero_banco' => 'required|integer|between:1,' . self::MAX_BANCOS,
            'trozas' => 'required|array|min:1',
            'trozas.*.diametro_cm' => [
                'required',
                'integer',
                Rule::in(self::DIAMETROS_DISPONIBLES)
            ],
            'trozas.*.largo_m' => [
                'required',
                'numeric',
                Rule::in(self::LARGOS_DISPONIBLES)
            ],
            'trozas.*.cantidad' => 'required|integer|min:0|max:' . self::MAX_TROZAS_POR_COMBINACION
        ], [
            'trozas.*.diametro_cm.in' => 'Diámetro debe estar entre los valores permitidos',
            'trozas.*.largo_m.in' => 'Largo debe estar entre los valores permitidos: ' . implode(', ', self::LARGOS_DISPONIBLES) . ' metros',
            'trozas.*.cantidad.max' => 'Cantidad máxima por combinación es ' . self::MAX_TROZAS_POR_COMBINACION
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
                                 ->where('USER_ID', auth()->id())
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

            // Insertar nuevas combinaciones de trozas
            $totalTrozasBanco = 0;
            foreach ($request->trozas as $troza) {
                if ($troza['cantidad'] > 0) {
                    TrozaDetail::create([
                        'ID_REGISTRO' => $registroId,
                        'NUMERO_BANCO' => $request->numero_banco,
                        'DIAMETRO_CM' => $troza['diametro_cm'],
                        'LARGO_M' => $troza['largo_m'],
                        'CANTIDAD_TROZAS' => $troza['cantidad'],
                        'BANCO_CERRADO' => false,
                        'CREATED_AT' => now()
                    ]);
                    $totalTrozasBanco += $troza['cantidad'];
                }
            }

            // Actualizar total de trozas del registro
            $this->recalcularTotalTrozas($registro);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Trozas registradas exitosamente en el banco {$request->numero_banco}",
                'data' => [
                    'total_trozas_banco' => $totalTrozasBanco,
                    'total_trozas_registro' => $registro->fresh()->TOTAL_TROZAS,
                    'banco_numero' => $request->numero_banco,
                    'combinaciones_guardadas' => count(array_filter($request->trozas, fn($t) => $t['cantidad'] > 0))
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error agregando trozas al banco: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar trozas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cerrar banco con foto y GPS
     */
    public function cerrarBanco(Request $request, $registroId, $numeroBanco): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'numero_banco' => 'integer|between:1,' . self::MAX_BANCOS,
            'gps_latitud' => 'nullable|numeric|between:-90,90',
            'gps_longitud' => 'nullable|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
            'observaciones_banco' => 'nullable|string|max:300'
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
                                 ->where('USER_ID', auth()->id())
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
                    'message' => 'No se puede cerrar banco de un registro cerrado'
                ], 400);
            }

            // Verificar que el banco tenga trozas
            $trozasEnBanco = TrozaDetail::where('ID_REGISTRO', $registroId)
                                       ->where('NUMERO_BANCO', $numeroBanco)
                                       ->sum('CANTIDAD_TROZAS');

            if ($trozasEnBanco == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cerrar un banco sin trozas registradas'
                ], 400);
            }

            DB::beginTransaction();

            // Cerrar todas las entradas de este banco
            TrozaDetail::where('ID_REGISTRO', $registroId)
                       ->where('NUMERO_BANCO', $numeroBanco)
                       ->update([
                           'FECHA_CIERRE_BANCO' => now(),
                           'GPS_LATITUD' => $request->gps_latitud,
                           'GPS_LONGITUD' => $request->gps_longitud,
                           'GPS_ACCURACY' => $request->gps_accuracy,
                           'OBSERVACIONES_BANCO' => $request->observaciones_banco,
                           'BANCO_CERRADO' => true
                       ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Banco {$numeroBanco} cerrado exitosamente",
                'data' => [
                    'banco_numero' => $numeroBanco,
                    'total_trozas_banco' => $trozasEnBanco,
                    'fecha_cierre' => now()->toISOString(),
                    'gps_registrado' => $request->gps_latitud && $request->gps_longitud
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cerrando banco: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar banco: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir foto de banco
     */
    public function uploadFotoBanco(Request $request, $registroId, $numeroBanco): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,jpg,png|max:10240', // 10MB máximo
            'gps_latitud' => 'nullable|numeric|between:-90,90',
            'gps_longitud' => 'nullable|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar que el registro pertenece al usuario
            $registro = TrozaHead::where('ID_REGISTRO', $registroId)
                                 ->where('USER_ID', auth()->id())
                                 ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado o sin permisos'
                ], 404);
            }

            DB::beginTransaction();

            // Subir foto
            $foto = $request->file('foto');
            $nombreArchivo = "banco_{$registroId}_{$numeroBanco}_" . time() . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('fotos/bancos', $nombreArchivo, 'public');

            // Actualizar detalles del banco con la foto
            $updated = TrozaDetail::where('ID_REGISTRO', $registroId)
                                  ->where('NUMERO_BANCO', $numeroBanco)
                                  ->update([
                                      'FOTO_PATH' => $fotoPath,
                                      'GPS_LATITUD' => $request->gps_latitud ?: DB::raw('GPS_LATITUD'),
                                      'GPS_LONGITUD' => $request->gps_longitud ?: DB::raw('GPS_LONGITUD')
                                  ]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros del banco para actualizar'
                ], 404);
            }

            // Log de sincronización
            SyncLog::logUpload(
                auth()->id(),
                $request->header('Device-ID', 'mobile'),
                SyncLog::ENTITY_TYPE_FOTO,
                $registroId,
                SyncLog::STATUS_SUCCESS
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto subida exitosamente',
                'data' => [
                    'foto_url' => Storage::url($fotoPath),
                    'foto_path' => $fotoPath,
                    'registro_id' => $registroId,
                    'numero_banco' => $numeroBanco,
                    'file_size' => $foto->getSize(),
                    'mime_type' => $foto->getMimeType()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error subiendo foto de banco: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al subir foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cerrar registro completo
     */
    public function cerrarRegistro(Request $request, $registroId): JsonResponse
    {
        try {
            $registro = TrozaHead::with('detalles')
                                 ->where('ID_REGISTRO', $registroId)
                                 ->where('USER_ID', auth()->id())
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
                    'message' => 'El registro ya está cerrado'
                ], 400);
            }

            // Verificar que al menos un banco esté cerrado
            $bancosCerrados = $registro->detalles()
                                      ->where('BANCO_CERRADO', true)
                                      ->distinct('NUMERO_BANCO')
                                      ->count('NUMERO_BANCO');

            if ($bancosCerrados == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe cerrar al menos un banco antes de cerrar el registro'
                ], 400);
            }

            DB::beginTransaction();

            // Recalcular total de trozas
            $totalTrozas = $registro->detalles()->sum('CANTIDAD_TROZAS');
            
            $registro->update([
                'FECHA_CIERRE' => now(),
                'ESTADO' => 'CERRADO',
                'TOTAL_TROZAS' => $totalTrozas,
                'UPDATED_AT' => now()
            ]);

            DB::commit();

            $enrichedRegistro = $this->enrichRegistroData($registro->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Registro cerrado exitosamente',
                'data' => [
                    'registro' => $enrichedRegistro,
                    'bancos_cerrados' => $bancosCerrados,
                    'total_trozas_final' => $totalTrozas
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cerrando registro: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener configuración de la aplicación
     */
    public function getConfiguracion(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'diametros_disponibles' => self::DIAMETROS_DISPONIBLES,
                'largos_disponibles' => self::LARGOS_DISPONIBLES,
                'max_bancos' => self::MAX_BANCOS,
                'max_trozas_por_combinacion' => self::MAX_TROZAS_POR_COMBINACION,
                'formatos_foto' => ['jpeg', 'jpg', 'png'],
                'max_size_foto_mb' => 10,
                'estados_registro' => ['ABIERTO', 'CERRADO'],
                'version' => '2.0.0'
            ]
        ]);
    }

    // MÉTODOS PRIVADOS DE APOYO

    /**
     * Enriquecer datos del registro con información calculada
     */
    private function enrichRegistroData($registro)
    {
        $registro->bancos_cerrados = $registro->detalles()
            ->where('BANCO_CERRADO', true)
            ->distinct('NUMERO_BANCO')
            ->count('NUMERO_BANCO');

        $registro->bancos_abiertos = self::MAX_BANCOS - $registro->bancos_cerrados;
        $registro->total_trozas_calculado = $registro->detalles()->sum('CANTIDAD_TROZAS');

        return $registro;
    }

    /**
     * Obtener detalle de bancos
     */
    private function getBancosDetalle($registro)
    {
        $bancos = [];
        
        for ($banco = 1; $banco <= self::MAX_BANCOS; $banco++) {
            $detallesBanco = $registro->detalles()->where('NUMERO_BANCO', $banco)->get();
            
            $bancos[$banco] = [
                'numero' => $banco,
                'estado' => $detallesBanco->first()?->BANCO_CERRADO ? 'CERRADO' : 'ABIERTO',
                'total_trozas' => $detallesBanco->sum('CANTIDAD_TROZAS'),
                'fecha_cierre' => $detallesBanco->first()?->FECHA_CIERRE_BANCO,
                'foto_url' => $detallesBanco->first()?->FOTO_PATH ? Storage::url($detallesBanco->first()->FOTO_PATH) : null,
                'gps' => [
                    'latitud' => $detallesBanco->first()?->GPS_LATITUD,
                    'longitud' => $detallesBanco->first()?->GPS_LONGITUD,
                    'accuracy' => $detallesBanco->first()?->GPS_ACCURACY
                ],
                'combinaciones' => $detallesBanco->map(function($detalle) {
                    return [
                        'diametro_cm' => $detalle->DIAMETRO_CM,
                        'largo_m' => $detalle->LARGO_M,
                        'cantidad' => $detalle->CANTIDAD_TROZAS
                    ];
                })
            ];
        }
        
        return $bancos;
    }

    /**
     * Obtener resumen por diámetro
     */
    private function getResumenPorDiametro($registro)
    {
        return $registro->detalles()
            ->selectRaw('DIAMETRO_CM, SUM(CANTIDAD_TROZAS) as total')
            ->groupBy('DIAMETRO_CM')
            ->orderBy('DIAMETRO_CM')
            ->get();
    }

    /**
     * Obtener resumen por largo (NUEVO)
     */
    private function getResumenPorLargo($registro)
    {
        return $registro->detalles()
            ->selectRaw('LARGO_M, SUM(CANTIDAD_TROZAS) as total')
            ->groupBy('LARGO_M')
            ->orderBy('LARGO_M')
            ->get();
    }

    /**
     * Recalcular total de trozas
     */
    private function recalcularTotalTrozas($registro)
    {
        $total = $registro->detalles()->sum('CANTIDAD_TROZAS');
        $registro->update(['TOTAL_TROZAS' => $total]);
        return $total;
    }
}