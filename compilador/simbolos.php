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
// RENDERIZADO DE TABLA ASCII (Diseño Hacker/Terminal)
// ==========================================================================
echo "╔══════════════════════════════╦════════════════════════╦══════════════════════════╗\n";
echo "║ IDENTIFICADOR                ║ TIPO / CONTEXTO        ║ LÍNEAS DE APARICIÓN      ║\n";
echo "╠══════════════════════════════╬════════════════════════╬══════════════════════════╣\n";

if (empty($simbolos)) {
    echo "║ " . str_pad("No se detectaron símbolos en el código fuente.", 80) . " ║\n";
} else {
    ksort($simbolos); // Ordenamos alfabéticamente
    foreach ($simbolos as $id => $datos) {
        // Unimos el array de líneas en un string separado por comas
        $lineas_str = implode(", ", $datos['lineas']);
        
        // Recortamos por si los nombres son inmensamente largos (evita romper la tabla)
        $id_recortado = mb_substr($id, 0, 28);
        $tipo_recortado = mb_substr($datos['tipo'], 0, 22);
        $lineas_recortado = mb_substr($lineas_str, 0, 24);
        
        // Pad para rellenar los espacios exactos de la columna
        $id_pad = str_pad($id_recortado, 28);
        $tipo_pad = str_pad($tipo_recortado, 22);
        $lineas_pad = str_pad($lineas_recortado, 24);
        
        echo "║ $id_pad ║ $tipo_pad ║ $lineas_pad ║\n";
    }
}
echo "╚══════════════════════════════╩════════════════════════╩══════════════════════════╝\n";
?>