<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) die("Error de lectura.\n");

$codigo = file_get_contents($archivo);
echo "--- TABLA DE SÍMBOLOS ---\n\n";
echo str_pad("IDENTIFICADOR", 20) . " | " . str_pad("TIPO/CONTEXTO", 15) . " | LÍNEA\n";
echo str_repeat("-", 50) . "\n";

$simbolos = [];
$lineas = explode("\n", $codigo);

foreach ($lineas as $num => $linea) {
    // 1. Detecta declaraciones clásicas (C/C++/JS/Java)
    if (preg_match_all('/(?:int|float|string|let|var)\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $linea, $matches)) {
        foreach($matches[1] as $var) {
            if (!isset($simbolos[$var])) {
                $simbolos[$var] = true;
                echo str_pad($var, 20) . " | " . str_pad("Variable", 15) . " | " . ($num+1) . "\n";
            }
        }
    }
    
    // 2. Detecta variables del lenguaje TINY (identificador := expresion)
    if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*:=/', $linea, $matches)) {
        foreach($matches[1] as $var) {
            if (!isset($simbolos[$var])) {
                $simbolos[$var] = true;
                echo str_pad($var, 20) . " | " . str_pad("Variable TINY", 15) . " | " . ($num+1) . "\n";
            }
        }
    }

    // 3. Detecta variables estilo PHP ($variable)
    if (preg_match_all('/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $linea, $matches)) {
        foreach($matches[1] as $var) {
            if (!isset($simbolos[$var])) {
                $simbolos[$var] = true;
                echo str_pad($var, 20) . " | " . str_pad("Variable PHP", 15) . " | " . ($num+1) . "\n";
            }
        }
    }

    // 4. Detecta funciones genéricas
    if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $linea, $matches)) {
        foreach($matches[1] as $func) {
            if (!isset($simbolos[$func])) {
                $simbolos[$func] = true;
                echo str_pad($func, 20) . " | " . str_pad("Función", 15) . " | " . ($num+1) . "\n";
            }
        }
    }
}

if (empty($simbolos)) {
    echo "No se detectaron símbolos en el código fuente.\n";
}
?>
