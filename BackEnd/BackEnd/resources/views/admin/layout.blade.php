<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel Admin') - Sistema Trozas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.min.js"></script>
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: fixed;
            width: var(--sidebar-width);
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.4rem;
        }

        .sidebar-header small {
            opacity: 0.8;
            font-size: 0.85rem;
        }

        .sidebar nav {
            padding: 20px 0;
        }

        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent-color);
        }

        .sidebar a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: var(--accent-color);
        }

        .sidebar a i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 0;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .content-wrapper {
            padding: 0 30px 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), var(--success-color));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .stat-card.primary::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card.success::before { background: linear-gradient(90deg, #11998e, #38ef7d); }
        .stat-card.warning::before { background: linear-gradient(90deg, #f093fb, #f5576c); }
        .stat-card.info::before { background: linear-gradient(90deg, #4facfe, #00f2fe); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
        }

        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            margin: 5px 0 0 0;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
        }

        .card-header h5 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color), #5dade2);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .user-profile {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            background: rgba(255,255,255,0.1);
            margin: 20px 0 0;
            border-radius: 10px;
        }

        .user-profile .avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: 600;
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            font-size: 1.8rem;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 5px 0 0;
        }

        .breadcrumb-item {
            color: #6c757d;
        }

        .breadcrumb-item.active {
            color: var(--accent-color);
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-tree fa-2x mb-2"></i>
            <h4>Sistema Trozas</h4>
            <small>Panel Administrativo</small>
        </div>
        
        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="{{ route('admin.usuarios') }}" class="{{ request()->routeIs('admin.usuarios') ? 'active' : '' }}">
                <i class="fas fa-users"></i> Usuarios
            </a>
            <a href="{{ route('admin.grupos') }}" class="{{ request()->routeIs('admin.grupos') ? 'active' : '' }}">
                <i class="fas fa-user-tag"></i> Grupos
            </a>
            <a href="{{ route('admin.modulos') }}" class="{{ request()->routeIs('admin.modulos') ? 'active' : '' }}">
                <i class="fas fa-puzzle-piece"></i> Módulos
            </a>
            <a href="{{ route('admin.registros') }}" class="{{ request()->routeIs('admin.registros') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list"></i> Registros
            </a>
            <a href="{{ route('admin.estadisticas') }}" class="{{ request()->routeIs('admin.estadisticas') ? 'active' : '' }}">
                <i class="fas fa-chart-bar"></i> Estadísticas
            </a>
            <a href="{{ route('admin.sincronizacion') }}" class="{{ request()->routeIs('admin.sincronizacion') ? 'active' : '' }}">
                <i class="fas fa-sync"></i> Sincronización
            </a>
        </nav>
        
        <div class="user-profile">
            <div class="avatar">
                {{ substr(auth()->user()->username ?? 'A', 0, 1) }}
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ auth()->user()->username ?? 'Admin' }}</div>
                <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link text-white p-0 text-decoration-none small">
                        <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">@yield('page-title', 'Panel Administrativo')</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Inicio</a></li>
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-3">
                        <i class="fas fa-circle me-1"></i>En línea
                    </span>
                    <span class="text-muted">{{ now()->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Alerts -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡Atención!</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    {{ session('info') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ session('warning') }}
                </div>
            @endif

            <!-- Page Content -->
            @yield('content')
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-refresh script para datos en tiempo real -->
    <script>
        // Auto-refresh cada 60 segundos
        setInterval(function() {
            if (window.location.pathname.includes('dashboard')) {
                // Solo actualizar si estamos en el dashboard
                fetch('{{ route("admin.dashboard") }}')
                    .then(response => {
                        if (response.ok) {
                            console.log('Dashboard data refreshed');
                        }
                    })
                    .catch(error => console.log('Refresh error:', error));
            }
        }, 60000);

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
    </script>

    @stack('scripts')
</body>
</html>