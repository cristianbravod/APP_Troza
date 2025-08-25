
@extends('admin.layout')

@section('title', 'Estadísticas')
@section('page-title', 'Estadísticas y Reportes')

@section('content')
<div class="row">
    <!-- Selector de período -->
    <div class="col-12 mb-3">
        <form method="GET" class="d-flex align-items-center gap-3">
            <label>Período:</label>
            <select name="periodo" class="form-select" style="width: auto;" onchange="this.form.submit()">
                <option value="7" {{ $periodo == 7 ? 'selected' : '' }}>Últimos 7 días</option>
                <option value="30" {{ $periodo == 30 ? 'selected' : '' }}>Últimos 30 días</option>
                <option value="90" {{ $periodo == 90 ? 'selected' : '' }}>Últimos 90 días</option>
                <option value="365" {{ $periodo == 365 ? 'selected' : '' }}>Último año</option>
            </select>
        </form>
    </div>
</div>

<div class="row">
    <!-- Stats Cards -->
    <div class="col-md-6">
        <div class="stat-card">
            <h4>{{ number_format($stats['registros']['total']) }}</h4>
            <p>Total Registros</p>
            <small>Período: {{ number_format($stats['registros']['periodo']) }} | 
                   Promedio diario: {{ number_format($stats['registros']['promedio_diario'], 1) }}</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card success">
            <h4>{{ number_format($stats['trozas']['total']) }}</h4>
            <p>Total Trozas</p>
            <small>Período: {{ number_format($stats['trozas']['periodo']) }} | 
                   Promedio por registro: {{ number_format($stats['trozas']['promedio_por_registro'], 1) }}</small>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico registros por día -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line"></i> Registros por Día</h5>
            </div>
            <div class="card-body">
                <canvas id="registrosChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Usuarios -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Top Usuarios</h5>
            </div>
            <div class="card-body">
                @forelse($topUsuarios as $usuario)
                    <div class="d-flex justify-content-between mb-2">
                        <div>
                            <strong>{{ $usuario->usuario->username }}</strong><br>
                            <small>{{ $usuario->total_trozas }} trozas</small>
                        </div>
                        <span class="badge bg-primary">{{ $usuario->total_registros }}</span>
                    </div>
                @empty
                    <p class="text-muted">No hay datos</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Estadísticas por diámetro -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie"></i> Distribución por Diámetro</h5>
            </div>
            <div class="card-body">
                <canvas id="diametrosChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Transportes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-truck"></i> Top Transportes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Registros</th>
                                <th>Trozas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topTransportes as $transporte)
                                <tr>
                                    <td>{{ $transporte->transporte->NOMBRE_TRANSPORTES }}</td>
                                    <td><span class="badge bg-primary">{{ $transporte->total_registros }}</span></td>
                                    <td>{{ number_format($transporte->total_trozas) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted">No hay datos</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Gráfico de registros por día
const registrosData = @json($registrosPorDia->pluck('total', 'fecha'));
const ctxRegistros = document.getElementById('registrosChart').getContext('2d');

new Chart(ctxRegistros, {
    type: 'line',
    data: {
        labels: Object.keys(registrosData),
        datasets: [{
            label: 'Registros',
            data: Object.values(registrosData),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de diámetros
const diametrosData = @json($estadisticasDiametro);
const ctxDiametros = document.getElementById('diametrosChart').getContext('2d');

new Chart(ctxDiametros, {
    type: 'doughnut',
    data: {
        labels: diametrosData.map(d => d.DIAMETRO_CM + ' cm'),
        datasets: [{
            data: diametrosData.map(d => d.total_trozas),
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
                '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
@endpush

{{-- resources/views/admin/sincronizacion.blade.php --}}
@extends('admin.layout')

@section('title', 'Sincronización')
@section('page-title', 'Estado de Sincronización')

@section('content')
<div class="row">
    <!-- Stats de Sync -->
    <div class="col-md-3">
        <div class="stat-card warning">
            <h3>{{ $syncStats['pendientes'] }}</h3>
            <p class="mb-0">Pendientes</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <h3>{{ $syncStats['exitosos_24h'] }}</h3>
            <p class="mb-0">Exitosos 24h</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h3>{{ $syncStats['errores_24h'] }}</h3>
            <p class="mb-0">Errores 24h</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <h3>{{ $syncStats['total_periodo'] }}</h3>
            <p class="mb-0">Total {{ $dias }} días</p>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de sync por día -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5><i class="fas fa-chart-bar"></i> Sincronización por Día</h5>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <select name="dias" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="7" {{ $dias == 7 ? 'selected' : '' }}>7 días</option>
                        <option value="30" {{ $dias == 30 ? 'selected' : '' }}>30 días</option>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <canvas id="syncChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Sync por usuario -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Por Usuario</h5>
            </div>
            <div class="card-body">
                @forelse($syncPorUsuario as $userSync)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $userSync->user->username }}</strong>
                            <span class="badge bg-primary">{{ $userSync->total }}</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: {{ ($userSync->exitosos / $userSync->total) * 100 }}%"></div>
                            <div class="progress-bar bg-danger" style="width: {{ ($userSync->errores / $userSync->total) * 100 }}%"></div>
                        </div>
                        <small class="text-muted">
                            {{ $userSync->exitosos }} exitosos, {{ $userSync->errores }} errores
                        </small>
                    </div>
                @empty
                    <p class="text-muted">No hay datos</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Logs recientes -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5><i class="fas fa-list"></i> Logs Recientes</h5>
                <button class="btn btn-sm btn-outline-danger" onclick="limpiarLogs()">
                    <i class="fas fa-trash"></i> Limpiar Logs Antiguos
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Entidad</th>
                                <th>Estado</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logsRecientes as $log)
                                <tr>
                                    <td>{{ $log->CREATED_AT->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ $log->user->username ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $log->SYNC_TYPE }}</span>
                                    </td>
                                    <td>{{ $log->ENTITY_TYPE }}</td>
                                    <td>
                                        <span class="badge bg-{{ $log->SYNC_STATUS == 'SUCCESS' ? 'success' : ($log->SYNC_STATUS == 'ERROR' ? 'danger' : 'warning') }}">
                                            {{ $log->SYNC_STATUS }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->ERROR_MESSAGE)
                                            <span class="text-danger" title="{{ $log->ERROR_MESSAGE }}">
                                                {{ Str::limit($log->ERROR_MESSAGE, 50) }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay logs recientes</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Gráfico de sync por día
const syncData = @json($syncPorDia);
const ctx = document.getElementById('syncChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: syncData.map(d => d.fecha),
        datasets: [
            {
                label: 'Exitosos',
                data: syncData.map(d => d.exitosos),
                backgroundColor: 'rgba(40, 167, 69, 0.8)'
            },
            {
                label: 'Errores',
                data: syncData.map(d => d.errores),
                backgroundColor: 'rgba(220, 53, 69, 0.8)'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { stacked: true },
            y: { 
                stacked: true,
                beginAtZero: true
            }
        }
    }
});

function limpiarLogs() {
    if (confirm('¿Estás seguro de limpiar los logs antiguos? Esta acción no se puede deshacer.')) {
        fetch('/api/v1/sync/cleanup', {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('admin_token'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Logs limpiados exitosamente');
                location.reload();
            } else {
                alert('Error al limpiar logs: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error de conexión');
        });
    }
}
</script>
@endpush

{{-- resources/views/welcome.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Registro de Trozas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .api-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="api-card text-center">
                    <i class="fas fa-tree fa-4x text-success mb-4"></i>
                    <h1 class="mb-4">Sistema de Registro de Trozas</h1>
                    <p class="lead mb-4">API RESTful para gestión de carga de camiones y registro de trozas</p>
                    
                    <div class="row text-start">
                        <div class="col-md-6">
                            <h5><i class="fas fa-code text-primary"></i> API Endpoints</h5>
                            <ul class="list-unstyled">
                                <li><code>GET {{ $api_url }}/health</code> - Health check</li>
                                <li><code>GET {{ $api_url }}/test</code> - Test configuración</li>
                                <li><code>POST {{ $api_url }}/auth/login</code> - Login</li>
                                <li><code>GET {{ $api_url }}/trozas</code> - Registros</li>
                                <li><code>GET {{ $api_url }}/version</code> - Información</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-cog text-info"></i> Información del Sistema</h5>
                            <ul class="list-unstyled">
                                <li><strong>Versión:</strong> {{ $version }}</li>
                                <li><strong>Laravel:</strong> {{ app()->version() }}</li>
                                <li><strong>PHP:</strong> {{ PHP_VERSION }}</li>
                                <li><strong>Entorno:</strong> {{ app()->environment() }}</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="{{ $api_url }}/test" class="btn btn-primary me-2">
                            <i class="fas fa-vial"></i> Test API
                        </a>
                        <a href="{{ $admin_url }}" class="btn btn-secondary">
                            <i class="fas fa-user-shield"></i> Panel Admin
                        </a>
                    </div>
                    
                    <div class="mt-4 text-muted">
                        <small>
                            <i class="fas fa-mobile-alt"></i> Compatible con aplicación móvil React Native<br>
                            <i class="fas fa-database"></i> Conectado a SQL Server<br>
                            <i class="fas fa-sync"></i> Sincronización offline disponible
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
