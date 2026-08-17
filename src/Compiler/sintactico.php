<?php
error_reporting(0);
if ($argc < 2) {
    echo json_encode(['success' => false, 'error' => 'Archivo fuente no especificado.']);
    exit;
}

$archivo = $argv[1];
if (!is_file($archivo)) {
    echo json_encode(['success' => false, 'error' => 'El archivo no existe.']);
    exit;
}

$jsonLexico = shell_exec(escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__DIR__ . '/lexico.php') . " " . escapeshellarg($archivo));
$datosLexico = json_decode($jsonLexico, true);

if (!$datosLexico || !isset($datosLexico['success'])) {
    echo json_encode(['success' => false, 'error' => 'Error al comunicarse con el analizador léxico.']);
    exit;
}

$tokensRaw = $datosLexico['tokens'] ?? [];
$tokens = [];
foreach ($tokensRaw as $t) {
    if ($t['tipo'] !== 'COM_MULTI' && $t['tipo'] !== 'COM_SIMPLE') {
        $tokens[] = $t;
    }
}

class Parser {
    private array $tokens;
    private int $pos = 0;
    private int $total;
    public array $errores = [];
    private bool $panicMode = false;

    public function __construct(array $tokens) {
        $this->tokens = $tokens;
        $this->total = count($tokens);
    }

    private function current() {
        return $this->pos < $this->total ? $this->tokens[$this->pos] : $this->tokens[$this->total - 1];
    }

    private function advance() {
        if ($this->pos < $this->total - 1) {
            $this->pos++;
        }
    }

    private function matchLexema(string $esperado) {
        $t = $this->current();
        if ($t['lexema'] === $esperado) {
            $this->advance();
            return true;
        }
        $this->error("Se esperaba '$esperado' pero se encontró '{$t['lexema']}'");
        return false;
    }

    private function error(string $msg) {
        if ($this->panicMode) return;
        $this->panicMode = true;
        $t = $this->current();
        $this->errores[] = [
            'linea' => $t['linea'] ?? 1,
            'col' => $t['col'] ?? 1,
            'len' => mb_strlen($t['lexema'] ?? '', 'UTF-8'),
            'msg' => $msg
        ];
    }

    private function synchronize() {
        $this->panicMode = false;
        while ($this->current()['tipo'] !== 'EOF') {
            if ($this->current()['lexema'] === ';') {
                $this->advance();
                return;
            }
            switch ($this->current()['lexema']) {
                case 'int': case 'float': case 'bool': case 'real':
                case 'if': case 'while': case 'do': case 'cin': case 'cout':
                case '}': case 'end': case 'else': case 'then':
                    return;
            }
            $this->advance();
        }
    }

    public function parse() {
        if ($this->total === 0) return [];
        $ast = $this->programa();
        if ($this->current()['tipo'] !== 'EOF') {
            $this->error("Código inesperado al final del archivo.");
        }
        return $ast;
    }

    private function programa() {
        $nodos = [];
        $tMain = $this->current();
        
        if (!$this->matchLexema('main')) {
            $this->error("El programa debe iniciar con 'main'");
            $this->synchronize();
        }
        
        if (!$this->matchLexema('{')) {
            while ($this->current()['tipo'] !== 'EOF') {
                if ($this->current()['lexema'] === '{' && ($this->current()['col'] ?? 1) === 1) {
                    break;
                }
                $this->advance();
            }
            if ($this->current()['lexema'] === '{') {
                $this->advance();
            } else {
                $this->error("Se esperaba '{' después de main");
            }
        }
        
        $nodos = $this->lista_declaracion();
        
        if ($this->current()['lexema'] === '}') {
            $this->advance();
        } else {
            $this->error("Se esperaba '}' al final del programa");
        }
        
        return [
            'name' => 'Programa Principal',
            'linea' => $tMain['linea'] ?? 1,
            'children' => $nodos
        ];
    }

    private function lista_declaracion() {
        $nodos = [];
        while ($this->current()['tipo'] !== 'EOF' && $this->current()['lexema'] !== '}') {
            $nodo = $this->declaracion();
            if ($nodo) {
                if (isset($nodo[0]) && is_array($nodo[0])) {
                    $nodos = array_merge($nodos, $nodo);
                } else {
                    $nodos[] = $nodo;
                }
            }
            if ($this->panicMode) {
                $this->synchronize();
            }
        }
        return $nodos;
    }

    private function declaracion() {
        $t = $this->current();
        if (in_array($t['lexema'], ['int', 'float', 'bool', 'real'])) {
            return $this->declaracion_variable();
        } else {
            return $this->sentencia();
        }
    }

