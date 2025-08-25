<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chofer;
use App\Models\Transporte;
use Illuminate\Http\JsonResponse;

class CamionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Obtener lista de choferes activos
     */
    public function getChoferes(): JsonResponse
    {
        try {
            $choferes = Chofer::with('transporte')
                              ->active()
                              ->orderBy('NOMBRE_CHOFER')
                              ->get();

            $data = $choferes->map(function($chofer) {
                return [
                    'id' => $chofer->ID_CHOFER,
                    'rut' => $chofer->RUT_CHOFER,
                    'rut_formatted' => $chofer->formatted_rut,
                    'nombre' => $chofer->NOMBRE_CHOFER,
                    'telefono' => $chofer->TELEFONO,
                    'transporte' => [
                        'id' => $chofer->transporte->ID_TRANSPORTE,
                        'nombre' => $chofer->transporte->NOMBRE_TRANSPORTES,
                        'rut' => $chofer->transporte->RUT
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de empresas de transporte activas
     */
    public function getTransportes(): JsonResponse
    {
        try {
            $transportes = Transporte::active()
                                    ->orderBy('NOMBRE_TRANSPORTES')
                                    ->get();

            $data = $transportes->map(function($transporte) {
                return [
                    'id' => $transporte->ID_TRANSPORTE,
                    'nombre' => $transporte->NOMBRE_TRANSPORTES,
                    'rut' => $transporte->RUT,
                    'traslado_bodegas' => $transporte->TRASLADO_BODEGAS,
                    'fecha_creacion' => $transporte->DATECREATE,
                    'total_choferes' => $transporte->choferes()->active()->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener transportes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener choferes de un transporte específico
     */
    public function getChoferesByTransporte($transporteId): JsonResponse
    {
        try {
            // Verificar que el transporte existe
            $transporte = Transporte::active()->find($transporteId);
            
            if (!$transporte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa de transporte no encontrada'
                ], 404);
            }

            $choferes = Chofer::where('ID_TRANSPORTE', $transporteId)
                              ->active()
                              ->orderBy('NOMBRE_CHOFER')
                              ->get();

            $data = $choferes->map(function($chofer) {
                return [
                    'id' => $chofer->ID_CHOFER,
                    'rut' => $chofer->RUT_CHOFER,
                    'rut_formatted' => $chofer->formatted_rut,
                    'nombre' => $chofer->NOMBRE_CHOFER,
                    'telefono' => $chofer->TELEFONO,
                    'fecha_creacion' => $chofer->DATECREATE
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'transporte' => [
                        'id' => $transporte->ID_TRANSPORTE,
                        'nombre' => $transporte->NOMBRE_TRANSPORTES
                    ],
                    'choferes' => $data
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar choferes por nombre o RUT
     */
    public function searchChoferes(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $search = $request->get('q');
            
            if (!$search || strlen($search) < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'El término de búsqueda debe tener al menos 3 caracteres'
                ], 400);
            }

            $choferes = Chofer::with('transporte')
                              ->active()
                              ->where(function($query) use ($search) {
                                  $query->where('NOMBRE_CHOFER', 'like', "%{$search}%")
                                        ->orWhere('RUT_CHOFER', 'like', "%{$search}%");
                              })
                              ->orderBy('NOMBRE_CHOFER')
                              ->limit(20)
                              ->get();

            $data = $choferes->map(function($chofer) {
                return [
                    'id' => $chofer->ID_CHOFER,
                    'rut' => $chofer->RUT_CHOFER,
                    'rut_formatted' => $chofer->formatted_rut,
                    'nombre' => $chofer->NOMBRE_CHOFER,
                    'telefono' => $chofer->TELEFONO,
                    'transporte' => [
                        'id' => $chofer->transporte->ID_TRANSPORTE,
                        'nombre' => $chofer->transporte->NOMBRE_TRANSPORTES
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $choferes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
}