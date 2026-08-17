<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado para el análisis semántico.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) {
    die("Error: El archivo no existe.\n");
}

$codigo = file_get_contents($archivo);
echo "--- ANÁLISIS SEMÁNTICO ---\n\n";

$declaradas = [];
$errores = 0;
$lineas = explode("\n", $codigo);

// Buscar declaraciones simples (ej. int x; let y = 5; $z = 1;)
foreach ($lineas as $num => $linea) {
    // Declaraciones estilo C/JS
    if (preg_match('/(?:int|float|string|let|var)\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $linea, $match)) {
        $declaradas[$match[1]] = "Tipo detectado";
        echo "Validación (Línea " . ($num+1) . "): Variable '{$match[1]}' declarada correctamente.\n";
    }
    // Declaraciones estilo PHP
    if (preg_match('/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=/', $linea, $match)) {
        $declaradas[$match[1]] = "Variable PHP";
        echo "Validación (Línea " . ($num+1) . "): Variable '{$match[1]}' inicializada en contexto.\n";
    }
}

echo "\n--- Verificación de Uso ---\n";
foreach ($lineas as $num => $linea) {
    // Si hay una asignación, revisar si la variable existe
    if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=/', trim($linea), $match)) {
        $var_name = $match[1];
        if (!array_key_exists($var_name, $declaradas) && !in_array($var_name, ['echo', 'return'])) {
            echo "Error Semántico (Línea " . ($num+1) . "): La variable '$var_name' está siendo usada pero no fue declarada.\n";
            $errores++;
        }
    }
}

if ($errores === 0) {
    echo "\nVerificación semántica completada con éxito. No hay inconsistencias de declaración.\n";
}
?>