    private function declaracion_variable() {
        $t = $this->current();
        $tipo = $this->tipo();
        $ids = $this->identificador();
        
        if ($tipo && $ids) {
            $nodos = [['name' => 'Tipo: ' . $tipo['valor'], 'linea' => $tipo['linea']]];
            foreach ($ids as $id) {
                $nodos[] = ['name' => 'ID: ' . $id['valor'], 'linea' => $id['linea']];
            }
            if (!$this->matchLexema(';')) $this->synchronize();
            return [
                'name' => 'Declaración Variable',
                'linea' => $t['linea'],
                'children' => $nodos
            ];
        }
        return null;
    }

    private function identificador() {
        $ids = [];
        $t = $this->current();
        if ($this->current()['tipo'] === 'ID') {
            $ids[] = ['valor' => $t['lexema'], 'linea' => $t['linea']];
            $this->advance();
            while ($this->current()['lexema'] === ',') {
                $this->advance();
                $t2 = $this->current();
                if ($this->current()['tipo'] === 'ID') {
                    $ids[] = ['valor' => $t2['lexema'], 'linea' => $t2['linea']];
                    $this->advance();
                } else {
                    $this->error("Se esperaba un identificador después de ','");
                    break;
                }
            }
        } else {
            $this->error("Se esperaba un identificador");
        }
        return $ids;
    }

    private function tipo() {
        $t = $this->current();
        if (in_array($t['lexema'], ['int', 'float', 'bool', 'real'])) {
            $this->advance();
            return ['valor' => $t['lexema'], 'linea' => $t['linea']];
        }
        $this->error("Se esperaba un tipo de dato");
        return null;
    }

    private function isDoWhileTerminator(): bool {
        if ($this->current()['lexema'] !== 'while') return false;
        $saved = $this->pos;
        $this->advance();
        $this->expresion();
        $next = $this->current()['lexema'];
        $this->pos = $saved;
        return $next !== '{';
    }

    private function lista_sentencias_do_cuerpo() {
        $nodos = [];
        while ($this->current()['tipo'] !== 'EOF' && $this->current()['lexema'] !== '}') {
            if ($this->isDoWhileTerminator()) break;
            $nodo = $this->sentencia();
            if ($nodo) {
                if (isset($nodo[0]) && is_array($nodo[0])) {
                    $nodos = array_merge($nodos, $nodo);
                } else {
                    $nodos[] = $nodo;
                }
            }
            if ($this->panicMode) {
                $this->synchronize();
            }
        }
        return $nodos;
    }

    private function lista_sentencias(array $extra_stops = []) {
        $nodos = [];
        $stops = array_merge(['end', 'else', '}'], $extra_stops);
        while ($this->current()['tipo'] !== 'EOF' && !in_array($this->current()['lexema'], $stops)) {
            $nodo = $this->sentencia();
            if ($nodo) {
                if (isset($nodo[0]) && is_array($nodo[0])) {
                    $nodos = array_merge($nodos, $nodo);
                } else {
                    $nodos[] = $nodo;
                }
            }
            if ($this->panicMode) {
                $this->synchronize();
            }
        }
        return $nodos;
    }

    private function sentencia() {
        $t = $this->current();
        if ($t['lexema'] === ';') {
            $this->advance();
            return null;
        }
        
        if ($t['lexema'] === '{') {
            $this->advance();
            $nodos = $this->lista_sentencias();
            if (!$this->matchLexema('}')) $this->synchronize();
            return ['name' => 'Llaves', 'linea' => $t['linea'], 'children' => $nodos];
        }

        switch ($t['lexema']) {
            case 'if': return $this->seleccion();
            case 'while': return $this->iteracion();
            case 'do': return $this->repeticion();
            case 'cin': return $this->sent_in();
            case 'cout': return $this->sent_in_out('cout');
            case 'int': case 'float': case 'bool': case 'real':
                $this->error("Declaración de variable no permitida en este ámbito");
                $this->panicMode = false;
                $this->declaracion_variable(); // Parse it so we consume it
                return null;
            default:
                if ($t['tipo'] === 'ID') {
                    return $this->asignacion();
                } elseif (in_array($t['lexema'], ['+', '-', '*', '/'])) {
                    $this->error("Expresión incompleta. Se encontró '{$t['lexema']}'");
                    $this->panicMode = false;
                    $this->advance();
                    if ($this->current()['lexema'] === ';') $this->advance();
                    return ['name' => 'Expresión incompleta: ' . $t['lexema'], 'linea' => $t['linea']];
                } else {
                    $this->error("Sentencia inválida. Se encontró '{$t['lexema']}'");
                    $this->advance();
                    return ['name' => 'Sentencia inválida: ' . $t['lexema'], 'linea' => $t['linea']];
                }
        }
    }

