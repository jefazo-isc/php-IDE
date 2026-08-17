<?php
// ==========================================================================
// IDE INDÓMITO - PUNTO DE ENTRADA Y ENRUTADOR MODULAR
// ==========================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// --------------------------------------------------------------------------
// AUTOLOADER SIMPLE PSR-4
// --------------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Utils\DotEnv;
use App\Controllers\FileController;
use App\Controllers\CompilerController;
use App\Controllers\AIController;

// Cargar variables de entorno del archivo .env (¡El Agente no lo abre directamente!)
DotEnv::load(__DIR__ . '/.env');

$accion = $_REQUEST['accion'] ?? '';

// --------------------------------------------------------------------------
// ENRUTADOR DE ACCIONES
// --------------------------------------------------------------------------

// 1. Operaciones con Archivos
$fileController = new FileController();
if ($accion === 'explorar_directorio') {
    $fileController->explorarDirectorio();
}
if ($accion === 'guardar_servidor') {
    $fileController->guardarServidor();
}
if ($accion === 'borrar_archivo') {
    $fileController->borrarArchivo();
}
if ($accion === 'cargar_archivo') {
    $fileController->cargarArchivo();
}
if ($accion === 'ver_log') {
    $fileController->verLog();
}

// 2. Compilación y Autómata
$compilerController = new CompilerController();
if (in_array($accion, ['lexico', 'sintactico', 'semantico', 'intermedio', 'simbolos', 'ejecucion'])) {
    $compilerController->compilar($accion);
}
if ($accion === 'automata') {
    $compilerController->verAutomata();
}

// 3. Inteligencia Artificial
$aiController = new AIController();
$accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');

if ($accion === 'consultar_ia') {
    $aiController->procesar();
} elseif ($accion === 'auditar_gemini') {
    $aiController->auditarGemini();
}
if ($accion === 'obtener_proveedores_ia') {
    $aiController->obtenerProveedores();
}

// --------------------------------------------------------------------------
// CARGAR VISTA PRINCIPAL POR DEFECTO (Migración a Wasm/Client-Side)
// --------------------------------------------------------------------------
// Ya no llamamos a vistas/vista.php. Todo ha sido migrado a index.html
include 'index.html';
?>
