<?php
if ($argc < 2) {
    die("Error: Archivo fuente no especificado para la tabla de símbolos.\n");
}

$archivo = $argv[1];
if (!file_exists($archivo)) die("Error: El archivo no existe o no se puede leer.\n");

$codigo = file_get_contents($archivo);

$simbolos = [];
$lineas = explode("\n", $codigo);

foreach ($lineas as $num => $linea) {
    $num_linea = $num + 1;

    // 1. Detección de Clases
    if (preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $linea, $matches)) {
        foreach($matches[1] as $clase) {
            registrarSimbolo($simbolos, $clase, 'Clase', $num_linea);
        }
    }

    // 2. Detección de Funciones (ej. int main(), void hacerAlgo())
    if (preg_match_all('/(?:function|int|float|void)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $linea, $matches)) {
        foreach($matches[1] as $func) {
            // Evitamos capturar el 'if' o 'while' si se escribieron pegados al paréntesis
            if (!in_array($func, ['if', 'while', 'switch', 'for'])) {
                registrarSimbolo($simbolos, $func, 'Función/Método', $num_linea);
            }
        }
    }

    // 3. Declaración de Variables separadas por comas (ej. int x, y, z;)
    if (preg_match_all('/(?:int|float|double|char|string)\s+([a-zA-Z_][a-zA-Z0-9_,\s]*)/', $linea, $matches)) {
        foreach($matches[1] as $bloque_vars) {
            $vars = explode(',', $bloque_vars);
            foreach($vars as $var) {
                // Limpiamos la variable de espacios u otros caracteres raros
                $var_limpia = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $var));
                if ($var_limpia !== '') {
                    registrarSimbolo($simbolos, $var_limpia, 'Variable', $num_linea);
                }
            }
        }
    }

    // 4. Asignaciones directas o de uso
    if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*(:=|=)/', $linea, $matches)) {
        foreach($matches[1] as $var) {
            if (!in_array($var, ['let', 'var', 'const', 'return', 'if', 'while', 'for', 'else', 'do'])) {
                registrarSimbolo($simbolos, $var, 'Asignación/Uso', $num_linea);
            }
        }
    }
}

function registrarSimbolo(&$tabla, $identificador, $tipo, $linea) {
    if (!isset($tabla[$identificador])) {
        $tabla[$identificador] = [
            'tipo' => $tipo,
            'lineas' => []
        ];
    }
    // Evita duplicar el número de línea si la variable aparece varias veces en la misma línea
    if (!in_array($linea, $tabla[$identificador]['lineas'])) {
        $tabla[$identificador]['lineas'][] = $linea;
    }
}

// ==========================================================================
// RENDERIZADO DE TABLA (JSON para Web UI)
// ==========================================================================
$output = [];
if (!empty($simbolos)) {
    ksort($simbolos); // Ordenamos alfabéticamente
    foreach ($simbolos as $id => $datos) {
        $output[] = [
            'identificador' => $id,
            'tipo' => $datos['tipo'],
            'lineas' => implode(', ', $datos['lineas'])
        ];
    }
}
header('Content-Type: application/json');
echo json_encode(['simbolos' => $output]);
?>