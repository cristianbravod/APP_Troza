@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row">
    <!-- Stats Cards -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="stat-number">{{ $stats['registros']['total'] ?? 0 }}</h2>
                    <p class="stat-label">Total Registros</p>
                    <small class="text-muted">
                        <i class="fas fa-calendar-day me-1"></i>
                        Hoy: {{ $stats['registros']['hoy'] ?? 0 }}
                    </small>
                </div>
                <i class="fas fa-clipboard-list stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="stat-number">{{ number_format($stats['trozas']['total'] ?? 0) }}</h2>
                    <p class="stat-label">Total Trozas</p>
                    <small class="text-muted">
                        <i class="fas fa-calendar-day me-1"></i>
                        Hoy: {{ number_format($stats['trozas']['hoy'] ?? 0) }}
                    </small>
                </div>
                <i class="fas fa-tree stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="stat-number">{{ $stats['usuarios']['activos_hoy'] ?? 0 }}</h2>
                    <p class="stat-label">Usuarios Activos</p>
                    <small class="text-muted">
                        <i class="fas fa-users me-1"></i>
                        Total: {{ $stats['usuarios']['total'] ?? 0 }}
                    </small>
                </div>
                <i class="fas fa-user-check stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="stat-number">{{ $stats['sync']['pendientes'] ?? 0 }}</h2>
                    <p class="stat-label">Sync Pendientes</p>
                    <small class="text-muted">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Errores 24h: {{ $stats['sync']['errores_24h'] ?? 0 }}
                    </small>
                </div>
                <i class="fas fa-sync-alt stat-icon"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de registros -->
    <div class="col-xl-8 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-chart-line me-2"></i>Registros por Día (Últimos 7 días)</h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm">7 días</button>
                    <button class="btn btn-outline-secondary btn-sm">30 días</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="registrosChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Usuarios más activos -->
    <div class="col-xl-4 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-trophy me-2"></i>Usuarios Más Activos</h5>
            </div>
            <div class="card-body">
                @if(isset($usuariosActivos) && $usuariosActivos->count() > 0)
                    @foreach($usuariosActivos as $usuario)
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-primary text-white rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    {{ substr($usuario->usuario->username ?? 'U', 0, 1) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $usuario->usuario->username ?? 'Usuario' }}</div>
                                    <small class="text-muted">{{ $usuario->usuario->first_name ?? '' }} {{ $usuario->usuario->last_name ?? '' }}</small>
                                </div>
                            </div>
                            <span class="badge bg-primary rounded-pill">{{ $usuario->total_registros ?? 0 }}</span>
                        </div>
                    @endforeach
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                        <p>No hay datos de usuarios activos</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Registros Recientes -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-clock me-2"></i>Registros Recientes</h5>
                <a href="{{ route('admin.registros') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i>Ver Todos
                </a>
            </div>
            <div class="card-body">
                @if(isset($registrosRecientes) && $registrosRecientes->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                    <th><i class="fas fa-truck me-1"></i>Patente</th>
                                    <th><i class="fas fa-user me-1"></i>Usuario</th>
                                    <th><i class="fas fa-user-tie me-1"></i>Chofer</th>
                                    <th><i class="fas fa-flag me-1"></i>Estado</th>
                                    <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                                    <th><i class="fas fa-tree me-1"></i>Trozas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrosRecientes as $registro)
                                    <tr>
                                        <td><span class="badge bg-light text-dark">#{{ $registro->ID_REGISTRO }}</span></td>
                                        <td><strong>{{ $registro->PATENTE_CAMION }}</strong></td>
                                        <td>{{ $registro->usuario->username ?? 'N/A' }}</td>
                                        <td>{{ $registro->chofer->NOMBRE_CHOFER ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $registro->ESTADO == 'CERRADO' ? 'success' : 'warning' }}">
                                                {{ $registro->ESTADO }}
                                            </span>
                                        </td>
                                        <td>{{ $registro->FECHA_INICIO ? $registro->FECHA_INICIO->format('d/m/Y H:i') : 'N/A' }}</td>
                                        <td>
                                            @if($registro->TOTAL_TROZAS)
                                                <span class="badge bg-info">{{ number_format($registro->TOTAL_TROZAS) }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                        <p>No hay registros recientes para mostrar</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt me-2"></i>Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('admin.usuarios') }}" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <span>Gestionar Usuarios</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('admin.registros') }}" class="btn btn-outline-success w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                            <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                            <span>Ver Registros</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('admin.estadisticas') }}" class="btn btn-outline-info w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                            <span>Estadísticas</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('admin.sincronizacion') }}" class="btn btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                            <i class="fas fa-sync fa-2x mb-2"></i>
                            <span>Sincronización</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Gráfico de registros por día
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('registrosChart');
    if (ctx) {
        // Datos por defecto si no hay datos del servidor
        const registrosData = @json($registrosPorDia ?? collect());
        
        // Preparar datos para Chart.js
        const labels = [];
        const data = [];
        
        if (registrosData && Object.keys(registrosData).length > 0) {
            Object.keys(registrosData).forEach(fecha => {
                labels.push(fecha);
                data.push(registrosData[fecha]);
            });
        } else {
            // Datos de ejemplo para los últimos 7 días
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toISOString().split('T')[0]);
                data.push(Math.floor(Math.random() * 10));
            }
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Registros',
                    data: data,
                    borderColor: 'rgb(52, 152, 219)',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgb(52, 152, 219)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });
    }
});

// Auto-refresh de estadísticas cada 30 segundos
setInterval(function() {
    // Solo actualizar números, no recargar toda la página
    updateDashboardStats();
}, 30000);

function updateDashboardStats() {
    // Simular actualización de estadísticas
    // En producción, harías una llamada AJAX al servidor
    console.log('Actualizando estadísticas del dashboard...');
}

// Animar números al cargar la página
function animateNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(element => {
        const target = parseInt(element.textContent.replace(/,/g, ''));
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 20);
    });
}

// Ejecutar animación al cargar
document.addEventListener('DOMContentLoaded', animateNumbers);
</script>
@endpush