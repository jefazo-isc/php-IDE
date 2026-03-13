<?php
// ==========================================================================
// IDE INDÓMITO - PROCESAMIENTO BACKEND (CON EXPLORADOR DE ARCHIVOS)
// ==========================================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function logError($mensaje) {
    $archivo = __DIR__ . '/error_log.txt';
    $fecha = date('Y-m-d H:i:s');
    $log = "[$fecha] $mensaje\n";
    file_put_contents($archivo, $log, FILE_APPEND);
}

// ==========================================================================
// EXPLORAR DIRECTORIO (EL "PLUGIN" DE WORDPRESS)
// ==========================================================================
if ($accion === 'explorar_directorio') {
    header('Content-Type: application/json; charset=utf-8');
    $rutaBase = $_POST['ruta'] ?? __DIR__;
    $rutaBase = realpath($rutaBase);

    if (!$rutaBase || !is_dir($rutaBase)) {
        echo json_encode(['success' => false, 'error' => 'Ruta inválida o sin permisos de lectura.']);
        exit;
    }

    $elementos = [];
    $lista = scandir($rutaBase);
    foreach ($lista as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $rutaCompleta = $rutaBase . DIRECTORY_SEPARATOR . $item;
        $esDirectorio = is_dir($rutaCompleta);
        
        // Si no es directorio, validamos la extensión para no cargar binarios pesados
        if (!$esDirectorio) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $extensionesValidas = ['txt', 'c', 'cpp', 'php', 'js', 'html', 'css', 'json', 'md'];
            if (!in_array($ext, $extensionesValidas)) continue;
        }

        $elementos[] = [
            'nombre' => $item,
            'es_directorio' => $esDirectorio,
            'ruta' => $rutaCompleta
        ];
    }

    // Ordenar: Carpetas primero, luego archivos, ambos alfabéticamente
    usort($elementos, function($a, $b) {
        if ($a['es_directorio'] == $b['es_directorio']) {
            return strcasecmp($a['nombre'], $b['nombre']);
        }
        return $a['es_directorio'] ? -1 : 1;
    });

    echo json_encode([
        'success' => true,
        'ruta_actual' => $rutaBase,
        'elementos' => $elementos
    ]);
    exit;
}

// ==========================================================================
// GUARDADO DIRECTO A RUTA ABSOLUTA O RAIZ
// ==========================================================================
if ($accion === 'guardar_servidor') {
    header('Content-Type: text/plain; charset=utf-8');
    
    $nombreArchivo = $_POST['nombre_archivo'] ?? 'sin_nombre.txt';
    $rutaAbsoluta = trim($_POST['ruta_absoluta'] ?? '');
    $codigoFuente = $_POST['codigo_fuente'] ?? '';
    $isBase64 = $_POST['is_base64'] ?? '0';
    
    if ($isBase64 === '1') {
        $codigoFuente = base64_decode($codigoFuente);
    }
    
    // Si viene una ruta absoluta del explorador, escribimos ahí. Si no, en la raíz.
    if (!empty($rutaAbsoluta)) {
        $rutaArchivo = $rutaAbsoluta;
    } else {
        $nombreArchivo = basename($nombreArchivo);
        if (empty($nombreArchivo)) $nombreArchivo = 'archivo_' . time() . '.txt';
        $rutaArchivo = __DIR__ . DIRECTORY_SEPARATOR . $nombreArchivo;
    }
    
    $existeArchivo = file_exists($rutaArchivo);
    $bytes = file_put_contents($rutaArchivo, $codigoFuente);
    
    if ($bytes !== false) {
        $tamañoReal = filesize($rutaArchivo);
        $estado = $existeArchivo ? 'SOBRESCRITO' : 'CREADO';
        logError("GUARDADO $estado: $rutaArchivo ($tamañoReal bytes)");
        echo "SUCCESS|Archivo $estado: " . basename($rutaArchivo) . "|" . $tamañoReal . " bytes";
    } else {
        $error = error_get_last();
        logError("ERROR GUARDANDO EN $rutaArchivo : " . json_encode($error));
        http_response_code(500);
        echo "ERROR|No se pudo guardar. Revisa los permisos.";
    }
    exit;
}

// ==========================================================================
// CARGAR ARCHIVO FÍSICO DESDE EL EXPLORADOR
// ==========================================================================
if ($accion === 'cargar_archivo') {
    header('Content-Type: application/json; charset=utf-8');
    
    $rutaArchivo = $_GET['ruta_absoluta'] ?? '';
    if (empty($rutaArchivo)) {
        $rutaArchivo = __DIR__ . DIRECTORY_SEPARATOR . basename($_GET['archivo'] ?? '');
    }
    
    if (file_exists($rutaArchivo) && is_file($rutaArchivo)) {
        $contenido = file_get_contents($rutaArchivo);
        echo json_encode([
            'success' => true,
            'contenido' => base64_encode($contenido),
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
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    } else {
        echo "Log limpio. No hay errores registrados.";
    }
    exit;
}

// ==========================================================================
// ENRUTAMIENTO DE COMPILACIÓN
// ==========================================================================
if (in_array($accion, ['lexico', 'sintactico', 'semantico', 'intermedio', 'simbolos', 'ejecucion'])) {
    $codigoFuente = $_POST['codigo_fuente'] ?? '';
    if (($_POST['is_base64'] ?? '0') === '1') {
        $codigoFuente = base64_decode($codigoFuente);
    }
    file_put_contents(__DIR__ . '/_temp_code.tmp', $codigoFuente);
    
    $moduloMap = [
        'lexico' => 'compilador/lexico.php',
        'sintactico' => 'compilador/sintactico.php',
        'semantico' => 'compilador/semantico.php',
        'intermedio' => 'compilador/intermedio.php',
        'simbolos' => 'compilador/simbolos.php',
        'ejecucion' => 'compilador/ejecucion.php'
    ];
    
    if (file_exists($moduloMap[$accion])) {
        include $moduloMap[$accion];
    } else {
        echo "Error: Módulo del compilador no encontrado en la ruta correspondiente.";
    }
    exit;
}

// Carga la interfaz
include 'vistas/vista.php';
?>
