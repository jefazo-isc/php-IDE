<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado para el análisis sintáctico.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) {
    die("Error: El archivo no existe o no se puede leer.\n");
}

$codigo = file_get_contents($archivo);
echo "--- ANÁLISIS SINTÁCTICO ---\n\n";

$pila = [];
$pares = [')' => '(', '}' => '{', ']' => '['];
$lineas = explode("\n", $codigo);
$errores = 0;

foreach ($lineas as $num_linea => $linea) {
    $longitud = strlen($linea);
    for ($i = 0; $i < $longitud; $i++) {
        $char = $linea[$i];
        
        if (in_array($char, ['(', '{', '['])) {
            array_push($pila, ['char' => $char, 'linea' => $num_linea + 1]);
        } elseif (array_key_exists($char, $pares)) {
            if (empty($pila)) {
                echo "Error de sintaxis en línea " . ($num_linea + 1) . ": Se encontró '$char' sin apertura previa.\n";
                $errores++;
            } else {
                $ultimo = array_pop($pila);
                if ($ultimo['char'] !== $pares[$char]) {
                    echo "Error de sintaxis en línea " . ($num_linea + 1) . ": Se esperaba cierre para '{$ultimo['char']}' de la línea {$ultimo['linea']}, pero se encontró '$char'.\n";
                    $errores++;
                }
            }
        }
    }
}

if (!empty($pila)) {
    foreach ($pila as $elemento) {
        echo "Error de sintaxis: Estructura no cerrada. Falta cerrar '{$elemento['char']}' abierto en la línea {$elemento['linea']}.\n";
        $errores++;
    }
}

if ($errores === 0) {
    echo "Análisis Sintáctico Finalizado.\nEstructura de bloques y cierres validada correctamente. Cero errores.\n";
}
?>
