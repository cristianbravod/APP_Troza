# Crear archivo de prueba test-db.php en public/
<?php
require_once '../vendor/autoload.php';

try {
    $pdo = new PDO(
        "sqlsrv:Server=192.1.1.16,1433;Database=Producto_Terminado", 
        "sa", 
        "Deptoinfoeagon1"
    );
    echo "✅ Conexión exitosa a SQL Server\n";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch();
    echo "✅ Total usuarios: " . $result['total'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>