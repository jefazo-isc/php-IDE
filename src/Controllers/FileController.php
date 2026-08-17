<?php

namespace App\Controllers;

use App\Utils\Logger;

class FileController {

    private function getWorkspacePath(): string {
        $path = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'workspace';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return realpath($path);
    }

    private function validatePath(string $targetPath): ?string {
        $workspace = $this->getWorkspacePath();
        
        // Si la ruta no empieza con el workspace, la tratamos como relativa
        if (strpos($targetPath, $workspace) !== 0) {
            $targetPath = rtrim($workspace, '/\\') . DIRECTORY_SEPARATOR . ltrim($targetPath, '/\\');
        }
        
        // Si no existe el archivo, resolvemos su directorio padre para verificar
        $isNewFile = !file_exists($targetPath);
        $pathToCheck = $isNewFile ? dirname($targetPath) : $targetPath;
        
        $realTarget = realpath($pathToCheck);
        
        // Si el directorio tampoco existe o la ruta escapa del workspace
        if ($realTarget === false || strpos($realTarget, $workspace) !== 0) {
            return null; // Peligro de Path Traversal
        }
        
        // Devolver la ruta absoluta limpia y validada
        return $isNewFile ? $realTarget . DIRECTORY_SEPARATOR . basename($targetPath) : realpath($targetPath);
    }

    /**
     * Explorar directorio físico de forma segura.
     */
    public function explorarDirectorio(): void {
        header('Content-Type: application/json; charset=utf-8');
        
        $workspace = $this->getWorkspacePath();
        $requestedPath = $_POST['ruta'] ?? '';
        
        $rutaBase = $this->validatePath($requestedPath);

        if (!$rutaBase || !is_dir($rutaBase)) {
            echo json_encode(['success' => false, 'error' => 'Ruta inválida, prohibida o sin permisos de lectura.']);
            exit;
        }

        $elementos = [];

        foreach (scandir($rutaBase) as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $rutaCompleta = $rutaBase . DIRECTORY_SEPARATOR . $item;
            $esDirectorio = is_dir($rutaCompleta);

            $rutaRelativaItem = str_replace($workspace, '', $rutaCompleta);
            $rutaRelativaItem = str_replace('\\', '/', $rutaRelativaItem);

            $elementos[] = [
                'nombre' => $item,
                'es_directorio' => $esDirectorio,
                'ruta' => $rutaRelativaItem
            ];
        }

        // Ordenar directorios primero, luego archivos, ambos alfabéticamente
        usort($elementos, fn($a, $b) => $a['es_directorio'] === $b['es_directorio'] 
            ? strcasecmp($a['nombre'], $b['nombre']) 
            : ($a['es_directorio'] ? -1 : 1)
        );

        $rutaRelativaActual = str_replace($workspace, '', $rutaBase);
        $rutaRelativaActual = str_replace('\\', '/', $rutaRelativaActual);
        if ($rutaRelativaActual === '') $rutaRelativaActual = '/';

        echo json_encode(['success' => true, 'ruta_actual' => $rutaRelativaActual, 'elementos' => $elementos]);
        exit;
    }

    /**
     * Guardar archivo de forma segura.
     */
    public function guardarServidor(): void {
        header('Content-Type: text/plain; charset=utf-8');
        
        $nombreArchivo = basename($_POST['nombre_archivo'] ?? 'sin_nombre.txt');
        $rutaAbsoluta = trim($_POST['ruta_absoluta'] ?? '');
        $codigoFuente = $_POST['codigo_fuente'] ?? '';
        
        if (($_POST['is_base64'] ?? '0') === '1') {
            $codigoFuente = base64_decode($codigoFuente);
        }
        
        $workspace = $this->getWorkspacePath();
        // Si no envían ruta absoluta, guardarlo en la raíz del workspace
        $rutaTarget = $rutaAbsoluta ?: $workspace . DIRECTORY_SEPARATOR . ($nombreArchivo ?: 'archivo_' . time() . '.txt');
        
        $rutaArchivo = $this->validatePath($rutaTarget);
        
        if (!$rutaArchivo) {
            http_response_code(403);
            echo "ERROR|Acceso denegado: Intento de escritura fuera del Workspace seguro.";
            exit;
        }
        
        $existeArchivo = file_exists($rutaArchivo);
        $bytes = file_put_contents($rutaArchivo, $codigoFuente);
        
        if ($bytes !== false) {
            $estado = $existeArchivo ? 'SOBRESCRITO' : 'CREADO';
            Logger::log("GUARDADO $estado: $rutaArchivo ($bytes bytes)");
            echo "SUCCESS|Archivo $estado: " . basename($rutaArchivo) . "|$bytes bytes";
        } else {
            Logger::log("ERROR GUARDANDO EN $rutaArchivo : " . json_encode(error_get_last()));
            http_response_code(500);
            echo "ERROR|No se pudo guardar. Revisa los permisos.";
        }
        exit;
    }

    /**
     * Borrar un archivo físico de forma segura.
     */
    public function borrarArchivo(): void {
        header('Content-Type: text/plain; charset=utf-8');
        $rutaAbsoluta = trim($_POST['ruta_absoluta'] ?? '');
        
        if (!$rutaAbsoluta) {
            echo "ERROR|Archivo no válido.";
            exit;
        }
        
        $rutaSegura = $this->validatePath($rutaAbsoluta);
        
        if (!$rutaSegura || !is_file($rutaSegura)) {
            echo "ERROR|Archivo no válido, prohibido o es un directorio.";
            exit;
        }
        
        if (unlink($rutaSegura)) {
            Logger::log("BORRADO: $rutaSegura");
            echo "SUCCESS|Archivo eliminado";
        } else {
            http_response_code(500);
            echo "ERROR|Fallo al eliminar por permisos en el sistema.";
        }
        exit;
    }

    /**
     * Cargar archivo físico de forma segura.
     */
    public function cargarArchivo(): void {
        header('Content-Type: application/json; charset=utf-8');
        
        $workspace = $this->getWorkspacePath();
        $target = $_GET['ruta_absoluta'] ?? ($workspace . DIRECTORY_SEPARATOR . basename($_GET['archivo'] ?? ''));
        
        $rutaArchivo = $this->validatePath($target);
        
        if ($rutaArchivo && is_file($rutaArchivo)) {
            echo json_encode([
                'success' => true,
                'contenido' => base64_encode(file_get_contents($rutaArchivo)),
                'nombre' => basename($rutaArchivo),
                'ruta_absoluta' => $rutaArchivo
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado físicamente o acceso denegado.']);
        }
        exit;
    }

    /**
     * Ver el log de errores.
     */
    public function verLog(): void {
        // Se mantiene acceso al log raíz porque es interno del sistema
        header('Content-Type: text/plain; charset=utf-8');
        $logFile = dirname(dirname(__DIR__)) . '/error_log.txt';
        echo file_exists($logFile) ? file_get_contents($logFile) : "Log limpio. No hay errores registrados.";
        exit;
    }
}
