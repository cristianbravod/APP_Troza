#!/bin/bash

# test-runner.sh - Script principal para ejecutar todos los tests

echo "=== TRUCKING APP - TEST RUNNER ==="
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo "Uso: ./test-runner.sh [OPCIÓN]"
    echo ""
    echo "Opciones:"
    echo "  all              Ejecutar todos los tests"
    echo "  unit             Ejecutar solo tests unitarios"
    echo "  feature          Ejecutar solo tests de características"
    echo "  auth             Ejecutar solo tests de autenticación"
    echo "  trucking         Ejecutar solo tests del módulo trucking"
    echo "  coverage         Ejecutar tests con reporte de cobertura"
    echo "  filter=PATTERN   Ejecutar tests que coincidan con el patrón"
    echo "  setup            Configurar entorno de testing"
    echo "  clean            Limpiar archivos de testing"
    echo "  watch            Ejecutar tests en modo watch"
    echo "  help             Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  ./test-runner.sh all"
    echo "  ./test-runner.sh filter=AuthTest"
    echo "  ./test-runner.sh trucking"
}

# Función para setup inicial
setup_testing() {
    echo -e "${BLUE}Configurando entorno de testing...${NC}"
    
    # Verificar que estamos en un proyecto Laravel
    if [ ! -f artisan ]; then
        echo -e "${RED}✗ No se encontró artisan. ¿Estás en la raíz del proyecto Laravel?${NC}"
        exit 1
    fi
    
    # Instalar dependencias si no existen
    if [ ! -d vendor ]; then
        echo "Instalando dependencias..."
        composer install --dev
    fi
    
    # Crear archivo de configuración de testing
    if [ ! -f .env.testing ]; then
        echo "Creando archivo .env.testing..."
        cp .env.example .env.testing
        
        # Configurar base de datos en memoria para testing
        sed -i.bak 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env.testing
        sed -i.bak 's/DB_DATABASE=.*/DB_DATABASE=:memory:/' .env.testing
        
        # Agregar configuraciones específicas de testing
        cat >> .env.testing << EOF

# Testing Configuration
APP_ENV=testing
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
BROADCAST_DRIVER=log
EOF
    fi
    
    # Crear directorios necesarios
    mkdir -p tests/Feature
    mkdir -p tests/Unit
    mkdir -p storage/app/testing
    mkdir -p storage/framework/testing
    mkdir -p storage/logs
    
    # Generar key para testing
    php artisan key:generate --env=testing
    
    echo -e "${GREEN}✓ Entorno de testing configurado${NC}"
}

