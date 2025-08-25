<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\Module;
use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use App\Models\SyncLog;
use App\Models\Chofer;
use App\Models\Transporte;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
	public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('admin.access'); // Middleware personalizado para admin
    }

    /**
     * Dashboard principal con estadísticas generales
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = [
                // Estadísticas de registros
                'registros' => [
                    'total' => TrozaHead::count(),
                    'abiertos' => TrozaHead::where('ESTADO', 'ABIERTO')->count(),
                    'cerrados' => TrozaHead::where('ESTADO', 'CERRADO')->count(),
                    'hoy' => TrozaHead::whereDate('FECHA_INICIO', today())->count(),
                    'esta_semana' => TrozaHead::whereBetween('FECHA_INICIO', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])->count(),
                    'este_mes' => TrozaHead::whereMonth('FECHA_INICIO', now()->month)
                                          ->whereYear('FECHA_INICIO', now()->year)
                                          ->count()
                ],
                
                // Estadísticas de trozas
                'trozas' => [
                    'total' => TrozaHead::where('ESTADO', 'CERRADO')->sum('TOTAL_TROZAS'),
                    'promedio_por_registro' => TrozaHead::where('ESTADO', 'CERRADO')
                                                       ->where('TOTAL_TROZAS', '>', 0)
                                                       ->avg('TOTAL_TROZAS'),
                    'hoy' => TrozaHead::whereDate('FECHA_INICIO', today())
                                     ->where('ESTADO', 'CERRADO')
                                     ->sum('TOTAL_TROZAS')
                ],

                // Estadísticas de usuarios
                'usuarios' => [
                    'total' => User::where('User_Activo', true)->count(),
                    'con_acceso_app' => User::where('User_Activo', true)
                                           ->where('User_AppProd', true)
                                           ->count(),
                    'activos_hoy' => TrozaHead::whereDate('FECHA_INICIO', today())
                                             ->distinct('USER_ID')
                                             ->count('USER_ID')
                ],

                // Estadísticas de sincronización
                'sincronizacion' => [
                    'pendientes' => SyncLog::where('SYNC_STATUS', SyncLog::STATUS_PENDING)->count(),
                    'errores_24h' => SyncLog::where('SYNC_STATUS', SyncLog::STATUS_ERROR)
                                           ->where('CREATED_AT', '>', now()->subHours(24))
                                           ->count(),
                    'exitosas_24h' => SyncLog::where('SYNC_STATUS', SyncLog::STATUS_SUCCESS)
                                            ->where('CREATED_AT', '>', now()->subHours(24))
                                            ->count()
                ],

                // Estadísticas de transportes
                'transportes' => [
                    'total' => Transporte::where('VIGENCIA', 1)->count(),
                    'con_choferes' => Transporte::where('VIGENCIA', 1)
                                                ->whereHas('choferes', function($query) {
                                                    $query->where('VIGENCIA', 1);
                                                })
                                                ->count(),
                    'total_choferes' => Chofer::where('VIGENCIA', 1)->count()
                ]
            ];

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
     * Obtener lista de usuarios
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $activos = $request->get('activos');

            $query = User::with(['groups']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            if ($activos !== null) {
                $query->where('User_Activo', (bool)$activos);
            }

            $users = $query->orderBy('username')->paginate($perPage);

            // Agregar estadísticas por usuario
            $users->getCollection()->transform(function ($user) {
                $user->registros_count = $user->trozaRegistros()->count();
                $user->ultimo_registro = $user->trozaRegistros()
                                             ->orderBy('FECHA_INICIO', 'desc')
                                             ->first()?->FECHA_INICIO;
                $user->groups_names = $user->groups->pluck('name')->join(', ');
                return $user;
            });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de grupos
     */
    public function getGroups(): JsonResponse
    {
        try {
            $groups = Group::with(['modules', 'users'])
                          ->active()
                          ->orderBy('name')
                          ->get();

            $data = $groups->map(function($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'status' => $group->status,
                    'app_prod' => $group->AppProd,
                    'users_count' => $group->users->count(),
                    'modules_count' => $group->modules->count(),
                    'modules' => $group->modules->map(function($module) {
                        return [
                            'id' => $module->id,
                            'name' => $module->NAME,
                            'description' => $module->DESCRIPTION
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de módulos
     */
    public function getModules(): JsonResponse
    {
        try {
            $modules = Module::with(['groups'])
                            ->active()
                            ->ordered()
                            ->get();

            $data = $modules->map(function($module) {
                return [
                    'id' => $module->id,
                    'name' => $module->NAME,
                    'description' => $module->DESCRIPTION,
                    'url' => $module->URL,
                    'icon' => $module->ICON,
                    'priority' => $module->PRIORITY,
                    'dependency' => $module->DEPENDENCY,
                    'vigencia' => $module->VIGENCIA,
                    'groups_count' => $module->groups->count(),
                    'groups' => $module->groups->pluck('name')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas de registros
     */
    public function getRegistrosStats(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            $groupBy = $request->get('group_by', 'day'); // day, week, month

            // Estadísticas generales
            $stats = [
                'total_registros' => TrozaHead::count(),
                'registros_abiertos' => TrozaHead::where('ESTADO', 'ABIERTO')->count(),
                'registros_cerrados' => TrozaHead::where('ESTADO', 'CERRADO')->count(),
                'total_trozas' => TrozaHead::where('ESTADO', 'CERRADO')->sum('TOTAL_TROZAS'),
                'promedio_trozas_por_registro' => TrozaHead::where('ESTADO', 'CERRADO')
                                                         ->where('TOTAL_TROZAS', '>', 0)
                                                         ->avg('TOTAL_TROZAS')
            ];

            // Registros por período
            $dateFormat = match($groupBy) {
                'week' => "YEAR(FECHA_INICIO), WEEK(FECHA_INICIO)",
                'month' => "YEAR(FECHA_INICIO), MONTH(FECHA_INICIO)",
                default => "CAST(FECHA_INICIO AS DATE)"
            };

            $registrosPorPeriodo = TrozaHead::selectRaw("
                    {$dateFormat} as periodo,
                    COUNT(*) as total_registros,
                    COUNT(CASE WHEN ESTADO = 'CERRADO' THEN 1 END) as cerrados,
                    SUM(CASE WHEN ESTADO = 'CERRADO' THEN TOTAL_TROZAS ELSE 0 END) as total_trozas
                ")
                ->where('FECHA_INICIO', '>', now()->subDays($days))
                ->groupByRaw($dateFormat)
                ->orderByRaw($dateFormat)
                ->get();

            // Top usuarios por registros
            $topUsuarios = TrozaHead::select('USER_ID')
                                   ->selectRaw('COUNT(*) as total_registros')
                                   ->selectRaw('SUM(CASE WHEN ESTADO = "CERRADO" THEN TOTAL_TROZAS ELSE 0 END) as total_trozas')
                                   ->with('usuario:id,username,first_name,last_name')
                                   ->where('FECHA_INICIO', '>', now()->subDays($days))
                                   ->groupBy('USER_ID')
                                   ->orderByDesc('total_registros')
                                   ->limit(10)
                                   ->get();

            // Estadísticas por diámetro
            $estadisticasDiametro = TrozaDetail::selectRaw('
                    DIAMETRO_CM,
                    SUM(CANTIDAD_TROZAS) as total_trozas,
                    COUNT(DISTINCT ID_REGISTRO) as registros_count,
                    AVG(CANTIDAD_TROZAS) as promedio_por_banco
                ')
                ->whereHas('registro', function($query) use ($days) {
                    $query->where('FECHA_INICIO', '>', now()->subDays($days));
                })
                ->groupBy('DIAMETRO_CM')
                ->orderBy('DIAMETRO_CM')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => $stats,
                    'por_periodo' => $registrosPorPeriodo,
                    'top_usuarios' => $topUsuarios,
                    'por_diametro' => $estadisticasDiametro
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas por transportes
     */
    public function getTransportesStats(): JsonResponse
    {
        try {
            $stats = Transporte::select([
                    'ID_TRANSPORTE',
                    'NOMBRE_TRANSPORTES',
                    'RUT'
                ])
                ->selectRaw('(SELECT COUNT(*) FROM CHOFERES_PACK WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE AND VIGENCIA = 1) as choferes_count')
                ->selectRaw('(SELECT COUNT(*) FROM ABAS_Troza_HEAD WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE) as registros_count')
                ->selectRaw('(SELECT SUM(TOTAL_TROZAS) FROM ABAS_Troza_HEAD WHERE ID_TRANSPORTE = TRANSPORTES_PACK.ID_TRANSPORTE AND ESTADO = "CERRADO") as total_trozas')
                ->where('VIGENCIA', 1)
                ->orderBy('NOMBRE_TRANSPORTES')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de transportes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registros recientes para monitoreo
     */
    public function getRegistrosRecientes(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);

            $registros = TrozaHead::with(['usuario:id,username,first_name,last_name', 'chofer', 'transporte'])
                                  ->orderBy('FECHA_INICIO', 'desc')
                                  ->limit($limit)
                                  ->get()
                                  ->map(function($registro) {
                                      return [
                                          'id' => $registro->ID_REGISTRO,
                                          'patente' => $registro->PATENTE_CAMION,
                                          'estado' => $registro->ESTADO,
                                          'fecha_inicio' => $registro->FECHA_INICIO,
                                          'total_trozas' => $registro->TOTAL_TROZAS,
                                          'bancos_cerrados' => $registro->bancos_cerrados,
                                          'usuario' => $registro->usuario ? [
                                              'id' => $registro->usuario->id,
                                              'username' => $registro->usuario->username,
                                              'nombre' => trim($registro->usuario->first_name . ' ' . $registro->usuario->last_name)
                                          ] : null,
                                          'chofer' => $registro->chofer ? [
                                              'nombre' => $registro->chofer->NOMBRE_CHOFER
                                          ] : null,
                                          'transporte' => $registro->transporte ? [
                                              'nombre' => $registro->transporte->NOMBRE_TRANSPORTES
                                          ] : null
                                      ];
                                  });

            return response()->json([
                'success' => true,
                'data' => $registros
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros recientes: ' . $e->getMessage()
            ], 500);
        }
    }
}