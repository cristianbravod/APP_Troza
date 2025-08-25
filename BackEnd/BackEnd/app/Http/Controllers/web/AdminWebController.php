<?php
// app/Http/Controllers/Web/AdminWebController.php

namespace App\Http\Controllers\Web;

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminWebController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Procesar login del administrador
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'El usuario es requerido',
            'password.required' => 'La contraseña es requerida'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Buscar usuario
            $user = User::where(function($query) use ($request) {
                        $query->where('username', $request->username)
                              ->orWhere('email', $request->username);
                    })
                    ->where('User_Activo', true)
                    ->first();

            if (!$user) {
                return back()->withErrors([
                    'login' => 'Credenciales incorrectas o usuario inactivo'
                ])->withInput();
            }

            // Verificar contraseña
            $passwordValid = false;
            if ($user->salt) {
                // Sistema existente con salt
                $passwordValid = $user->password === sha1($user->salt . sha1($request->password));
            } else {
                // Hash de Laravel
                $passwordValid = Hash::check($request->password, $user->password);
            }

            if (!$passwordValid) {
                return back()->withErrors([
                    'login' => 'Credenciales incorrectas'
                ])->withInput();
            }

            // Verificar permisos de administrador
            $user->load('groups');
            $isAdmin = $user->groups()->whereIn('name', ['admin', 'administrador'])->exists() ||
                       $user->hasModuleAccess('ADMIN');

            if (!$isAdmin) {
                return back()->withErrors([
                    'login' => 'No tiene permisos para acceder al panel administrativo'
                ])->withInput();
            }

            // Login exitoso
            Auth::guard('web')->login($user, $request->filled('remember'));

            // Actualizar último login
            $user->update(['last_login' => time()]);

            return redirect()->intended(route('admin.dashboard'));

        } catch (\Exception $e) {
            return back()->withErrors([
                'login' => 'Error interno del servidor'
            ])->withInput();
        }
    }

    /**
     * Dashboard principal
     */
    public function dashboard()
    {
        try {
            // Estadísticas generales
            $stats = [
                'registros' => [
                    'total' => TrozaHead::count(),
                    'abiertos' => TrozaHead::where('ESTADO', 'ABIERTO')->count(),
                    'cerrados' => TrozaHead::where('ESTADO', 'CERRADO')->count(),
                    'hoy' => TrozaHead::whereDate('FECHA_INICIO', today())->count(),
                ],
                'usuarios' => [
                    'total' => User::where('User_Activo', true)->count(),
                    'con_acceso' => User::where('User_Activo', true)
                                       ->where('User_AppProd', true)
                                       ->count(),
                    'activos_hoy' => TrozaHead::whereDate('FECHA_INICIO', today())
                                             ->distinct('USER_ID')
                                             ->count('USER_ID'),
                ],
                'trozas' => [
                    'total' => TrozaHead::where('ESTADO', 'CERRADO')->sum('TOTAL_TROZAS'),
                    'hoy' => TrozaHead::whereDate('FECHA_INICIO', today())
                                     ->where('ESTADO', 'CERRADO')
                                     ->sum('TOTAL_TROZAS'),
                ],
                'sync' => [
                    'pendientes' => SyncLog::where('SYNC_STATUS', 'PENDING')->count(),
                    'errores_24h' => SyncLog::where('SYNC_STATUS', 'ERROR')
                                           ->where('CREATED_AT', '>', now()->subHours(24))
                                           ->count(),
                ]
            ];

            // Registros recientes
            $registrosRecientes = TrozaHead::with(['usuario', 'chofer', 'transporte'])
                                          ->orderBy('FECHA_INICIO', 'desc')
                                          ->limit(10)
                                          ->get();

            // Usuarios más activos (última semana)
            $usuariosActivos = TrozaHead::select('USER_ID')
                                       ->selectRaw('COUNT(*) as total_registros')
                                       ->with('usuario:id,username,first_name,last_name')
                                       ->where('FECHA_INICIO', '>', now()->subWeek())
                                       ->groupBy('USER_ID')
                                       ->orderByDesc('total_registros')
                                       ->limit(5)
                                       ->get();

            // Datos para gráficos (últimos 7 días)
            $registrosPorDia = TrozaHead::selectRaw('DATE(FECHA_INICIO) as fecha, COUNT(*) as total')
                                       ->where('FECHA_INICIO', '>', now()->subDays(7))
                                       ->groupBy('fecha')
                                       ->orderBy('fecha')
                                       ->get()
                                       ->pluck('total', 'fecha');

            return view('admin.dashboard', compact(
                'stats', 
                'registrosRecientes', 
                'usuariosActivos', 
                'registrosPorDia'
            ));

        } catch (\Exception $e) {
            return view('admin.dashboard')->withErrors([
                'error' => 'Error al cargar el dashboard: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Gestión de usuarios
     */
    public function usuarios(Request $request)
    {
        try {
            $search = $request->get('search');
            $grupo = $request->get('grupo');
            $activo = $request->get('activo');

            $query = User::with(['groups']);

            // Filtros
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            if ($grupo) {
                $query->whereHas('groups', function($q) use ($grupo) {
                    $q->where('groups.id', $grupo);
                });
            }

            if ($activo !== null) {
                $query->where('User_Activo', (bool)$activo);
            }

            $usuarios = $query->orderBy('username')->paginate(20);

            // Agregar estadísticas por usuario
            $usuarios->getCollection()->transform(function ($user) {
                $user->registros_count = $user->trozaRegistros()->count();
                $user->ultimo_registro = $user->trozaRegistros()
                                             ->orderBy('FECHA_INICIO', 'desc')
                                             ->first()?->FECHA_INICIO;
                return $user;
            });

            // Grupos para filtro
            $grupos = Group::active()->orderBy('name')->get();

            return view('admin.usuarios', compact('usuarios', 'grupos'));

        } catch (\Exception $e) {
            return view('admin.usuarios')->withErrors([
                'error' => 'Error al cargar usuarios: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Gestión de grupos
     */
    public function grupos()
    {
        try {
            $grupos = Group::with(['users', 'modules'])
                          ->withCount(['users', 'modules'])
                          ->orderBy('name')
                          ->get();

            $modulos = Module::active()->ordered()->get();

            return view('admin.grupos', compact('grupos', 'modulos'));

        } catch (\Exception $e) {
            return view('admin.grupos')->withErrors([
                'error' => 'Error al cargar grupos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Gestión de módulos
     */
    public function modulos()
    {
        try {
            $modulos = Module::with(['groups'])
                            ->withCount('groups')
                            ->ordered()
                            ->get();

            return view('admin.modulos', compact('modulos'));

        } catch (\Exception $e) {
            return view('admin.modulos')->withErrors([
                'error' => 'Error al cargar módulos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Gestión de registros
     */
    public function registros(Request $request)
    {
        try {
            $search = $request->get('search');
            $estado = $request->get('estado');
            $usuario = $request->get('usuario');
            $fecha_desde = $request->get('fecha_desde');
            $fecha_hasta = $request->get('fecha_hasta');

            $query = TrozaHead::with(['usuario', 'chofer', 'transporte']);

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

            if ($usuario) {
                $query->where('USER_ID', $usuario);
            }

            if ($fecha_desde) {
                $query->whereDate('FECHA_INICIO', '>=', $fecha_desde);
            }

            if ($fecha_hasta) {
                $query->whereDate('FECHA_INICIO', '<=', $fecha_hasta);
            }

            $registros = $query->orderBy('FECHA_INICIO', 'desc')->paginate(20);

            // Agregar información calculada
            $registros->getCollection()->transform(function ($registro) {
                $registro->bancos_cerrados_count = $registro->bancos_cerrados;
                $registro->total_trozas_calculado = $registro->total_trozas_calculado;
                return $registro;
            });

            // Usuarios para filtro
            $usuarios = User::where('User_Activo', true)
                           ->orderBy('username')
                           ->get(['id', 'username', 'first_name', 'last_name']);

            return view('admin.registros', compact('registros', 'usuarios'));

        } catch (\Exception $e) {
            return view('admin.registros')->withErrors([
                'error' => 'Error al cargar registros: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Estadísticas y reportes
     */
    public function estadisticas(Request $request)
    {
        try {
            $periodo = $request->get('periodo', 30); // días

            // Estadísticas generales
            $stats = [
                'registros' => [
                    'total' => TrozaHead::count(),
                    'periodo' => TrozaHead::where('FECHA_INICIO', '>', now()->subDays($periodo))->count(),
                    'cerrados' => TrozaHead::where('ESTADO', 'CERRADO')->count(),
                    'promedio_diario' => TrozaHead::where('FECHA_INICIO', '>', now()->subDays($periodo))->count() / $periodo,
                ],
                'trozas' => [
                    'total' => TrozaHead::where('ESTADO', 'CERRADO')->sum('TOTAL_TROZAS'),
                    'periodo' => TrozaHead::where('ESTADO', 'CERRADO')
                                         ->where('FECHA_INICIO', '>', now()->subDays($periodo))
                                         ->sum('TOTAL_TROZAS'),
                    'promedio_por_registro' => TrozaHead::where('ESTADO', 'CERRADO')
                                                       ->where('TOTAL_TROZAS', '>', 0)
                                                       ->avg('TOTAL_TROZAS'),
                ]
            ];

            // Registros por día (último mes)
            $registrosPorDia = TrozaHead::selectRaw('DATE(FECHA_INICIO) as fecha, COUNT(*) as total')
                                       ->where('FECHA_INICIO', '>', now()->subDays(30))
                                       ->groupBy('fecha')
                                       ->orderBy('fecha')
                                       ->get();

            // Top usuarios
            $topUsuarios = TrozaHead::select('USER_ID')
                                   ->selectRaw('COUNT(*) as total_registros')
                                   ->selectRaw('SUM(CASE WHEN ESTADO = "CERRADO" THEN TOTAL_TROZAS ELSE 0 END) as total_trozas')
                                   ->with('usuario:id,username,first_name,last_name')
                                   ->where('FECHA_INICIO', '>', now()->subDays($periodo))
                                   ->groupBy('USER_ID')
                                   ->orderByDesc('total_registros')
                                   ->limit(10)
                                   ->get();

            // Estadísticas por diámetro
            $estadisticasDiametro = TrozaDetail::selectRaw('
                    DIAMETRO_CM,
                    SUM(CANTIDAD_TROZAS) as total_trozas,
                    COUNT(DISTINCT ID_REGISTRO) as registros_count
                ')
                ->whereHas('registro', function($query) use ($periodo) {
                    $query->where('FECHA_INICIO', '>', now()->subDays($periodo));
                })
                ->groupBy('DIAMETRO_CM')
                ->orderBy('DIAMETRO_CM')
                ->get();

            // Top transportes
            $topTransportes = TrozaHead::select('ID_TRANSPORTE')
                                      ->selectRaw('COUNT(*) as total_registros')
                                      ->selectRaw('SUM(CASE WHEN ESTADO = "CERRADO" THEN TOTAL_TROZAS ELSE 0 END) as total_trozas')
                                      ->with('transporte:ID_TRANSPORTE,NOMBRE_TRANSPORTES')
                                      ->where('FECHA_INICIO', '>', now()->subDays($periodo))
                                      ->groupBy('ID_TRANSPORTE')
                                      ->orderByDesc('total_registros')
                                      ->limit(10)
                                      ->get();

            return view('admin.estadisticas', compact(
                'stats',
                'registrosPorDia',
                'topUsuarios',
                'estadisticasDiametro',
                'topTransportes',
                'periodo'
            ));

        } catch (\Exception $e) {
            return view('admin.estadisticas')->withErrors([
                'error' => 'Error al cargar estadísticas: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Estado de sincronización
     */
    public function sincronizacion(Request $request)
    {
        try {
            $dias = $request->get('dias', 7);

            // Estadísticas de sync
            $syncStats = [
                'pendientes' => SyncLog::where('SYNC_STATUS', 'PENDING')->count(),
                'exitosos_24h' => SyncLog::where('SYNC_STATUS', 'SUCCESS')
                                        ->where('CREATED_AT', '>', now()->subHours(24))
                                        ->count(),
                'errores_24h' => SyncLog::where('SYNC_STATUS', 'ERROR')
                                       ->where('CREATED_AT', '>', now()->subHours(24))
                                       ->count(),
                'total_periodo' => SyncLog::where('CREATED_AT', '>', now()->subDays($dias))->count(),
            ];

            // Logs recientes
            $logsRecientes = SyncLog::with('user:id,username')
                                   ->orderBy('CREATED_AT', 'desc')
                                   ->limit(50)
                                   ->get();

            // Sync por día
            $syncPorDia = SyncLog::selectRaw('
                    DATE(CREATED_AT) as fecha,
                    COUNT(*) as total,
                    COUNT(CASE WHEN SYNC_STATUS = "SUCCESS" THEN 1 END) as exitosos,
                    COUNT(CASE WHEN SYNC_STATUS = "ERROR" THEN 1 END) as errores
                ')
                ->where('CREATED_AT', '>', now()->subDays($dias))
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            // Sync por usuario
            $syncPorUsuario = SyncLog::select('USER_ID')
                                    ->selectRaw('
                                        COUNT(*) as total,
                                        COUNT(CASE WHEN SYNC_STATUS = "SUCCESS" THEN 1 END) as exitosos,
                                        COUNT(CASE WHEN SYNC_STATUS = "ERROR" THEN 1 END) as errores
                                    ')
                                    ->with('user:id,username')
                                    ->where('CREATED_AT', '>', now()->subDays($dias))
                                    ->groupBy('USER_ID')
                                    ->orderByDesc('total')
                                    ->limit(10)
                                    ->get();

            return view('admin.sincronizacion', compact(
                'syncStats',
                'logsRecientes',
                'syncPorDia',
                'syncPorUsuario',
                'dias'
            ));

        } catch (\Exception $e) {
            return view('admin.sincronizacion')->withErrors([
                'error' => 'Error al cargar datos de sincronización: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Logout del administrador
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        return redirect()->route('admin.login')->with('success', 'Sesión cerrada correctamente');
    }

    /**
     * API helper para obtener datos AJAX
     */
    public function ajaxDashboardData()
    {
        try {
            $data = [
                'registros_hoy' => TrozaHead::whereDate('FECHA_INICIO', today())->count(),
                'trozas_hoy' => TrozaHead::whereDate('FECHA_INICIO', today())
                                        ->where('ESTADO', 'CERRADO')
                                        ->sum('TOTAL_TROZAS'),
                'usuarios_activos' => TrozaHead::whereDate('FECHA_INICIO', today())
                                              ->distinct('USER_ID')
                                              ->count('USER_ID'),
                'sync_pendientes' => SyncLog::where('SYNC_STATUS', 'PENDING')->count(),
            ];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exportar datos (futuro)
     */
    public function exportar(Request $request)
    {
        // Placeholder para funcionalidad de exportación
        return back()->with('info', 'Funcionalidad de exportación en desarrollo');
    }
}