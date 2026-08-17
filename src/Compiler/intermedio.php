<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado para la generación de código.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) {
    die("Error: El archivo no existe.\n");
}

$codigo = file_get_contents($archivo);
echo "--- GENERACIÓN DE CÓDIGO INTERMEDIO (3 Direcciones) ---\n\n";

$lineas = explode("\n", $codigo);
$contador_temp = 1;

foreach ($lineas as $num => $linea) {
    $linea = trim($linea);
    // Busca líneas de tipo: variable = operando1 operador operando2;
    if (preg_match('/^([\$a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([\$a-zA-Z0-9_]+)\s*([\+\-\*\/])\s*([\$a-zA-Z0-9_]+);?/', $linea, $match)) {
        
        $destino = $match[1];
        $arg1 = $match[2];
        $op = $match[3];
        $arg2 = $match[4];
        
        $temp = "t" . $contador_temp++;
        echo "; Traducción de la línea " . ($num+1) . "\n";
        echo "$temp = $arg1 $op $arg2\n";
        echo "$destino = $temp\n\n";
    } 
    // Busca retornos simples
    elseif (preg_match('/^return\s+([\$a-zA-Z0-9_]+);?/', $linea, $match)) {
        echo "; Traducción de la línea " . ($num+1) . "\n";
        echo "ret {$match[1]}\n\n";
    }
}

echo "Generación finalizada.\n";
?>
