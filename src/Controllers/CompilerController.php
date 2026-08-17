<?php

namespace App\Controllers;

class CompilerController {
    /**
     * Compila o ejecuta el código fuente para una fase específica.
     * 
     * @param string $fase Fase de compilación ('lexico', 'sintactico', etc.)
     */
    public function compilar(string $fase): void {
        $codigoFuente = ($_POST['is_base64'] ?? '0') === '1' 
            ? base64_decode($_POST['codigo_fuente'] ?? '') 
            : ($_POST['codigo_fuente'] ?? '');
        
        $raizProyecto = dirname(dirname(__DIR__));
        $tempFile = $raizProyecto . '/_temp_code.tmp';
        file_put_contents($tempFile, $codigoFuente);
        
        $modulo = match ($fase) {
            'lexico' => $raizProyecto . '/src/Compiler/lexico.php',
            'sintactico' => $raizProyecto . '/src/Compiler/sintactico.php',
            'semantico' => $raizProyecto . '/src/Compiler/semantico.php',
            'intermedio' => $raizProyecto . '/src/Compiler/intermedio.php',
            'simbolos' => $raizProyecto . '/src/Compiler/simbolos.php',
            'ejecucion' => $raizProyecto . '/src/Compiler/ejecucion.php',
            default => null
        };
        
        if ($modulo && file_exists($modulo)) {
            echo shell_exec(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($modulo) . " " . escapeshellarg($tempFile) . " 2>&1");
        } else {
            echo "Error: Módulo del compilador '$fase' no encontrado.";
        }
        exit;
    }

    /**
     * Muestra la interfaz del autómata léxico.
     */
    public function verAutomata(): void {
        $raizProyecto = dirname(dirname(__DIR__));
        $archivoAutomata = $raizProyecto . '/src/Compiler/automata.php';

        if (file_exists($archivoAutomata)) {
            include $archivoAutomata;
        } else {
            http_response_code(404);
            echo "Error: El módulo visual del autómata no está disponible.";
        }
        exit;
    }
}
