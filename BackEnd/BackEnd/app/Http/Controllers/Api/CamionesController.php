<?php
// app/Http/Controllers/Api/CamionesController.php - VERSIÓN 2.0 OPTIMIZADA

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chofer;
use App\Models\Transporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CamionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Health check específico para módulo camiones
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $stats = [
                'database_connection' => true,
                'transportes_count' => Transporte::where('VIGENCIA', 1)->count(),
                'choferes_count' => Chofer::where('VIGENCIA', 1)->count(),
                'server_time' => now()->toISOString(),
                'version' => '2.0.0'
            ];

            return response()->json([
                'success' => true,
                'status' => 'OK',
                'message' => 'Módulo camiones funcionando correctamente',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Error en módulo camiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronización completa de datos (NUEVO ENDPOINT)
     */
    public function syncAll(): JsonResponse
    {
        try {
            $cacheKey = 'camiones_sync_data';
            $cacheTTL = 300; // 5 minutos

            $data = Cache::remember($cacheKey, $cacheTTL, function () {
                // Obtener transportes con estadísticas
                $transportes = Transporte::select([
                        'ID_TRANSPORTE as id',
                        'NOMBRE_TRANSPORTES as nombre',
                        'RUT as rut',
                        'CONTACTO_NOMBRE as contacto_nombre',
                        'CONTACTO_TELEFONO as contacto_telefono',
                        'CONTACTO_CORREO as contacto_correo',
                        'TRASLADO_BODEGAS as traslado_bodegas',
                        'DATECREATE as fecha_creacion'
                    ])
                    ->selectRaw('(SELECT COUNT(*) FROM CHOFERES_PACK WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE AND VIGENCIA = 1) as total_choferes')
                    ->where('VIGENCIA', 1)
                    ->orderBy('NOMBRE_TRANSPORTES')
                    ->get()
                    ->map(function($transporte) {
                        return [
                            'id' => $transporte->id,
                            'nombre' => $transporte->nombre,
                            'rut' => $this->formatRut($transporte->rut),
                            'contacto_nombre' => $transporte->contacto_nombre,
                            'contacto_telefono' => $transporte->contacto_telefono,
                            'contacto_correo' => $transporte->contacto_correo,
                            'traslado_bodegas' => (bool) $transporte->traslado_bodegas,
                            'total_choferes' => $transporte->total_choferes,
                            'fecha_creacion' => $transporte->fecha_creacion
                        ];
                    });

                // Obtener choferes con información de transporte
                $choferes = Chofer::select([
                        'CHOFERES_PACK.ID_CHOFER as id',
                        'CHOFERES_PACK.RUT_CHOFER as rut',
                        'CHOFERES_PACK.NOMBRE_CHOFER as nombre',
                        'CHOFERES_PACK.TELEFONO as telefono',
                        'CHOFERES_PACK.ID_TRANSPORTE as transporte_id',
                        'CHOFERES_PACK.DATECREATE as fecha_creacion',
                        'TRANSPORTES_PACK.NOMBRE_TRANSPORTES as transporte_nombre',
                        'TRANSPORTES_PACK.RUT as transporte_rut'
                    ])
                    ->join('TRANSPORTES_PACK', 'CHOFERES_PACK.ID_TRANSPORTE', '=', 'TRANSPORTES_PACK.ID_TRANSPORTE')
                    ->where('CHOFERES_PACK.VIGENCIA', 1)
                    ->where('TRANSPORTES_PACK.VIGENCIA', 1)
                    ->orderBy('CHOFERES_PACK.NOMBRE_CHOFER')
                    ->get()
                    ->map(function($chofer) {
                        return [
                            'id' => $chofer->id,
                            'rut' => $this->formatRut($chofer->rut),
                            'nombre' => $chofer->nombre,
                            'telefono' => $chofer->telefono,
                            'fecha_creacion' => $chofer->fecha_creacion,
                            'transporte' => [
                                'id' => $chofer->transporte_id,
                                'nombre' => $chofer->transporte_nombre,
                                'rut' => $this->formatRut($chofer->transporte_rut)
                            ]
                        ];
                    });

                return [
                    'transportes' => $transportes,
                    'choferes' => $choferes,
                    'meta' => [
                        'total_transportes' => $transportes->count(),
                        'total_choferes' => $choferes->count(),
                        'transportes_con_choferes' => $transportes->where('total_choferes', '>', 0)->count(),
                        'sync_timestamp' => now()->toISOString(),
                        'cache_ttl' => 300
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completa exitosa',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sincronización completa de camiones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de transportes optimizada
     */
    public function getTransportes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $includeStats = $request->get('include_stats', true);
            $perPage = $request->get('per_page', 100);

            $query = Transporte::select([
                    'ID_TRANSPORTE as id',
                    'NOMBRE_TRANSPORTES as nombre',
                    'RUT as rut',
                    'CONTACTO_NOMBRE as contacto_nombre',
                    'CONTACTO_TELEFONO as contacto_telefono',
                    'CONTACTO_CORREO as contacto_correo',
                    'TRASLADO_BODEGAS as traslado_bodegas',
                    'DATECREATE as fecha_creacion'
                ]);

            if ($includeStats) {
                $query->selectRaw('(SELECT COUNT(*) FROM CHOFERES_PACK WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE AND VIGENCIA = 1) as total_choferes');
            }

            $query->where('VIGENCIA', 1);

            // Filtros de búsqueda optimizados
            if ($search) {
                $searchTerm = '%' . $search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('NOMBRE_TRANSPORTES', 'like', $searchTerm)
                      ->orWhere('RUT', 'like', str_replace(['.', '-', ' '], ['%', '%', '%'], $searchTerm))
                      ->orWhere('CONTACTO_NOMBRE', 'like', $searchTerm);
                });
            }

            // Usar paginación para datasets grandes
            if ($perPage > 0) {
                $result = $query->orderBy('NOMBRE_TRANSPORTES')->paginate($perPage);
                $transportes = $result->items();
                $meta = [
                    'current_page' => $result->currentPage(),
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'last_page' => $result->lastPage()
                ];
            } else {
                $transportes = $query->orderBy('NOMBRE_TRANSPORTES')->get();
                $meta = ['total' => count($transportes)];
            }

            // Formatear datos
            $data = collect($transportes)->map(function($transporte) {
                return [
                    'id' => $transporte->id,
                    'nombre' => $transporte->nombre,
                    'rut' => $this->formatRut($transporte->rut),
                    'contacto_nombre' => $transporte->contacto_nombre,
                    'contacto_telefono' => $transporte->contacto_telefono,
                    'contacto_correo' => $transporte->contacto_correo,
                    'traslado_bodegas' => (bool) $transporte->traslado_bodegas,
                    'total_choferes' => $transporte->total_choferes ?? 0,
                    'fecha_creacion' => $transporte->fecha_creacion
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => $meta
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo transportes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener transportes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de choferes optimizada
     */
    public function getChoferes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $transporteId = $request->get('transporte_id');
            $perPage = $request->get('per_page', 100);

            $query = Chofer::select([
                    'CHOFERES_PACK.ID_CHOFER as id',
                    'CHOFERES_PACK.RUT_CHOFER as rut',
                    'CHOFERES_PACK.NOMBRE_CHOFER as nombre',
                    'CHOFERES_PACK.TELEFONO as telefono',
                    'CHOFERES_PACK.ID_TRANSPORTE as transporte_id',
                    'CHOFERES_PACK.DATECREATE as fecha_creacion',
                    'TRANSPORTES_PACK.NOMBRE_TRANSPORTES as transporte_nombre',
                    'TRANSPORTES_PACK.RUT as transporte_rut'
                ])
                ->join('TRANSPORTES_PACK', 'CHOFERES_PACK.ID_TRANSPORTE', '=', 'TRANSPORTES_PACK.ID_TRANSPORTE')
                ->where('CHOFERES_PACK.VIGENCIA', 1)
                ->where('TRANSPORTES_PACK.VIGENCIA', 1);

            // Filtro por transporte
            if ($transporteId) {
                $query->where('CHOFERES_PACK.ID_TRANSPORTE', $transporteId);
            }

            // Filtros de búsqueda
            if ($search) {
                $searchTerm = '%' . $search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('CHOFERES_PACK.NOMBRE_CHOFER', 'like', $searchTerm)
                      ->orWhere('CHOFERES_PACK.RUT_CHOFER', 'like', str_replace(['.', '-', ' '], ['%', '%', '%'], $searchTerm))
                      ->orWhere('TRANSPORTES_PACK.NOMBRE_TRANSPORTES', 'like', $searchTerm);
                });
            }

            // Paginación
            if ($perPage > 0) {
                $result = $query->orderBy('CHOFERES_PACK.NOMBRE_CHOFER')->paginate($perPage);
                $choferes = $result->items();
                $meta = [
                    'current_page' => $result->currentPage(),
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'last_page' => $result->lastPage()
                ];
            } else {
                $choferes = $query->orderBy('CHOFERES_PACK.NOMBRE_CHOFER')->get();
                $meta = ['total' => count($choferes)];
            }

            // Formatear datos
            $data = collect($choferes)->map(function($chofer) {
                return [
                    'id' => $chofer->id,
                    'rut' => $this->formatRut($chofer->rut),
                    'nombre' => $chofer->nombre,
                    'telefono' => $chofer->telefono,
                    'fecha_creacion' => $chofer->fecha_creacion,
                    'transporte' => [
                        'id' => $chofer->transporte_id,
                        'nombre' => $chofer->transporte_nombre,
                        'rut' => $this->formatRut($chofer->transporte_rut)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => $meta
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo choferes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes: ' . $e->getMessage()
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
            $transporte = Transporte::select(['ID_TRANSPORTE as id', 'NOMBRE_TRANSPORTES as nombre', 'RUT as rut'])
                                   ->where('ID_TRANSPORTE', $transporteId)
                                   ->where('VIGENCIA', 1)
                                   ->first();
            
            if (!$transporte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa de transporte no encontrada'
                ], 404);
            }

            $choferes = Chofer::select([
                    'ID_CHOFER as id',
                    'RUT_CHOFER as rut',
                    'NOMBRE_CHOFER as nombre',
                    'TELEFONO as telefono',
                    'DATECREATE as fecha_creacion'
                ])
                ->where('ID_TRANSPORTE', $transporteId)
                ->where('VIGENCIA', 1)
                ->orderBy('NOMBRE_CHOFER')
                ->get()
                ->map(function($chofer) {
                    return [
                        'id' => $chofer->id,
                        'rut' => $this->formatRut($chofer->rut),
                        'nombre' => $chofer->nombre,
                        'telefono' => $chofer->telefono,
                        'fecha_creacion' => $chofer->fecha_creacion
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'transporte' => [
                        'id' => $transporte->id,
                        'nombre' => $transporte->nombre,
                        'rut' => $this->formatRut($transporte->rut)
                    ],
                    'choferes' => $choferes
                ],
                'meta' => [
                    'total_choferes' => $choferes->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo choferes por transporte: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Búsqueda inteligente de choferes (MEJORADO)
     */
    public function searchChoferes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q');
            $transporteId = $request->get('transporte_id');
            $limit = $request->get('limit', 20);
            
            if (!$search || strlen($search) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El término de búsqueda debe tener al menos 2 caracteres'
                ], 400);
            }

            $query = Chofer::select([
                    'CHOFERES_PACK.ID_CHOFER as id',
                    'CHOFERES_PACK.RUT_CHOFER as rut',
                    'CHOFERES_PACK.NOMBRE_CHOFER as nombre',
                    'CHOFERES_PACK.TELEFONO as telefono',
                    'TRANSPORTES_PACK.ID_TRANSPORTE as transporte_id',
                    'TRANSPORTES_PACK.NOMBRE_TRANSPORTES as transporte_nombre'
                ])
                ->join('TRANSPORTES_PACK', 'CHOFERES_PACK.ID_TRANSPORTE', '=', 'TRANSPORTES_PACK.ID_TRANSPORTE')
                ->where('CHOFERES_PACK.VIGENCIA', 1)
                ->where('TRANSPORTES_PACK.VIGENCIA', 1);

            // Filtro por transporte específico
            if ($transporteId) {
                $query->where('CHOFERES_PACK.ID_TRANSPORTE', $transporteId);
            }

            // Búsqueda inteligente con scoring
            $searchTerm = '%' . $search . '%';
            $searchClean = str_replace(['.', '-', ' '], ['%', '%', '%'], $search);
            
            $query->where(function($q) use ($searchTerm, $searchClean) {
                $q->where('CHOFERES_PACK.NOMBRE_CHOFER', 'like', $searchTerm)
                  ->orWhere('CHOFERES_PACK.RUT_CHOFER', 'like', $searchClean)
                  ->orWhere('TRANSPORTES_PACK.NOMBRE_TRANSPORTES', 'like', $searchTerm);
            });

            $choferes = $query->orderByRaw("
                    CASE 
                        WHEN CHOFERES_PACK.NOMBRE_CHOFER LIKE ? THEN 1
                        WHEN CHOFERES_PACK.RUT_CHOFER LIKE ? THEN 2
                        ELSE 3
                    END, CHOFERES_PACK.NOMBRE_CHOFER
                ", [$search . '%', $searchClean . '%'])
                ->limit($limit)
                ->get()
                ->map(function($chofer) {
                    return [
                        'id' => $chofer->id,
                        'rut' => $this->formatRut($chofer->rut),
                        'nombre' => $chofer->nombre,
                        'telefono' => $chofer->telefono,
                        'transporte' => [
                            'id' => $chofer->transporte_id,
                            'nombre' => $chofer->transporte_nombre
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $choferes,
                'meta' => [
                    'total' => $choferes->count(),
                    'search_term' => $search,
                    'filtered_by_transporte' => $transporteId ? true : false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de choferes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Búsqueda inteligente de transportes (NUEVO)
     */
    public function searchTransportes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q');
            $limit = $request->get('limit', 20);
            
            if (!$search || strlen($search) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El término de búsqueda debe tener al menos 2 caracteres'
                ], 400);
            }

            $searchTerm = '%' . $search . '%';
            $searchClean = str_replace(['.', '-', ' '], ['%', '%', '%'], $search);
            
            $transportes = Transporte::select([
                    'ID_TRANSPORTE as id',
                    'NOMBRE_TRANSPORTES as nombre',
                    'RUT as rut',
                    'CONTACTO_NOMBRE as contacto_nombre',
                    'CONTACTO_TELEFONO as contacto_telefono'
                ])
                ->selectRaw('(SELECT COUNT(*) FROM CHOFERES_PACK WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE AND VIGENCIA = 1) as total_choferes')
                ->where('VIGENCIA', 1)
                ->where(function($q) use ($searchTerm, $searchClean) {
                    $q->where('NOMBRE_TRANSPORTES', 'like', $searchTerm)
                      ->orWhere('RUT', 'like', $searchClean)
                      ->orWhere('CONTACTO_NOMBRE', 'like', $searchTerm);
                })
                ->orderByRaw("
                    CASE 
                        WHEN NOMBRE_TRANSPORTES LIKE ? THEN 1
                        WHEN RUT LIKE ? THEN 2
                        ELSE 3
                    END, NOMBRE_TRANSPORTES
                ", [$search . '%', $searchClean . '%'])
                ->limit($limit)
                ->get()
                ->map(function($transporte) {
                    return [
                        'id' => $transporte->id,
                        'nombre' => $transporte->nombre,
                        'rut' => $this->formatRut($transporte->rut),
                        'contacto_nombre' => $transporte->contacto_nombre,
                        'contacto_telefono' => $transporte->contacto_telefono,
                        'total_choferes' => $transporte->total_choferes
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $transportes,
                'meta' => [
                    'total' => $transportes->count(),
                    'search_term' => $search
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de transportes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales (NUEVO)
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = Cache::remember('camiones_stats', 300, function () {
                return [
                    'transportes' => [
                        'total' => Transporte::where('VIGENCIA', 1)->count(),
                        'con_choferes' => Transporte::where('VIGENCIA', 1)
                                                   ->whereHas('choferes', function($query) {
                                                       $query->where('VIGENCIA', 1);
                                                   })
                                                   ->count(),
                        'sin_choferes' => Transporte::where('VIGENCIA', 1)
                                                   ->whereDoesntHave('choferes', function($query) {
                                                       $query->where('VIGENCIA', 1);
                                                   })
                                                   ->count()
                    ],
                    'choferes' => [
                        'total' => Chofer::where('VIGENCIA', 1)->count(),
                        'con_telefono' => Chofer::where('VIGENCIA', 1)
                                                ->whereNotNull('TELEFONO')
                                                ->where('TELEFONO', '!=', '')
                                                ->count()
                    ],
                    'updated_at' => now()->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear RUT chileno
     */
    private function formatRut($rut): string
    {
        if (!$rut) return '';
        
        // Limpiar RUT
        $cleanRut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($cleanRut) < 8) return $rut;
        
        // Separar número y DV
        $number = substr($cleanRut, 0, -1);
        $dv = strtoupper(substr($cleanRut, -1));
        
        // Formatear con puntos
        $formattedNumber = number_format($number, 0, '', '.');
        
        return $formattedNumber . '-' . $dv;
    }

    /**
     * Limpiar cache (NUEVO)
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::forget('camiones_sync_data');
            Cache::forget('camiones_stats');
            
            return response()->json([
                'success' => true,
                'message' => 'Cache limpiado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error limpiando cache: ' . $e->getMessage()
            ], 500);
        }
    }
}