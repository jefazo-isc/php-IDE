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

$patrones = [
    'COM_MULTI'     => '/^\/\*[\s\S]*?\*\//',
    'COM_SIMPLE'    => '/^\/\/[^\n]*/',
    'RESERVADA'     => '/^(if|else|end|do|while|switch|case|int|float|main|cin|cout)\b/',
    'ID'            => '/^[a-zA-Z_][a-zA-Z0-9_]*/',
    'NUM_REAL'      => '/^[0-9]+\.[0-9]+/',
    'ERR_NUM'       => '/^[0-9]+\.(?![0-9])/',
    'NUM_ENTERO'    => '/^[0-9]+/',
    'OP_RELACIONAL' => '/^(<=|>=|!=|==|<|>)/',
    'OP_LOGICO'     => '/^(&&|\|\||!)/',
    'OP_ARITMETICO' => '/^(\+\+|--|\+|-|\*|\/|%|\^)/',
    'ASIGNACION'    => '/^=/',
    'CADENA'        => '/^"[^"]*"/',
    'CARACTER'      => '/^\'[^\']*\'/',
    'SIMBOLO'       => '/^(\(|\)|\{|\}|,|;|"|\')/'
];

$offset = 0;
$linea_actual = 1;
$col_actual = 1;

$tokens = [];
$errores = [];

while ($offset < $longitud) {
    $subcadena = substr($codigo, $offset);
    $matched = false;

    if (preg_match('/^(\s+)/', $subcadena, $coincidencia)) {
        $espacios = $coincidencia[1];
        $lineas_en_espacio = substr_count($espacios, "\n");
        
        if ($lineas_en_espacio > 0) {
            $linea_actual += $lineas_en_espacio;
            $col_actual = strlen($espacios) - strrpos($espacios, "\n");
        } else {
            $col_actual += strlen($espacios);
        }
        $offset += strlen($espacios);
        continue;
    }

    foreach ($patrones as $tipo => $regex) {
        if (preg_match($regex, $subcadena, $coincidencia)) {
            $lexema = mb_convert_encoding($coincidencia[0], 'UTF-8', 'auto');
            
            if ($tipo === 'ERR_NUM') {
                $errores[] = [
                    'linea' => $linea_actual, 
                    'col' => $col_actual, 
                    'tipo' => 'ERROR_LÉXICO', 
                    'lexema' => $lexema,
                    'msg' => 'Número malformado'
                ];
            } elseif ($tipo !== 'COM_MULTI' && $tipo !== 'COM_SIMPLE') {
                $tokens[] = [
                    'linea' => $linea_actual, 
                    'col' => $col_actual, 
                    'tipo' => $tipo, 
                    'lexema' => $lexema
                ];
            }
            
            $lineas_en_lexema = substr_count($lexema, "\n");
            if ($lineas_en_lexema > 0) {
                $linea_actual += $lineas_en_lexema;
                $col_actual = strlen($lexema) - strrpos($lexema, "\n");
            } else {
                $col_actual += mb_strlen($lexema, 'UTF-8'); 
            }
            
            $offset += strlen($lexema);
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