<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado para el análisis léxico.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) {
    die("Error: El archivo no existe o no se puede leer.\n");
}

$codigo = file_get_contents($archivo);
echo "--- ANÁLISIS LÉXICO ---\n\n";

// Las expresiones regulares están ordenadas por prioridad para simular la jerarquía del autómata
$patrones = [
    'COM_MULTI'    => '/^\/\*[\s\S]*?\*\//',
    'COM_SIMPLE'   => '/^\/\/[^\n]*/',
    'STRING'       => '/^"[^"]*"/',
    'CHAR'         => '/^\'[^\']*\'/',
    'RESERVADA'    => '/^(if|else|while|for|return|int|float|void|class|public|private|function|echo|let|var|const)\b/',
    'ID'           => '/^(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|[a-zA-Z_áéíóúÁÉÍÓÚñÑ][a-zA-Z0-9_áéíóúÁÉÍÓÚñÑ]*)/',
    'NUM_REAL'     => '/^[0-9]+\.[0-9]+/',
    'NUMERO'       => '/^[0-9]+/',
    'OP_RELACIONAL'=> '/^(==|!=|<=|>=|<|>)/',
    'OP_LOGICO'    => '/^(&&|\|\||!)/',
    'ASIGNACION'   => '/^=/',
    'OP_ARITMETICO'=> '/^(\+|-|\*|\/|%)/',
    'SIMBOLO'      => '/^(\(|\)|\{|\}|\[|\]|;|:|,)/',
    'PUNTUACION'   => '/^(\.|\?|\\\\|\$|`)/',
    'CARACTER_ESP' => '/^[\x80-\xFF]+/'
];

$lineas = explode("\n", str_replace("\r", "", $codigo));

foreach ($lineas as $num_linea => $linea) {
    $linea = trim($linea);
    $offset = 0;
    $longitud = strlen($linea);

    while ($offset < $longitud) {
        $subcadena = substr($linea, $offset);
        $matched = false;

        // Simula la transición "espacio en blanco: ignorar"
        if (preg_match('/^\s+/', $subcadena, $coincidencia)) {
            $offset += strlen($coincidencia[0]);
            continue;
        }

        foreach ($patrones as $tipo => $regex) {
            if (preg_match($regex, $subcadena, $coincidencia)) {
                $lexema = $coincidencia[0];
                echo "Línea " . ($num_linea + 1) . " | Token: " . str_pad($tipo, 15) . " | Lexema: $lexema\n";
                $offset += strlen($lexema);
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            echo "Línea " . ($num_linea + 1) . " | ERROR LÉXICO | Carácter no reconocido: " . mb_substr($subcadena, 0, 1, 'UTF-8') . "\n";
            $offset++;
        }
    }
}
?>
