<?php
// ==========================================================================
// IDE INDÓMITO - PROCESAMIENTO BACKEND
// ==========================================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

$accion = $_REQUEST['accion'] ?? '';

function logError(string $mensaje): void {
    $archivo = __DIR__ . '/error_log.txt';
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($archivo, "[$fecha] $mensaje\n", FILE_APPEND);
}

// ==========================================================================
// EXPLORAR DIRECTORIO
// ==========================================================================
if ($accion === 'explorar_directorio') {
    header('Content-Type: application/json; charset=utf-8');
    $rutaBase = realpath($_POST['ruta'] ?? __DIR__);

    if (!$rutaBase || !is_dir($rutaBase)) {
        echo json_encode(['success' => false, 'error' => 'Ruta inválida o sin permisos de lectura.']);
        exit;
    }

    $elementos = [];
    $extensionesValidas = ['txt', 'c', 'cpp', 'php', 'js', 'html', 'css', 'json', 'md'];

    foreach (scandir($rutaBase) as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $rutaCompleta = $rutaBase . DIRECTORY_SEPARATOR . $item;
        $esDirectorio = is_dir($rutaCompleta);
        
        if (!$esDirectorio && !in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $extensionesValidas)) {
            continue;
        }

        $elementos[] = [
            'nombre' => $item,
            'es_directorio' => $esDirectorio,
            'ruta' => $rutaCompleta
        ];
    }

    usort($elementos, fn($a, $b) => $a['es_directorio'] === $b['es_directorio'] 
        ? strcasecmp($a['nombre'], $b['nombre']) 
        : ($a['es_directorio'] ? -1 : 1)
    );

    echo json_encode(['success' => true, 'ruta_actual' => $rutaBase, 'elementos' => $elementos]);
    exit;
}

// ==========================================================================
// GUARDADO DIRECTO A RUTA ABSOLUTA O RAIZ
// ==========================================================================
if ($accion === 'guardar_servidor') {
    header('Content-Type: text/plain; charset=utf-8');
    
    $nombreArchivo = basename($_POST['nombre_archivo'] ?? 'sin_nombre.txt');
    $rutaAbsoluta = trim($_POST['ruta_absoluta'] ?? '');
    $codigoFuente = $_POST['codigo_fuente'] ?? '';
    
    if (($_POST['is_base64'] ?? '0') === '1') {
        $codigoFuente = base64_decode($codigoFuente);
    }
    
    $rutaArchivo = $rutaAbsoluta ?: __DIR__ . DIRECTORY_SEPARATOR . ($nombreArchivo ?: 'archivo_' . time() . '.txt');
    
    $existeArchivo = file_exists($rutaArchivo);
    $bytes = file_put_contents($rutaArchivo, $codigoFuente);
    
    if ($bytes !== false) {
        $estado = $existeArchivo ? 'SOBRESCRITO' : 'CREADO';
        logError("GUARDADO $estado: $rutaArchivo ($bytes bytes)");
        echo "SUCCESS|Archivo $estado: " . basename($rutaArchivo) . "|$bytes bytes";
    } else {
        logError("ERROR GUARDANDO EN $rutaArchivo : " . json_encode(error_get_last()));
        http_response_code(500);
        echo "ERROR|No se pudo guardar. Revisa los permisos.";
    }
    exit;
}

// ==========================================================================
// BORRAR ARCHIVO FÍSICO
// ==========================================================================
if ($accion === 'borrar_archivo') {
    header('Content-Type: text/plain; charset=utf-8');
    $rutaAbsoluta = trim($_POST['ruta_absoluta'] ?? '');
    
    if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
        echo "ERROR|Archivo no válido o es un directorio.";
        exit;
    }
    
    if (unlink($rutaAbsoluta)) {
        logError("BORRADO: $rutaAbsoluta");
        echo "SUCCESS|Archivo eliminado";
    } else {
        http_response_code(500);
        echo "ERROR|Fallo al eliminar por permisos en el sistema.";
    }
    exit;
}

// ==========================================================================
// CARGAR ARCHIVO FÍSICO DESDE EL EXPLORADOR
// ==========================================================================
if ($accion === 'cargar_archivo') {
    header('Content-Type: application/json; charset=utf-8');
    
    $rutaArchivo = $_GET['ruta_absoluta'] ?? (__DIR__ . DIRECTORY_SEPARATOR . basename($_GET['archivo'] ?? ''));
    
    if (is_file($rutaArchivo)) {
        echo json_encode([
            'success' => true,
            'contenido' => base64_encode(file_get_contents($rutaArchivo)),
            'nombre' => basename($rutaArchivo),
            'ruta_absoluta' => $rutaArchivo
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado físicamente.']);
    }
    exit;
}

// ==========================================================================
// VER LOG DE ERRORES
// ==========================================================================
if ($accion === 'ver_log') {
    header('Content-Type: text/plain; charset=utf-8');
    $logFile = __DIR__ . '/error_log.txt';
    echo file_exists($logFile) ? file_get_contents($logFile) : "Log limpio. No hay errores registrados.";
    exit;
}

// ==========================================================================
// ENRUTAMIENTO DE COMPILACIÓN
// ==========================================================================
if (in_array($accion, ['lexico', 'sintactico', 'semantico', 'intermedio', 'simbolos', 'ejecucion'])) {
    $codigoFuente = ($_POST['is_base64'] ?? '0') === '1' ? base64_decode($_POST['codigo_fuente'] ?? '') : ($_POST['codigo_fuente'] ?? '');
    
    $tempFile = __DIR__ . '/_temp_code.tmp';
    file_put_contents($tempFile, $codigoFuente);
    
    $modulo = match ($accion) {
        'lexico' => 'compilador/lexico.php',
        'sintactico' => 'compilador/sintactico.php',
        'semantico' => 'compilador/semantico.php',
        'intermedio' => 'compilador/intermedio.php',
        'simbolos' => 'compilador/simbolos.php',
        'ejecucion' => 'compilador/ejecucion.php',
    };
    
    if (file_exists($modulo)) {
        echo shell_exec("php " . escapeshellarg($modulo) . " " . escapeshellarg($tempFile) . " 2>&1");
    } else {
        echo "Error: Módulo del compilador '$modulo' no encontrado.";
    }
    exit;
}

include 'vistas/vista.php';
?>
