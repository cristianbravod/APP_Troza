#!/bin/bash

# quick-test.sh - Comandos rápidos para testing sin Makefile

echo "=== COMANDOS RÁPIDOS PARA TESTING ==="
echo ""

# Función para ejecutar comandos
run_command() {
    echo "Ejecutando: $1"
    echo "----------------------------------------"
    eval $1
    local exit_code=$?
    echo "----------------------------------------"
    if [ $exit_code -eq 0 ]; then
        echo "✓ Comando exitoso"
    else
        echo "✗ Comando falló (código: $exit_code)"
    fi
    echo ""
    return $exit_code
}

# Verificar entorno
echo "Verificando entorno..."
if [ ! -f vendor/bin/phpunit ]; then
    echo "❌ PHPUnit no encontrado. Instalando dependencias..."
    composer install --dev
fi

echo "✅ Entorno verificado"
echo ""

# Menú de opciones
echo "Selecciona una opción:"
echo "1.  Ejecutar todos los tests"
echo "2.  Tests de autenticación únicamente"
echo "3.  Tests del módulo trucking"
echo "4.  Tests de workflow completo"
echo "5.  Tests unitarios"
echo "6.  Tests de características (feature)"
echo "7.  Tests con cobertura"
echo "8.  Verificar sintaxis PHP"
echo "9.  Test específico (CompleteWorkflowTest)"
echo "10. Configurar entorno de testing"
echo "11. Limpiar archivos de testing"
echo "12. Mostrar ayuda de PHPUnit"
echo "0.  Salir"
echo ""

read -p "Ingresa tu opción (0-12): " option

