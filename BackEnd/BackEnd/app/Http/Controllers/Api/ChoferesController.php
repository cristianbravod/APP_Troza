<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chofer;
use App\Models\Transporte;
use App\Models\TrozaHead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChoferesController extends Controller
{
    /**
     * Listar todos los choferes con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|min:2|max:100',
            'transporte_id' => 'nullable|integer|exists:TRANSPORTES_PACK,ID_TRANSPORTE',
            'vigencia' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:NOMBRE_CHOFER,RUT_CHOFER,TELEFONO,DATECREATE',
            'sort_order' => 'nullable|string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $perPage = min($request->get('per_page', 20), 100);
            $search = $request->get('search', '');
            $transporteId = $request->get('transporte_id');
            $vigencia = $request->get('vigencia', true);
            $sortBy = $request->get('sort_by', 'NOMBRE_CHOFER');
            $sortOrder = $request->get('sort_order', 'asc');

            $query = Chofer::with(['transporte' => function($query) {
                $query->select('ID_TRANSPORTE', 'NOMBRE_TRANSPORTES', 'RUT');
            }]);

            // Filtros
            if ($vigencia) {
                $query->where('VIGENCIA', 1);
            }

            if ($transporteId) {
                $query->where('ID_TRANSPORTE', $transporteId);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $searchTerm = '%' . $search . '%';
                    $q->where('NOMBRE_CHOFER', 'like', $searchTerm)
                      ->orWhere('RUT_CHOFER', 'like', $searchTerm)
                      ->orWhereHas('transporte', function($subQ) use ($searchTerm) {
                          $subQ->where('NOMBRE_TRANSPORTES', 'like', $searchTerm);
                      });
                });
            }

            // Ordenamiento
            $query->orderBy($sortBy, $sortOrder);

            $choferes = $query->paginate($perPage);

            // Formatear datos
            $choferes->getCollection()->transform(function($chofer) {
                $chofer->rut_formatted = $this->formatRut($chofer->RUT_CHOFER);
                $chofer->telefono_formatted = $this->formatTelefono($chofer->TELEFONO);
                
                // Agregar estadísticas básicas
                $chofer->registros_count = $this->getRegistrosCount($chofer->ID_CHOFER);
                $chofer->ultimo_registro = $this->getUltimoRegistro($chofer->ID_CHOFER);
                
                return $chofer;
            });

            return response()->json([
                'success' => true,
                'data' => $choferes->items(),
                'pagination' => [
                    'current_page' => $choferes->currentPage(),
                    'per_page' => $choferes->perPage(),
                    'total' => $choferes->total(),
                    'last_page' => $choferes->lastPage(),
                    'from' => $choferes->firstItem(),
                    'to' => $choferes->lastItem(),
                ],
                'filters' => [
                    'search' => $search,
                    'transporte_id' => $transporteId,
                    'vigencia' => $vigencia
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lista de choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Búsqueda inteligente de choferes
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|min:2|max:100',
            'transporte_id' => 'nullable|integer|exists:TRANSPORTES_PACK,ID_TRANSPORTE',
            'limit' => 'nullable|integer|min:1|max:50',
            'vigencia' => 'nullable|boolean',
            'include_stats' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de búsqueda inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $search = $request->get('search', '');
            $transporteId = $request->get('transporte_id');
            $limit = min($request->get('limit', 20), 50);
            $vigencia = $request->get('vigencia', true);
            $includeStats = $request->get('include_stats', false);

            // Cache key basado en parámetros
            $cacheKey = "choferes_search_" . md5($search . $transporteId . $limit . $vigencia . $includeStats);
            
            $choferes = Cache::remember($cacheKey, 1800, function () use ($search, $transporteId, $limit, $vigencia, $includeStats) {
                $query = Chofer::with(['transporte' => function($query) {
                    $query->select('ID_TRANSPORTE', 'NOMBRE_TRANSPORTES', 'RUT', 'CONTACTO_TELEFONO');
                }]);

                // Filtros básicos
                if ($vigencia) {
                    $query->where('VIGENCIA', 1);
                }

                if ($transporteId) {
                    $query->where('ID_TRANSPORTE', $transporteId);
                }

                // Búsqueda por texto
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $searchTerm = '%' . $search . '%';
                        $q->where('NOMBRE_CHOFER', 'like', $searchTerm)
                          ->orWhere('RUT_CHOFER', 'like', $searchTerm)
                          ->orWhereHas('transporte', function($subQ) use ($searchTerm) {
                              $subQ->where('NOMBRE_TRANSPORTES', 'like', $searchTerm);
                          });
                    });
                }

                $results = $query->select([
                        'ID_CHOFER',
                        'RUT_CHOFER',
                        'NOMBRE_CHOFER',
                        'TELEFONO',
                        'ID_TRANSPORTE',
                        'DATECREATE'
                    ])
                    ->orderBy('NOMBRE_CHOFER')
                    ->limit($limit)
                    ->get();

                // Formatear y agregar estadísticas si se solicitan
                return $results->map(function($chofer) use ($includeStats) {
                    $chofer->rut_formatted = $this->formatRut($chofer->RUT_CHOFER);
                    $chofer->telefono_formatted = $this->formatTelefono($chofer->TELEFONO);
                    
                    if ($includeStats) {
                        $chofer->registros_count = $this->getRegistrosCount($chofer->ID_CHOFER);
                        $chofer->ultimo_registro = $this->getUltimoRegistro($chofer->ID_CHOFER);
                        $chofer->registros_mes_actual = $this->getRegistrosMesActual($chofer->ID_CHOFER);
                    }
                    
                    return $chofer;
                });
            });

            return response()->json([
                'success' => true,
                'data' => $choferes,
                'total' => $choferes->count(),
                'search_term' => $search,
                'transporte_filter' => $transporteId,
                'cache_key' => $cacheKey // Para debug
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda de choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener chofer específico con detalles completos
     */
    public function show($id): JsonResponse
    {
        try {
            $chofer = Cache::remember("chofer_detail_{$id}", 1800, function() use ($id) {
                return Chofer::with([
                        'transporte' => function($query) {
                            $query->select('ID_TRANSPORTE', 'NOMBRE_TRANSPORTES', 'RUT', 
                                         'CONTACTO_TELEFONO', 'CONTACTO_NOMBRE', 'CONTACTO_CORREO');
                        }
                    ])
                    ->where('ID_CHOFER', $id)
                    ->where('VIGENCIA', 1)
                    ->first();
            });

            if (!$chofer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chofer no encontrado o inactivo'
                ], 404);
            }

            // Agregar información adicional
            $chofer->rut_formatted = $this->formatRut($chofer->RUT_CHOFER);
            $chofer->telefono_formatted = $this->formatTelefono($chofer->TELEFONO);

            // Estadísticas detalladas del chofer
            $chofer->estadisticas = [
                'registros_totales' => $this->getRegistrosCount($chofer->ID_CHOFER),
                'registros_mes_actual' => $this->getRegistrosMesActual($chofer->ID_CHOFER),
                'registros_año_actual' => $this->getRegistrosAñoActual($chofer->ID_CHOFER),
                'ultimo_registro' => $this->getUltimoRegistro($chofer->ID_CHOFER),
                'total_trozas' => $this->getTotalTrozasChofer($chofer->ID_CHOFER),
                'promedio_trozas_por_registro' => $this->getPromedioTrozasPorRegistro($chofer->ID_CHOFER)
            ];

            // Historial reciente de registros (últimos 10)
            $chofer->registros_recientes = $this->getRegistrosRecientes($chofer->ID_CHOFER);

            return response()->json([
                'success' => true,
                'data' => $chofer
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener chofer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar RUT de chofer
     */
    public function validateRut(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rut' => 'required|string|max:12',
            'chofer_id' => 'nullable|integer|exists:CHOFERES_PACK,ID_CHOFER'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rut = $request->get('rut');
            $choferId = $request->get('chofer_id');
            
            // Validar formato RUT chileno
            $isValid = $this->validateChileanRut($rut);
            
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'RUT inválido',
                    'valid' => false,
                    'error_type' => 'format'
                ]);
            }

            // Verificar si el RUT ya existe (excluyendo el chofer actual si se especifica)
            $existsQuery = Chofer::where('RUT_CHOFER', $rut)->where('VIGENCIA', 1);
            
            if ($choferId) {
                $existsQuery->where('ID_CHOFER', '!=', $choferId);
            }
            
            $exists = $existsQuery->exists();
            $existingChofer = $exists ? $existsQuery->first() : null;

            return response()->json([
                'success' => true,
                'valid' => true,
                'exists' => $exists,
                'formatted_rut' => $this->formatRut($rut),
                'existing_chofer' => $existingChofer ? [
                    'id' => $existingChofer->ID_CHOFER,
                    'nombre' => $existingChofer->NOMBRE_CHOFER,
                    'transporte_id' => $existingChofer->ID_TRANSPORTE
                ] : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar RUT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener choferes por transporte específico
     */
    public function getByTransporte(Request $request, $transporteId): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['transporte_id' => $transporteId]), [
            'transporte_id' => 'required|integer|exists:TRANSPORTES_PACK,ID_TRANSPORTE',
            'search' => 'nullable|string|min:2|max:100',
            'vigencia' => 'nullable|boolean',
            'include_stats' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $search = $request->get('search', '');
            $vigencia = $request->get('vigencia', true);
            $includeStats = $request->get('include_stats', false);

            $query = Chofer::where('ID_TRANSPORTE', $transporteId);

            if ($vigencia) {
                $query->where('VIGENCIA', 1);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $searchTerm = '%' . $search . '%';
                    $q->where('NOMBRE_CHOFER', 'like', $searchTerm)
                      ->orWhere('RUT_CHOFER', 'like', $searchTerm);
                });
            }

            $choferes = $query->select([
                    'ID_CHOFER',
                    'RUT_CHOFER',
                    'NOMBRE_CHOFER',
                    'TELEFONO',
                    'ID_TRANSPORTE',
                    'DATECREATE'
                ])
                ->orderBy('NOMBRE_CHOFER')
                ->get();

            // Formatear y agregar estadísticas
            $choferes->transform(function($chofer) use ($includeStats) {
                $chofer->rut_formatted = $this->formatRut($chofer->RUT_CHOFER);
                $chofer->telefono_formatted = $this->formatTelefono($chofer->TELEFONO);
                
                if ($includeStats) {
                    $chofer->registros_count = $this->getRegistrosCount($chofer->ID_CHOFER);
                    $chofer->ultimo_registro = $this->getUltimoRegistro($chofer->ID_CHOFER);
                }
                
                return $chofer;
            });

            return response()->json([
                'success' => true,
                'data' => $choferes,
                'total' => $choferes->count(),
                'transporte_id' => $transporteId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes del transporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales de choferes
     */
    public function getEstadisticas(): JsonResponse
    {
        try {
            $stats = Cache::remember('choferes_estadisticas_general', 3600, function() {
                return [
                    'total_choferes' => Chofer::where('VIGENCIA', 1)->count(),
                    'choferes_activos_mes' => $this->getChoferesActivosMes(),
                    'choferes_por_transporte' => $this->getChoferesGroupedByTransporte(),
                    'top_choferes_registros' => $this->getTopChoferesPorRegistros(),
                    'choferes_sin_registros' => $this->getChoferesSinRegistros(),
                    'promedio_registros_por_chofer' => $this->getPromedioRegistrosPorChofer()
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

    // ========================================
    // MÉTODOS PRIVADOS AUXILIARES
    // ========================================

    /**
     * Validar RUT chileno
     */
    private function validateChileanRut($rut): bool
    {
        // Limpiar RUT
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        if (!is_numeric($number)) {
            return false;
        }
        
        return $dv === $this->calculateDV($number);
    }

    /**
     * Calcular dígito verificador
     */
    private function calculateDV($number): string
    {
        $factor = 2;
        $sum = 0;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $factor;
            $factor = $factor == 7 ? 2 : $factor + 1;
        }
        
        $dv = 11 - ($sum % 11);
        
        if ($dv == 11) return '0';
        if ($dv == 10) return 'K';
        
        return (string)$dv;
    }

    /**
     * Formatear RUT
     */
    private function formatRut($rut): string
    {
        if (empty($rut)) return '';
        
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        
        if (strlen($rut) < 8) {
            return $rut;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        return number_format($number, 0, '', '.') . '-' . $dv;
    }

    /**
     * Formatear teléfono
     */
    private function formatTelefono($telefono): ?string
    {
        if (empty($telefono)) return null;
        
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        if (strlen($telefono) == 9 && substr($telefono, 0, 1) == '9') {
            // Celular: +56 9 XXXX XXXX
            return '+56 ' . substr($telefono, 0, 1) . ' ' . substr($telefono, 1, 4) . ' ' . substr($telefono, 5);
        } elseif (strlen($telefono) == 8) {
            // Fijo: +56 XX XXX XXXX
            return '+56 ' . substr($telefono, 0, 2) . ' ' . substr($telefono, 2, 3) . ' ' . substr($telefono, 5);
        }
        
        return $telefono;
    }

    /**
     * Obtener conteo de registros por chofer
     */
    private function getRegistrosCount($choferId): int
    {
        return Cache::remember("chofer_registros_count_{$choferId}", 1800, function() use ($choferId) {
            return TrozaHead::where('ID_CHOFER', $choferId)->count();
        });
    }

    /**
     * Obtener último registro del chofer
     */
    private function getUltimoRegistro($choferId): ?string
    {
        return Cache::remember("chofer_ultimo_registro_{$choferId}", 1800, function() use ($choferId) {
            $registro = TrozaHead::where('ID_CHOFER', $choferId)
                                ->orderBy('FECHA_INICIO', 'desc')
                                ->first();
            
            return $registro ? $registro->FECHA_INICIO : null;
        });
    }

    /**
     * Obtener registros del mes actual
     */
    private function getRegistrosMesActual($choferId): int
    {
        return TrozaHead::where('ID_CHOFER', $choferId)
                       ->whereMonth('FECHA_INICIO', now()->month)
                       ->whereYear('FECHA_INICIO', now()->year)
                       ->count();
    }

    /**
     * Obtener registros del año actual
     */
    private function getRegistrosAñoActual($choferId): int
    {
        return TrozaHead::where('ID_CHOFER', $choferId)
                       ->whereYear('FECHA_INICIO', now()->year)
                       ->count();
    }

    /**
     * Obtener total de trozas del chofer
     */
    private function getTotalTrozasChofer($choferId): int
    {
        return TrozaHead::where('ID_CHOFER', $choferId)
                       ->where('ESTADO', 'CERRADO')
                       ->sum('TOTAL_TROZAS') ?? 0;
    }

    /**
     * Obtener promedio de trozas por registro
     */
    private function getPromedioTrozasPorRegistro($choferId): float
    {
        $registros = TrozaHead::where('ID_CHOFER', $choferId)
                             ->where('ESTADO', 'CERRADO')
                             ->whereNotNull('TOTAL_TROZAS')
                             ->get(['TOTAL_TROZAS']);
        
        if ($registros->isEmpty()) {
            return 0;
        }
        
        return round($registros->avg('TOTAL_TROZAS'), 2);
    }

    /**
     * Obtener registros recientes del chofer
     */
    private function getRegistrosRecientes($choferId, $limit = 10): array
    {
        return TrozaHead::where('ID_CHOFER', $choferId)
                       ->orderBy('FECHA_INICIO', 'desc')
                       ->limit($limit)
                       ->get(['ID_REGISTRO', 'PATENTE_CAMION', 'FECHA_INICIO', 'ESTADO', 'TOTAL_TROZAS'])
                       ->toArray();
    }

    /**
     * Obtener choferes activos del mes
     */
    private function getChoferesActivosMes(): int
    {
        return Chofer::whereHas('registros', function($query) {
                    $query->whereMonth('FECHA_INICIO', now()->month)
                          ->whereYear('FECHA_INICIO', now()->year);
                })
                ->where('VIGENCIA', 1)
                ->count();
    }

    /**
     * Obtener choferes agrupados por transporte
     */
    private function getChoferesGroupedByTransporte(): array
    {
        return DB::table('CHOFERES_PACK as c')
                ->join('TRANSPORTES_PACK as t', 'c.ID_TRANSPORTE', '=', 't.ID_TRANSPORTE')
                ->where('c.VIGENCIA', 1)
                ->where('t.VIGENCIA', 1)
                ->groupBy('t.ID_TRANSPORTE', 't.NOMBRE_TRANSPORTES')
                ->selectRaw('t.ID_TRANSPORTE, t.NOMBRE_TRANSPORTES, COUNT(c.ID_CHOFER) as total_choferes')
                ->orderBy('total_choferes', 'desc')
                ->get()
                ->toArray();
    }

    /**
     * Obtener top choferes por número de registros
     */
    private function getTopChoferesPorRegistros($limit = 10): array
    {
        return DB::table('CHOFERES_PACK as c')
                ->leftJoin('ABAS_Troza_HEAD as th', 'c.ID_CHOFER', '=', 'th.ID_CHOFER')
                ->leftJoin('TRANSPORTES_PACK as t', 'c.ID_TRANSPORTE', '=', 't.ID_TRANSPORTE')
                ->where('c.VIGENCIA', 1)
                ->groupBy('c.ID_CHOFER', 'c.NOMBRE_CHOFER', 'c.RUT_CHOFER', 't.NOMBRE_TRANSPORTES')
                ->selectRaw('
                    c.ID_CHOFER,
                    c.NOMBRE_CHOFER,
                    c.RUT_CHOFER,
                    t.NOMBRE_TRANSPORTES,
                    COUNT(th.ID_REGISTRO) as total_registros,
                    SUM(CASE WHEN th.ESTADO = "CERRADO" THEN th.TOTAL_TROZAS ELSE 0 END) as total_trozas
                ')
                ->orderBy('total_registros', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
    }

    /**
     * Obtener choferes sin registros
     */
    private function getChoferesSinRegistros(): int
    {
        return Chofer::whereDoesntHave('registros')
                    ->where('VIGENCIA', 1)
                    ->count();
    }

    /**
     * Obtener promedio de registros por chofer
     */
    private function getPromedioRegistrosPorChofer(): float
    {
        $totalChoferes = Chofer::where('VIGENCIA', 1)->count();
        $totalRegistros = TrozaHead::count();
        
        return $totalChoferes > 0 ? round($totalRegistros / $totalChoferes, 2) : 0;
    }
}