    private function asignacion() {
        $t = $this->current();
        $this->advance(); // Consume ID
        
        if ($this->current()['lexema'] === '++' || $this->current()['lexema'] === '--') {
            $op = $this->current()['lexema'];
            $this->advance(); // Consume ++ or --
            if (!$this->matchLexema(';')) $this->synchronize();
            return [
                'name' => ($op === '++') ? 'Incremento (++)' : 'Decremento (--)',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Variable: ' . $t['lexema'], 'linea' => $t['linea']]
                ]
            ];
        }

        if ($this->matchLexema('=')) {
            $exp = $this->sent_expresion();
            return [
                'name' => 'Asignación (=)',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Variable: ' . $t['lexema'], 'linea' => $t['linea']],
                    ['name' => 'Valor', 'linea' => $t['linea'], 'children' => $exp ? [$exp] : []]
                ]
            ];
        }

        if (in_array($this->current()['lexema'], ['+', '-', '*', '/'])) {
            $op = $this->current()['lexema'];
            $this->advance();
            $this->error("Expresión incompleta tras '{$t['lexema']}{$op}'");
            $this->panicMode = false;
            if ($this->current()['lexema'] === ';') $this->advance();
            return [
                'name' => 'Expresión incompleta: ' . $t['lexema'] . $op,
                'linea' => $t['linea'],
                'children' => [['name' => 'Variable: ' . $t['lexema'], 'linea' => $t['linea']]]
            ];
        }
        
        $this->error("Se esperaba '=' o '++' o '--' pero se encontró '{$this->current()['lexema']}'");
        $this->panicMode = false;
        $this->synchronize();
        return ['name' => 'Asignación inválida: ' . $t['lexema'], 'linea' => $t['linea']];
    }

    private function sent_expresion() {
        if ($this->current()['lexema'] === ';') {
            $this->advance();
            return null;
        }
        $exp = $this->expresion();
        if (!$this->matchLexema(';')) $this->synchronize();
        return $exp;
    }

    private function seleccion() {
        $t = $this->current();
        $this->advance(); // Consume if
        $exp = $this->expresion();
        $cuerpoThen = [];
        $cuerpoElse = [];
        
        if (!$this->matchLexema('then')) {
            $this->panicMode = false; // Recuperación inteligente
        }
        
        $cuerpoThen = $this->lista_sentencias(['else', 'end']);
        
        if ($this->current()['lexema'] === 'else') {
            $this->advance();
            $cuerpoElse = $this->lista_sentencias(['end']);
        }
        
        if (!$this->matchLexema('end')) {
            $this->synchronize();
        }

        $children = [
            ['name' => 'Condición', 'linea' => $t['linea'], 'children' => $exp ? [$exp] : []],
            ['name' => 'Bloque THEN', 'linea' => $t['linea'], 'children' => $cuerpoThen]
        ];
        if (!empty($cuerpoElse)) {
            $children[] = ['name' => 'Bloque ELSE', 'linea' => $t['linea'], 'children' => $cuerpoElse];
        }

        return [
            'name' => 'Sentencia IF',
            'linea' => $t['linea'],
            'children' => $children
        ];
    }

    private function iteracion() {
        $t = $this->current();
        $this->advance(); // Consume while
        $exp = $this->expresion();
        
        $cuerpo = [];
        $hasBrace = false;
        
        if ($this->current()['lexema'] === '{') {
            $this->advance();
            $hasBrace = true;
            $cuerpo = $this->lista_sentencias();
        } else {
            // Podría ser un while que usa 'end' u omitió la llave intencionalmente
            $cuerpo = $this->lista_sentencias(['end']);
        }

        if ($hasBrace) {
            if (!$this->matchLexema('}')) $this->synchronize();
        } else {
            if ($this->current()['lexema'] === 'end') {
                $this->advance();
            } else if ($this->current()['lexema'] === '}') {
                $this->advance(); // consumirlo por si alguien usó } sin {
            }
        }

        return [
            'name' => 'Bucle WHILE',
            'linea' => $t['linea'],
            'children' => [
                ['name' => 'Condición', 'linea' => $t['linea'], 'children' => $exp ? [$exp] : []],
                ['name' => 'Cuerpo', 'linea' => $t['linea'], 'children' => $cuerpo]
            ]
        ];
    }

    private function repeticion() {
        $t = $this->current();
        $this->advance(); // Consume do
        
        // #region agent log
        $logPath = dirname(dirname(__DIR__)) . '/.cursor/debug-814a75.log';
        file_put_contents($logPath, json_encode(['sessionId'=>'814a75','runId'=>'post-fix','hypothesisId'=>'A','location'=>'sintactico.php:repeticion','message'=>'do parsed, next token','data'=>['nextLexema'=>$this->current()['lexema'],'nextTipo'=>$this->current()['tipo'],'isTerminator'=>$this->isDoWhileTerminator()],'timestamp'=>round(microtime(true)*1000)])."\n", FILE_APPEND);
        // #endregion
        
        $cuerpo = [];
        $hasBrace = false;
        if ($this->current()['lexema'] === '{') {
            $this->advance();
            $hasBrace = true;
            $cuerpo = $this->lista_sentencias_do_cuerpo();
        } else {
            $cuerpo = $this->lista_sentencias_do_cuerpo();
        }

        if ($hasBrace) {
            if ($this->current()['lexema'] === '}') {
                $this->advance();
            } else {
                $this->error("Se esperaba '}' antes de while");
            }
        } else {
            if ($this->current()['lexema'] === '}') {
                $this->advance(); // consumir stray }
            }
        }

        $tWhile = $this->current();
        if ($this->matchLexema('while')) {
            $exp = $this->expresion();
            $lineaCondicion = $tWhile['linea'];
        } else {
            $exp = null;
            $lineaCondicion = $t['linea'];
            $this->error("Se esperaba 'while' al final del do");
            $this->synchronize();
        }

        return [
            'name' => 'Bucle DO-WHILE',
            'linea' => $t['linea'],
            'children' => [
                ['name' => 'Cuerpo', 'linea' => $t['linea'], 'children' => $cuerpo],
                ['name' => 'Condición', 'linea' => $lineaCondicion, 'children' => $exp ? [$exp] : []]
            ]
        ];
    }

    private function sent_in() {
        $t = $this->current();
        $this->advance(); // Consume cin
        $nodos = [];
        
        if ($this->current()['lexema'] === '>>') {
            $this->advance();
        } else {
            $this->error("Se esperaba '>>' despues de cin");
            $this->panicMode = false; // recover
        }

        $t2 = $this->current();
        if ($t2['tipo'] === 'ID') {
            $nodos[] = ['name' => 'Destino: ' . $t2['lexema'], 'linea' => $t2['linea']];
            $this->advance();
        } else {
            if (!$this->panicMode) {
                $this->error("Se esperaba un identificador");
            }
        }
        
        if (!$this->matchLexema(';')) $this->synchronize();
        
        return [
            'name' => 'Entrada (cin)',
            'linea' => $t['linea'],
            'children' => $nodos
        ];
    }

    private function sent_in_out($type) {
        $t = $this->current();
        $this->advance(); // Consume cout
        $nodos = [];
        if ($this->matchLexema('<<')) {
            $nodos[] = $this->salida();
            while ($this->current()['lexema'] === '<<') {
                $this->advance();
                $nodos[] = $this->salida();
            }
        } elseif ($this->current()['tipo'] === 'ID' || $this->current()['tipo'] === 'CADENA' || $this->current()['tipo'] === 'NUM_ENTERO' || $this->current()['tipo'] === 'NUM_REAL') {
            $this->error("Se esperaba '<<' despues de cout");
            $this->panicMode = false;
            $nodos[] = $this->salida();
        } else {
            $this->error("Se esperaba '<<' despues de cout");
            $this->synchronize();
        }
        if ($this->current()['lexema'] === ';') {
            $this->advance();
        }
        return [
            'name' => 'Salida (cout)',
            'linea' => $t['linea'],
            'children' => $nodos
        ];
    }

    private function salida() {
        $t = $this->current();
        if ($t['tipo'] === 'CADENA') {
            $this->advance();
            return ['name' => 'Cadena: ' . $t['lexema'], 'linea' => $t['linea']];
        } else {
            return $this->expresion();
        }
    }

    // ---------------- PRECEDENCIA Y ASOCIATIVIDAD ----------------

    private function expresion() {
        return $this->exp_or();
    }

    private function exp_or() {
        $nodo = $this->exp_and();
        while ($this->current()['lexema'] === '||') {
            $t = $this->current();
            $this->advance();
            $der = $this->exp_and();
            $nodo = [
                'name' => 'Op Lógico (||)',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Izq', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Der', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function exp_and() {
        $nodo = $this->exp_relacional();
        while ($this->current()['lexema'] === '&&') {
            $t = $this->current();
            $this->advance();
            $der = $this->exp_relacional();
            $nodo = [
                'name' => 'Op Lógico (&&)',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Izq', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Der', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function exp_relacional() {
        $nodo = $this->expresion_simple();
        if (in_array($this->current()['lexema'], ['<', '<=', '>', '>=', '==', '!='])) {
            $t = $this->current();
            $this->advance();
            $der = $this->expresion_simple();
            return [
                'name' => 'Operación Relacional (' . $t['lexema'] . ')',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Izq', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Der', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function expresion_simple() {
        $nodo = $this->termino();
        while (in_array($this->current()['lexema'], ['+', '-'])) {
            $t = $this->current();
            $this->advance();
            $der = $this->termino();
            $nodo = [
                'name' => 'Operación Aditiva (' . $t['lexema'] . ')',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Izq', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Der', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function termino() {
        $nodo = $this->factor();
        while (in_array($this->current()['lexema'], ['*', '/', '%'])) {
            $t = $this->current();
            $this->advance();
            $der = $this->factor();
            $nodo = [
                'name' => 'Operación Multiplicativa (' . $t['lexema'] . ')',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Izq', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Der', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function factor() {
        $nodo = $this->unaria();
        if ($this->current()['lexema'] === '^') {
            $t = $this->current();
            $this->advance();
            $der = $this->factor(); // Llamada recursiva para asociatividad derecha
            $nodo = [
                'name' => 'Potencia (^)',
                'linea' => $t['linea'],
                'children' => [
                    ['name' => 'Base', 'children' => $nodo ? [$nodo] : []],
                    ['name' => 'Exponente', 'children' => $der ? [$der] : []]
                ]
            ];
        }
        return $nodo;
    }

    private function unaria() {
        $t = $this->current();
        if (in_array($t['lexema'], ['!', '-', '++', '--'])) {
            $this->advance();
            $nodo = $this->unaria();
            return [
                'name' => 'Op Unaria (' . $t['lexema'] . ')',
                'linea' => $t['linea'],
                'children' => $nodo ? [$nodo] : []
            ];
        }
        return $this->componente();
    }

    private function componente() {
        $t = $this->current();
        
        if ($t['lexema'] === '(') {
            $this->advance();
            $exp = $this->expresion();
            if (!$this->matchLexema(')')) $this->synchronize();
            return [
                'name' => 'Agrupación ( )',
                'linea' => $t['linea'],
                'children' => $exp ? [$exp] : []
            ];
        } elseif ($t['tipo'] === 'NUM_ENTERO' || $t['tipo'] === 'NUM_REAL') {
            $this->advance();
            return ['name' => 'Número: ' . $t['lexema'], 'linea' => $t['linea']];
        } elseif ($t['tipo'] === 'ID') {
            $this->advance();
            return ['name' => 'Identificador: ' . $t['lexema'], 'linea' => $t['linea']];
        } elseif (in_array($t['lexema'], ['true', 'false'])) {
            $this->advance();
            return ['name' => 'Booleano: ' . $t['lexema'], 'linea' => $t['linea']];
        }
        
        $this->error("Se esperaba un componente válido. Se encontró '{$t['lexema']}'");
        return null;
    }
}

$parser = new Parser($tokens);
$arbol = $parser->parse();

// #region agent log
$logPath = dirname(dirname(__DIR__)) . '/.cursor/debug-814a75.log';
$countNodes = function($n) use (&$countNodes) {
    if (!$n || !is_array($n)) return 0;
    $c = 1;
    foreach ($n['children'] ?? [] as $ch) $c += $countNodes($ch);
    return $c;
};
$collectLines = function($n) use (&$collectLines) {
    $lines = isset($n['linea']) ? [$n['linea']] : [];
    foreach ($n['children'] ?? [] as $ch) $lines = array_merge($lines, $collectLines($ch));
    return $lines;
};
$astLines = array_values(array_unique($collectLines($arbol)));
sort($astLines);
file_put_contents($logPath, json_encode(['sessionId'=>'814a75','runId'=>'post-fix','hypothesisId'=>'A,B,D,E','location'=>'sintactico.php:parse-end','message'=>'AST parse result','data'=>['tokenCount'=>count($tokens),'nodeCount'=>$countNodes($arbol),'rootChildren'=>count($arbol['children']??[]),'success'=>empty($parser->errores),'errorCount'=>count($parser->errores),'astLines'=>$astLines,'errors'=>$parser->errores],'timestamp'=>round(microtime(true)*1000)])."\n", FILE_APPEND);
// #endregion

echo json_encode([
    'success' => empty($parser->errores),
    'tree' => $arbol,
    'errores' => $parser->errores
], JSON_UNESCAPED_UNICODE);
?>
