<?php
error_reporting(0); // Evitamos que warnings de PHP ensucien el output JSON

if ($argc < 2) {
    echo json_encode(['success' => false, 'error' => 'Archivo fuente no especificado.']);
    exit;
}

$archivo = $argv[1];
if (!is_file($archivo)) {
    echo json_encode(['success' => false, 'error' => 'El archivo no existe o no se puede leer.']);
    exit;
}

$codigo = file_get_contents($archivo);
$longitud = strlen($codigo);

// Se agregó \s* dentro de los operadores de múltiples caracteres para permitir que 
// se unan aunque estén separados por espacios o saltos de línea múltiples.
// NOTA: Se movió OP_LOGICO arriba de ID para evitar que palabras como 'and' sean tragadas por el identificador.
$patrones = [
    'COM_MULTI'     => '/^\/\*[\s\S]*?\*\//u',
    'COM_SIMPLE'    => '/^\/\/[^\n]*/u',
    'RESERVADA'     => '/^(if|else|end|do|while|switch|case|int|float|bool|main|cin|cout|real|then|until)\b/u',
    'OP_LOGICO'     => '/^(&\s*&|\|\s*\||!|and\b|or\b|not\b)/u',
    'ID'            => '/^[a-zA-Z_][a-zA-Z0-9_]*/u',
    'NUM_REAL'      => '/^[0-9]+\.[0-9]+/u',
    'ERR_NUM'       => '/^[0-9]+\.(?![0-9])/u',
    'NUM_ENTERO'    => '/^[0-9]+/u',
    'OP_RELACIONAL' => '/^(<\s*<|>\s*>|<\s*=|>\s*=|!\s*=|=\s*=|<|>)/u',
    'OP_ARITMETICO' => '/^(\+\s*\+|-\s*-|\+|-|\*|\/|%|\^)/u',
    'ASIGNACION'    => '/^=/u',
    'CADENA'        => '/^"[^"]*"/u',
    'CARACTER'      => '/^\'[^\']*\'/u',
    'SIMBOLO'       => '/^(\(|\)|\{|\}|,|;|"|\')/u'
];

$offset = 0;
$linea_actual = 1;
$col_actual = 1;

$tokens = [];
$errores = [];

while ($offset < $longitud) {
    $subcadena = substr($codigo, $offset);
    $matched = false;

    // Soporte robusto para espacios y saltos de línea puros
    if (preg_match('/^(\s+)/u', $subcadena, $coincidencia)) {
        $espacios = $coincidencia[1];
        $lineas_en_espacio = substr_count($espacios, "\n");
        
        if ($lineas_en_espacio > 0) {
            $linea_actual += $lineas_en_espacio;
            $ultimo_salto = strrpos($espacios, "\n");
            $texto_despues = substr($espacios, $ultimo_salto + 1);
            $col_actual = mb_strlen($texto_despues, 'UTF-8') + 1;
        } else {
            $col_actual += mb_strlen($espacios, 'UTF-8');
        }
        $offset += strlen($espacios);
        continue;
    }

    foreach ($patrones as $tipo => $regex) {
        if (preg_match($regex, $subcadena, $coincidencia)) {
            $lexema_original = mb_convert_encoding($coincidencia[0], 'UTF-8', 'auto');
            $lexema_mostrar = $lexema_original;
            
            // Limpiamos los saltos de línea internos solo para la interfaz, 
            // así "=\n\n=" se muestra como "==".
            if (in_array($tipo, ['OP_RELACIONAL', 'OP_LOGICO', 'OP_ARITMETICO'])) {
                $lexema_mostrar = preg_replace('/\s+/', '', $lexema_original);
            }

            if ($tipo === 'ERR_NUM') {
                $errores[] = [
                    'linea' => $linea_actual, 
                    'col' => $col_actual, 
                    'tipo' => 'ERROR_LÉXICO', 
                    'lexema' => $lexema_mostrar,
                    'msg' => 'Número malformado'
                ];
            } elseif ($tipo !== 'COM_MULTI' && $tipo !== 'COM_SIMPLE') {
                $tokens[] = [
                    'linea' => $linea_actual, 
                    'col' => $col_actual, 
                    'tipo' => $tipo, 
                    'lexema' => $lexema_mostrar
                ];
            }
            
            // El conteo lógico se hace sobre el lexema original con todo y sus saltos
            $lineas_en_lexema = substr_count($lexema_original, "\n");
            if ($lineas_en_lexema > 0) {
                $linea_actual += $lineas_en_lexema;
                $ultimo_salto_lex = strrpos($lexema_original, "\n");
                $texto_despues_lex = substr($lexema_original, $ultimo_salto_lex + 1);
                $col_actual = mb_strlen($texto_despues_lex, 'UTF-8') + 1;
            } else {
                $col_actual += mb_strlen($lexema_original, 'UTF-8'); 
            }
            
            $offset += strlen($lexema_original);
            $matched = true;
            break;
        }
    }

    if (!$matched && $offset < $longitud) {
        $charError = mb_substr(substr($codigo, $offset), 0, 1, 'UTF-8');
        $errores[] = [
            'linea' => $linea_actual, 
            'col' => $col_actual, 
            'tipo' => 'ERROR_LÉXICO', 
            'lexema' => $charError,
            'msg' => 'Carácter inválido no reconocido'
        ];
        $col_actual += mb_strlen($charError, 'UTF-8');
        $offset += strlen($charError);
    }
}

$tokens[] = ['linea' => $linea_actual, 'col' => $col_actual, 'tipo' => 'EOF', 'lexema' => 'EOF'];

echo json_encode([
    'success' => true,
    'tokens' => $tokens,
    'errores' => $errores
], JSON_UNESCAPED_UNICODE);
?>
