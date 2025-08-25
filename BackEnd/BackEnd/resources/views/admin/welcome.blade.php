<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Registro de Trozas - API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .api-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 50px;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .api-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary-gradient);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            font-size: 5rem;
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .main-title {
            color: #2c3e50;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .subtitle {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 30px;
        }

        .section-title {
            color: #34495e;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 1rem;
        }

        .section-title.api i {
            background: var(--primary-gradient);
            color: white;
        }

        .section-title.info i {
            background: var(--info-gradient);
            color: white;
        }

        .endpoint-list {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .endpoint-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .endpoint-item:last-child {
            border-bottom: none;
        }

        .endpoint-method {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
            margin-right: 15px;
        }

        .endpoint-method.post {
            background: #28a745;
        }

        .endpoint-url {
            font-family: 'Courier New', monospace;
            color: #495057;
            font-size: 0.9rem;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            flex-grow: 1;
            border: 1px solid #dee2e6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .info-item strong {
            color: #34495e;
            display: block;
            margin-bottom: 5px;
        }

        .action-buttons {
            text-align: center;
            margin: 40px 0;
        }

        .btn-custom {
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin: 0 10px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .btn-primary-custom {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .btn-secondary-custom {
            background: var(--info-gradient);
            color: white;
            border: none;
        }

        .btn-success-custom {
            background: var(--success-gradient);
            color: white;
            border: none;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }

        .feature-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            background: var(--success-gradient);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-badge i {
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .footer-info {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .api-card {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .main-title {
                font-size: 2rem;
            }
            
            .action-buttons .btn-custom {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="api-card">
                    <!-- Header Section -->
                    <div class="logo-section">
                        <div class="status-badge">
                            <i class="fas fa-circle"></i>
                            API Operativa
                        </div>
                        <i class="fas fa-tree logo-icon"></i>
                        <h1 class="main-title">Sistema de Registro de Trozas</h1>
                        <p class="subtitle">API RESTful para gestión de carga de camiones y registro de trozas de madera</p>
                    </div>

                    <!-- API Endpoints Section -->
                    <div class="row">
                        <div class="col-lg-6">
                            <h3 class="section-title api">
                                <i class="fas fa-code"></i>
                                Endpoints Principales
                            </h3>
                            <div class="endpoint-list">
                                <div class="endpoint-item">
                                    <span class="endpoint-method">GET</span>
                                    <code class="endpoint-url">{{ url('/api/v1/health') }}</code>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method">GET</span>
                                    <code class="endpoint-url">{{ url('/api/v1/test') }}</code>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method post">POST</span>
                                    <code class="endpoint-url">{{ url('/api/v1/auth/login') }}</code>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method">GET</span>
                                    <code class="endpoint-url">{{ url('/api/v1/trozas') }}</code>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method">GET</span>
                                    <code class="endpoint-url">{{ url('/api/v1/camiones/transportes') }}</code>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method">GET</span>
                                    <code class="endpoint-url">{{ url('/api/v1/version') }}</code>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <h3 class="section-title info">
                                <i class="fas fa-info-circle"></i>
                                Información del Sistema
                            </h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Versión API</strong>
                                    <span>{{ $version ?? '1.0.0' }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Laravel</strong>
                                    <span>{{ app()->version() }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>PHP</strong>
                                    <span>{{ PHP_VERSION }}</span>
                                </div>
                                <div class="info-item">
                                    <strong>Entorno</strong>
                                    <span class="badge bg-{{ app()->environment() === 'production' ? 'success' : 'warning' }}">
                                        {{ ucfirst(app()->environment()) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="{{ url('/api/v1/health') }}" class="btn-custom btn-primary-custom" target="_blank">
                            <i class="fas fa-heartbeat me-2"></i>
                            Test API Health
                        </a>
                        <a href="{{ url('/api/v1/test') }}" class="btn-custom btn-success-custom" target="_blank">
                            <i class="fas fa-vial me-2"></i>
                            Test Configuración
                        </a>
                        <a href="{{ url('/admin') }}" class="btn-custom btn-secondary-custom">
                            <i class="fas fa-user-shield me-2"></i>
                            Panel Admin
                        </a>
                    </div>

                    <!-- Features Grid -->
                    <div class="features-grid">
                        <div class="feature-card">
                            <i class="fas fa-mobile-alt feature-icon"></i>
                            <h5>App Móvil</h5>
                            <p class="text-muted small">Compatible con aplicación React Native para registro en terreno</p>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-database feature-icon"></i>
                            <h5>SQL Server</h5>
                            <p class="text-muted small">Conectado a base de datos empresarial con 99 usuarios y 66 transportes</p>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-sync feature-icon"></i>
                            <h5>Sync Offline</h5>
                            <p class="text-muted small">Sincronización automática de datos cuando hay conectividad</p>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-camera feature-icon"></i>
                            <h5>Fotos GPS</h5>
                            <p class="text-muted small">Captura automática de fotos con coordenadas de geolocalización</p>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-shield-alt feature-icon"></i>
                            <h5>Seguridad JWT</h5>
                            <p class="text-muted small">Autenticación segura con tokens JWT y control de permisos</p>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <h5>Reportes</h5>
                            <p class="text-muted small">Panel administrativo con estadísticas y reportes en tiempo real</p>
                        </div>
                    </div>

                    <!-- Authentication Example -->
                    <div class="row">
                        <div class="col-12">
                            <div class="endpoint-list">
                                <h5 class="mb-3"><i class="fas fa-key me-2"></i>Ejemplo de Autenticación</h5>
                                <div class="bg-dark text-light p-3 rounded">
                                    <code>
curl -X POST {{ url('/api/v1/auth/login') }} \<br>
&nbsp;&nbsp;-H "Content-Type: application/json" \<br>
&nbsp;&nbsp;-d '{"user":"cbravo","pass":"Eagon2024"}'
                                    </code>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Usa las credenciales del usuario <strong>cbravo</strong> para obtener un token JWT
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="footer-info">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="fas fa-clock"></i>
                                <strong>{{ now()->format('d/m/Y H:i:s') }}</strong><br>
                                <small>Hora del servidor</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-server"></i>
                                <strong>Laravel {{ app()->version() }}</strong><br>
                                <small>Framework utilizado</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-code-branch"></i>
                                <strong>API v{{ $version ?? '1.0.0' }}</strong><br>
                                <small>Versión actual</small>
                            </div>
                        </div>
                        <hr class="my-4">
                        <p class="mb-0">
                            <i class="fas fa-copyright me-1"></i>
                            {{ date('Y') }} Sistema de Registro de Trozas. 
                            Desarrollado para gestión eficiente de carga forestal.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh del estado cada 60 segundos
        setInterval(function() {
            fetch('{{ url("/api/v1/health") }}')
                .then(response => response.ok ? 
                    console.log('✅ API Health Check: OK') : 
                    console.log('❌ API Health Check: Error')
                )
                .catch(() => console.log('❌ API Health Check: No disponible'));
        }, 60000);

        // Copiar URL al hacer clic
        document.querySelectorAll('.endpoint-url').forEach(element => {
            element.addEventListener('click', function() {
                navigator.clipboard.writeText(this.textContent).then(() => {
                    // Mostrar feedback visual
                    this.style.background = '#d4edda';
                    setTimeout(() => {
                        this.style.background = 'white';
                    }, 1000);
                });
            });
        });
    </script>
</body>
</html>