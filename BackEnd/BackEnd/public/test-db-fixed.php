<?php
echo "<h2>Test de Credenciales SQL Server</h2>";

// Credenciales a probar (reemplaza con las reales)
$credentials = [
    ['usuario' => 'sa', 'password' => 'Deptoinfoeagon1'],
    ['usuario' => 'admin', 'password' => 'admin123'],
    ['usuario' => 'infoela_user', 'password' => 'infoela123'],
    // Agrega las credenciales que conozcas
];

$databases = [
    'Producto_Terminado',
    'Evaluacion'
];

foreach ($databases as $db) {
    echo "<h3>🗄️ Probando Base de Datos: {$db}</h3>";
    
    foreach ($credentials as $cred) {
        try {
            $pdo = new PDO(
                "sqlsrv:Server=192.1.1.16,1433;Database={$db};Encrypt=no;TrustServerCertificate=yes", 
                $cred['usuario'], 
                $cred['password']
            );
            
            echo "✅ <strong>ÉXITO:</strong> {$cred['usuario']} funciona en {$db}<br>";
            
            // Test específico por base de datos
            if ($db === 'Evaluacion') {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                $result = $stmt->fetch();
                echo "👥 Usuarios en Evaluacion: {$result['total']}<br>";
            }
            
            if ($db === 'Producto_Terminado') {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM TRANSPORTES_PACK");
                $result = $stmt->fetch();
                echo "🚛 Transportes: {$result['total']}<br>";
            }
            
            echo "<br>";
            break; // Si funciona, no probar más credenciales para esta BD
            
        } catch (PDOException $e) {
            echo "❌ {$cred['usuario']}: " . $e->getMessage() . "<br>";
        }
    }
    echo "<hr>";
}
?>