# Función para limpiar archivos de testing
clean_testing() {
    echo -e "${YELLOW}Limpiando archivos de testing...${NC}"
    
    # Limpiar archivos temporales
    rm -rf storage/app/testing/*
    rm -rf storage/framework/testing/*
    rm -rf coverage/
    
    # Limpiar logs de testing
    rm -f storage/logs/laravel-testing.log
    
    echo -e "${GREEN}✓ Archivos de testing limpiados${NC}"
}

# Función principal para ejecutar tests
run_tests() {
    local test_type=$1
    local filter=$2
    
    echo -e "${BLUE}Preparando tests...${NC}"
    
    # Verificar que vendor existe
    if [ ! -d vendor ]; then
        echo -e "${RED}✗ Vendor directory no encontrado. Ejecuta 'composer install'${NC}"
        exit 1
    fi
    
    # Verificar que PHPUnit existe
    if [ ! -f vendor/bin/phpunit ]; then
        echo -e "${RED}✗ PHPUnit no encontrado. Ejecuta 'composer install --dev'${NC}"
        exit 1
    fi
    
    # Configurar entorno
    export APP_ENV=testing
    
    case $test_type in
        "all")
            echo -e "${BLUE}Ejecutando todos los tests...${NC}"
            vendor/bin/phpunit --testdox
            ;;
        "unit")
            echo -e "${BLUE}Ejecutando tests unitarios...${NC}"
            vendor/bin/phpunit --testsuite=Unit --testdox
            ;;
        "feature")
            echo -e "${BLUE}Ejecutando tests de características...${NC}"
            vendor/bin/phpunit --testsuite=Feature --testdox
            ;;
        "auth")
            echo -e "${BLUE}Ejecutando tests de autenticación...${NC}"
            vendor/bin/phpunit --filter="Auth" --testdox
            ;;
        "trucking")
            echo -e "${BLUE}Ejecutando tests del módulo trucking...${NC}"
            vendor/bin/phpunit --filter="Trucking" --testdox
            ;;
        "workflow")
            echo -e "${BLUE}Ejecutando tests de workflow completo...${NC}"
            vendor/bin/phpunit tests/Feature/Api/CompleteWorkflowTest.php --testdox
            ;;
        "coverage")
            echo -e "${BLUE}Ejecutando tests con cobertura...${NC}"
            vendor/bin/phpunit --coverage-html coverage --coverage-text
            echo -e "${GREEN}✓ Reporte de cobertura generado en ./coverage/${NC}"
            ;;
        "filter")
            echo -e "${BLUE}Ejecutando tests filtrados por: $filter${NC}"
            vendor/bin/phpunit --filter="$filter" --testdox
            ;;
        "watch")
            echo -e "${BLUE}Iniciando modo watch...${NC}"
            if [ -f vendor/bin/phpunit-watcher ]; then
                vendor/bin/phpunit-watcher watch
            else
                echo -e "${YELLOW}phpunit-watcher no instalado. Instalando...${NC}"
                composer require --dev spatie/phpunit-watcher
                vendor/bin/phpunit-watcher watch
            fi
            ;;
        *)
            echo -e "${RED}✗ Tipo de test no reconocido: $test_type${NC}"
            show_help
            exit 1
            ;;
    esac
}

# Función para verificar estado de tests
check_test_status() {
    local exit_code=$?
    
    echo ""
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ Todos los tests pasaron exitosamente${NC}"
    else
        echo -e "${RED}✗ Algunos tests fallaron (código de salida: $exit_code)${NC}"
        echo -e "${YELLOW}Consejos para debugging:${NC}"
        echo "- Verifica que .env.testing esté configurado correctamente"
        echo "- Ejecuta 'php artisan migrate --env=testing'"
        echo "- Revisa los logs en storage/logs/"
        echo "- Ejecuta con --debug para más información"
    fi
    
    return $exit_code
}

# Función para mostrar estadísticas de tests
show_test_stats() {
    echo -e "${BLUE}=== ESTADÍSTICAS DE TESTS ===${NC}"
    
    # Contar archivos de test
    local unit_tests=$(find tests/Unit -name "*Test.php" 2>/dev/null | wc -l)
    local feature_tests=$(find tests/Feature -name "*Test.php" 2>/dev/null | wc -l)
    local total_tests=$((unit_tests + feature_tests))
    
    echo "Tests Unitarios: $unit_tests"
    echo "Tests de Características: $feature_tests"
    echo "Total de Tests: $total_tests"
    echo ""
}

# Función para verificar sintaxis PHP
check_syntax() {
    echo -e "${BLUE}Verificando sintaxis PHP...${NC}"
    
    local error_count=0
    
    # Verificar archivos en app/
    for file in $(find app -name "*.php" 2>/dev/null); do
        if ! php -l "$file" > /dev/null 2>&1; then
            echo -e "${RED}✗ Error de sintaxis en: $file${NC}"
            php -l "$file"
            ((error_count++))
        fi
    done
    
    # Verificar archivos en tests/
    for file in $(find tests -name "*.php" 2>/dev/null); do
        if ! php -l "$file" > /dev/null 2>&1; then
            echo -e "${RED}✗ Error de sintaxis en: $file${NC}"
            php -l "$file"
            ((error_count++))
        fi
    done
    
    if [ $error_count -eq 0 ]; then
        echo -e "${GREEN}✓ No se encontraron errores de sintaxis${NC}"
    else
        echo -e "${RED}✗ Se encontraron $error_count errores de sintaxis${NC}"
        return 1
    fi
}

# Script principal
main() {
    local command=$1
    
    # Verificar si estamos en el directorio correcto
    if [ ! -f composer.json ]; then
        echo -e "${RED}✗ No se encontró composer.json. Ejecuta este script desde la raíz del proyecto Laravel.${NC}"
        exit 1
    fi
    
    case $command in
        "setup")
            setup_testing
            ;;
        "clean")
            clean_testing
            ;;
        "syntax")
            check_syntax
            ;;
        "stats")
            show_test_stats
            ;;
        "help"|"--help"|"-h"|"")
            show_help
            ;;
        filter=*)
            local filter_pattern=${command#filter=}
            run_tests "filter" "$filter_pattern"
            check_test_status
            ;;
        *)
            run_tests "$command"
            check_test_status
            ;;
    esac
}

# Ejecutar función principal con todos los argumentos
main "$@"