case $option in
    1)
        run_command "vendor/bin/phpunit --testdox"
        ;;
    2)
        run_command "vendor/bin/phpunit --filter='Auth' --testdox"
        ;;
    3)
        run_command "vendor/bin/phpunit --filter='Trucking' --testdox"
        ;;
    4)
        run_command "vendor/bin/phpunit tests/Feature/Api/CompleteWorkflowTest.php --testdox"
        ;;
    5)
        run_command "vendor/bin/phpunit --testsuite=Unit --testdox"
        ;;
    6)
        run_command "vendor/bin/phpunit --testsuite=Feature --testdox"
        ;;
    7)
        echo "Generando reporte de cobertura..."
        run_command "vendor/bin/phpunit --coverage-html coverage --coverage-text"
        echo "📊 Reporte disponible en: ./coverage/index.html"
        ;;
    8)
        echo "Verificando sintaxis PHP..."
        echo "Verificando app/..."
        find app -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
        echo "Verificando tests/..."
        find tests -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
        echo "✅ Verificación de sintaxis completada"
        ;;
    9)
        echo "Ejecutando CompleteWorkflowTest específicamente..."
        echo ""
        echo "Tests disponibles en CompleteWorkflowTest:"
        echo "- complete_trucking_workflow_from_login_to_completion"
        echo "- offline_sync_workflow_works_correctly"
        echo "- admin_workflow_manages_users_and_permissions"
        echo "- error_handling_and_validation_workflow"
        echo "- file_upload_and_storage_workflow"
        echo "- performance_and_bulk_operations_workflow"
        echo "- concurrent_access_and_data_consistency_workflow"
        echo ""
        
        read -p "¿Ejecutar todos los tests de CompleteWorkflowTest? (y/n): " execute_all
        
        if [[ $execute_all =~ ^[Yy]$ ]]; then
            run_command "vendor/bin/phpunit tests/Feature/Api/CompleteWorkflowTest.php --testdox"
        else
            echo ""
            echo "Opciones específicas:"
            echo "1. Solo workflow completo (login to completion)"
            echo "2. Solo workflow offline sync"
            echo "3. Solo workflow admin"
            echo "4. Solo workflow de errores"
            echo ""
            read -p "Selecciona (1-4): " specific_test
            
            case $specific_test in
                1)
                    run_command "vendor/bin/phpunit --filter='complete_trucking_workflow_from_login_to_completion' --testdox"
                    ;;
                2)
                    run_command "vendor/bin/phpunit --filter='offline_sync_workflow_works_correctly' --testdox"
                    ;;
                3)
                    run_command "vendor/bin/phpunit --filter='admin_workflow_manages_users_and_permissions' --testdox"
                    ;;
                4)
                    run_command "vendor/bin/phpunit --filter='error_handling_and_validation_workflow' --testdox"
                    ;;
                *)
                    echo "❌ Opción inválida"
                    ;;
            esac
        fi
        ;;
    10)
        echo "Configurando entorno de testing..."
        
        # Crear .env.testing
        if [ ! -f .env.testing ]; then
            cp .env.example .env.testing
            echo "✅ Archivo .env.testing creado"
        fi
        
        # Configurar para SQLite en memoria
        if ! grep -q "DB_CONNECTION=sqlite" .env.testing; then
            echo "DB_CONNECTION=sqlite" >> .env.testing
            echo "DB_DATABASE=:memory:" >> .env.testing
            echo "APP_ENV=testing" >> .env.testing
            echo "CACHE_DRIVER=array" >> .env.testing
            echo "SESSION_DRIVER=array" >> .env.testing
            echo "QUEUE_CONNECTION=sync" >> .env.testing
            echo "✅ Configuración de testing agregada"
        fi
        
        # Crear directorios
        mkdir -p storage/app/testing
        mkdir -p storage/framework/testing
        mkdir -p tests/Feature/Api
        mkdir -p tests/Unit
        echo "✅ Directorios creados"
        
        # Generar key
        php artisan key:generate --env=testing
        echo "✅ Application key generada"
        
        echo "🎉 Entorno de testing configurado exitosamente"
        ;;
    11)
        echo "Limpiando archivos de testing..."
        rm -rf storage/app/testing/*
        rm -rf storage/framework/testing/*
        rm -rf coverage/
        rm -f storage/logs/laravel-testing.log
        echo "✅ Archivos de testing limpiados"
        ;;
    12)
        echo "Ayuda de PHPUnit:"
        echo ""
        vendor/bin/phpunit --help
        ;;
    0)
        echo "👋 ¡Hasta luego!"
        exit 0
        ;;
    *)
        echo "❌ Opción inválida"
        exit 1
        ;;
esac

echo ""
echo "=== COMANDOS ÚTILES PARA DESARROLLO ==="
echo ""
echo "Comandos directos que puedes usar:"
echo ""
echo "📋 TESTS BÁSICOS:"
echo "vendor/bin/phpunit                                    # Todos los tests"
echo "vendor/bin/phpunit --testdox                         # Con formato legible"
echo "vendor/bin/phpunit --testsuite=Feature               # Solo feature tests"
echo "vendor/bin/phpunit --testsuite=Unit                  # Solo unit tests"
echo ""
echo "🎯 TESTS ESPECÍFICOS:"
echo "vendor/bin/phpunit --filter=AuthTest                 # Tests de auth"
echo "vendor/bin/phpunit --filter=TruckingTest             # Tests de trucking"
echo "vendor/bin/phpunit tests/Feature/Api/CompleteWorkflowTest.php  # Workflow completo"
echo ""
echo "🔍 DEBUGGING:"
echo "vendor/bin/phpunit --debug                           # Modo debug"
echo "vendor/bin/phpunit --verbose                         # Verbose output"
echo "vendor/bin/phpunit --stop-on-failure                 # Parar en primer error"
echo ""
echo "📊 COBERTURA:"
echo "vendor/bin/phpunit --coverage-text                   # Cobertura en terminal"
echo "vendor/bin/phpunit --coverage-html coverage          # Reporte HTML"
echo ""
echo "⚡ DESARROLLO:"
echo "./test-runner.sh setup                               # Configurar entorno"
echo "./test-runner.sh clean                               # Limpiar archivos"
echo "./quick-test.sh                                      # Este menú interactivo"