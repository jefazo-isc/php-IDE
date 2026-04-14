<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autómata Léxico - Navegación con Lupa Inteligente</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        :root {
            --bg-primary: #ffffff;
            --text-primary: #333;
            --accent-primary: #0056b3;
            --font-sans: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        
        body {
            font-family: var(--font-sans);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        #automata-network {
            flex: 1;
            height: 100%;
            background: radial-gradient(circle at center, #f8f9fa 0%, #e9ecef 100%);
        }

        /* --- ESTILOS PARA LA LEYENDA --- */
        .leyenda-container {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.98);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            z-index: 10;
            font-size: 13px;
            min-width: 200px;
        }
        .leyenda-container h3 { margin: 0 0 15px 0; font-size: 16px; color: var(--accent-primary); border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .leyenda-item { display: flex; align-items: center; margin-bottom: 10px; }
        .color-box { width: 20px; height: 20px; border-radius: 5px; margin-right: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.1); }

        /* --- ESTILOS PARA LA LUPA (MINI AUTÓMATA) --- */
        #tooltip-container {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 380px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 2px solid var(--accent-primary);
            z-index: 100;
            display: none; 
            flex-direction: column;
            overflow: hidden;
            pointer-events: none; /* Evita que la lupa interfiera con el mouse */
        }

        .tooltip-header {
            background-color: var(--accent-primary);
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
        }

        #tooltip-network {
            flex: 1;
            width: 100%;
            height: 220px; 
            background: #fafafa;
        }

        .tooltip-info {
            padding: 12px 15px;
            font-size: 13px;
            border-top: 1px solid #eee;
            background: white;
            text-align: center;
        }
        
        .tooltip-info strong { color: var(--accent-primary); }
        .tooltip-arrow { color: #888; margin: 0 5px; font-weight: bold; }

        /* --- CONTROLES --- */
        .controls {
            position: absolute;
            bottom: 30px;
            right: 30px;
            z-index: 10;
            display: flex;
            gap: 10px;
        }
        .btn {
            background-color: var(--accent-primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,86,179,0.3);
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-3px); background-color: #004494; }
    </style>
</head>
<body>

    <div class="leyenda-container">
        <h3>Leyenda de Categorías</h3>
        <div class="leyenda-item"><span class="color-box" style="background:#eceff1;"></span>Control y Flujo</div>
        <div class="leyenda-item"><span class="color-box" style="background:#e3f2fd;"></span>Identificadores</div>
        <div class="leyenda-item"><span class="color-box" style="background:#fff3e0;"></span>Constantes Numéricas</div>
        <div class="leyenda-item"><span class="color-box" style="background:#fbe9e7;"></span>Operadores</div>
        <div class="leyenda-item"><span class="color-box" style="background:#f3e5f5;"></span>Cadenas y Caracteres</div>
        <div class="leyenda-item"><span class="color-box" style="background:#e8f5e9;"></span>Comentarios</div>
        <div class="leyenda-item"><span class="color-box" style="background:#e0f2f1;"></span>Símbolos de Puntuación</div>
        <div class="leyenda-item"><span class="color-box" style="background:#ffebee;"></span>Errores Léxicos</div>
        <div class="leyenda-item"><span class="color-box" style="background:#c8e6c9; border: 2px solid #2e7d32;"></span>Estado de Aceptación</div>
    </div>

    <div id="tooltip-container">
        <div class="tooltip-header" id="tooltip-title">🔍 Detalle de Elemento</div>
        <div id="tooltip-network"></div>
        <div class="tooltip-info" id="info-text">
            Pasa el cursor sobre un nodo o transición.
        </div>
    </div>

    <div id="automata-network"></div>
    
    <div class="controls">
        <button class="btn" onclick="network.fit({ animation: {duration: 800} })">✨ Reajustar Vista</button>
    </div>

    <script type="text/javascript">
        const colors = {
            control: { background: '#eceff1', border: '#90a4ae' },
            id:      { background: '#e3f2fd', border: '#42a5f5' },
            num:      { background: '#fff3e0', border: '#fb8c00' },
            op:       { background: '#fbe9e7', border: '#ff7043' },
            text:     { background: '#f3e5f5', border: '#ab47bc' },
            comment: { background: '#e8f5e9', border: '#4caf50' },
            sym:      { background: '#e0f2f1', border: '#26a69a' },
            error:    { background: '#ffebee', border: '#ef5350' },
            done:     { background: '#c8e6c9', border: '#2e7d32' }
        };

        // --- DATOS DEL AUTÓMATA PRINCIPAL ---
        var nodesArray = [
            { id: 'INICIO', label: 'ESTADO INICIAL', shape: 'circle', color: colors.control, x: -7000, y: 500, font: {size: 30, bold: true}, size: 60 },
            { id: 'HECHO', label: 'ESTADO DE ACEPTACIÓN\n(Token Finalizado)', shape: 'doubleCircle', color: colors.done, x: 2000, y: 500, font: { size: 30, bold: true }, borderWidth: 5, size: 80 },
            
            { id: 'CUERPO_CADENA', label: 'Texto de Cadena', shape: 'ellipse', color: colors.text, x: -2000, y: -2800 },
            { id: 'CUERPO_CARACTER', label: 'Carácter Interno', shape: 'ellipse', color: colors.text, x: -1500, y: -2300 },
            { id: 'IDENTIFICADOR', label: 'Identificador Principal', shape: 'ellipse', color: colors.id, x: -1000, y: -1800 },
            { id: 'NUMERO_ENTERO', label: 'Número Entero', shape: 'ellipse', color: colors.num, x: -600, y: -1300 },
            { id: 'NUMERO_FLOTANTE', label: 'Número Flotante', shape: 'ellipse', color: colors.num, x: -300, y: -800 },
            { id: 'LETRA_EXPONENTE', label: 'Letra de Exponente', shape: 'ellipse', color: colors.num, x: -100, y: -300 },
            { id: 'SIGNO_EXPONENTE', label: 'Signo de Exponente', shape: 'ellipse', color: colors.num, x: 0, y: 200 },
            { id: 'OP_RELACIONAL_PARCIAL', label: 'Op. Relacional Parcial', shape: 'ellipse', color: colors.op, x: -100, y: 700 },
            { id: 'OP_RELACIONAL_DOBLE', label: 'Op. Relacional Doble', shape: 'ellipse', color: colors.op, x: -300, y: 1200 },
            { id: 'OP_ARITMETICO', label: 'Operador Aritmético', shape: 'ellipse', color: colors.op, x: -600, y: 1700 },
            { id: 'SIMBOLO_UNICO', label: 'Símbolo de Puntuación', shape: 'ellipse', color: colors.sym, x: -1000, y: 2200 },
            { id: 'DIAGONAL_INICIAL', label: 'Primera Diagonal', shape: 'ellipse', color: colors.comment, x: -1500, y: 2700 },
            { id: 'COMENTARIO_LINEA', label: 'Comentario de Línea', shape: 'ellipse', color: colors.comment, x: -2000, y: 3200 },
            { id: 'ASTERISCO_CIERRE', label: 'Asterisco de Cierre', shape: 'ellipse', color: colors.comment, x: -2500, y: 3700 },

            { id: 'INICIO_CADENA', label: 'Apertura Comillas Dobles', shape: 'ellipse', color: colors.text, x: -4000, y: -2800 },
            { id: 'ESCAPE_CADENA', label: 'Carácter de Escape', shape: 'ellipse', color: colors.text, x: -2000, y: -3300 },
            { id: 'INICIO_CARACTER', label: 'Apertura Comilla Simple', shape: 'ellipse', color: colors.text, x: -3500, y: -2300 },
            { id: 'PUNTO_DECIMAL', label: 'Punto Decimal', shape: 'ellipse', color: colors.num, x: -1500, y: -1050 },
            { id: 'COMENTARIO_BLOQUE', label: 'Cuerpo Comentario Multilínea', shape: 'ellipse', color: colors.comment, x: -3500, y: 3700 },
            { id: 'ERROR_LEXICO', label: 'ERROR LÉXICO', shape: 'ellipse', color: colors.error, x: 2000, y: -2500, font: {size: 25} },
            { id: 'FIN_ARCHIVO', label: 'FIN DE ARCHIVO INESPERADO', shape: 'ellipse', color: colors.error, x: 2000, y: -1800, font: {size: 25} }
        ];

        var edgesArray = [
            { from: 'INICIO', to: 'INICIO', label: 'Espacio en blanco', arrows: 'to' },
            { from: 'INICIO', to: 'INICIO_CADENA', label: 'Comillas Dobles ( " )', arrows: 'to' },
            { from: 'INICIO', to: 'INICIO_CARACTER', label: 'Comilla Simple ( \' )', arrows: 'to' },
            { from: 'INICIO', to: 'IDENTIFICADOR', label: 'Letra / _', arrows: 'to' },
            { from: 'INICIO', to: 'NUMERO_ENTERO', label: 'Dígito Numérico', arrows: 'to' },
            { from: 'INICIO', to: 'OP_RELACIONAL_PARCIAL', label: '< > = !', arrows: 'to' },
            { from: 'INICIO', to: 'OP_ARITMETICO', label: '+ - * %', arrows: 'to' },
            { from: 'INICIO', to: 'SIMBOLO_UNICO', label: '( ) [ ] { } ; ,', arrows: 'to' },
            { from: 'INICIO', to: 'DIAGONAL_INICIAL', label: 'Diagonal ( / )', arrows: 'to' },
            
            { from: 'INICIO_CADENA', to: 'CUERPO_CADENA', label: 'Cualquier carácter', arrows: 'to' },
            { from: 'CUERPO_CADENA', to: 'CUERPO_CADENA', label: 'Continúa texto', arrows: 'to' },
            { from: 'CUERPO_CADENA', to: 'ESCAPE_CADENA', label: 'Escape ( \\ )', arrows: 'to' },
            { from: 'ESCAPE_CADENA', to: 'CUERPO_CADENA', label: 'Carácter Escapado', arrows: 'to' },
            
            { from: 'INICIO_CARACTER', to: 'CUERPO_CARACTER', label: 'Un carácter', arrows: 'to' },
            
            { from: 'IDENTIFICADOR', to: 'IDENTIFICADOR', label: 'Letra/Dígito/_', arrows: 'to' },
            
            { from: 'NUMERO_ENTERO', to: 'NUMERO_ENTERO', label: 'Dígito', arrows: 'to' },
            { from: 'NUMERO_ENTERO', to: 'PUNTO_DECIMAL', label: 'Punto ( . )', arrows: 'to' },
            { from: 'PUNTO_DECIMAL', to: 'NUMERO_FLOTANTE', label: 'Dígito', arrows: 'to' },
            { from: 'NUMERO_FLOTANTE', to: 'NUMERO_FLOTANTE', label: 'Dígito', arrows: 'to' },
            { from: 'NUMERO_ENTERO', to: 'LETRA_EXPONENTE', label: 'e | E', arrows: 'to' },
            { from: 'NUMERO_FLOTANTE', to: 'LETRA_EXPONENTE', label: 'e | E', arrows: 'to' },
            { from: 'LETRA_EXPONENTE', to: 'SIGNO_EXPONENTE', label: '+ | -', arrows: 'to' },
            
            { from: 'OP_RELACIONAL_PARCIAL', to: 'OP_RELACIONAL_DOBLE', label: 'Signo Igual ( = )', arrows: 'to' },
            
            { from: 'DIAGONAL_INICIAL', to: 'COMENTARIO_LINEA', label: 'Segunda Diagonal', arrows: 'to' },
            { from: 'COMENTARIO_LINEA', to: 'COMENTARIO_LINEA', label: 'Texto', arrows: 'to' },
            { from: 'DIAGONAL_INICIAL', to: 'COMENTARIO_BLOQUE', label: 'Asterisco ( * )', arrows: 'to' },
            { from: 'COMENTARIO_BLOQUE', to: 'COMENTARIO_BLOQUE', label: 'Texto', arrows: 'to' },
            { from: 'COMENTARIO_BLOQUE', to: 'ASTERISCO_CIERRE', label: 'Asterisco', arrows: 'to' },
            { from: 'ASTERISCO_CIERRE', to: 'COMENTARIO_BLOQUE', label: 'No es diagonal', arrows: 'to' },
            
            { from: 'PUNTO_DECIMAL', to: 'ERROR_LEXICO', label: 'Carácter inválido', arrows: 'to', font: {color: 'red'} },
            
            { from: 'CUERPO_CADENA', to: 'HECHO', label: 'Comillas Cierre', arrows: 'to' },
            { from: 'CUERPO_CARACTER', to: 'HECHO', label: 'Comilla Cierre', arrows: 'to' },
            { from: 'IDENTIFICADOR', to: 'HECHO', label: 'Otro carácter', arrows: 'to' },
            { from: 'NUMERO_ENTERO', to: 'HECHO', label: 'Otro carácter', arrows: 'to' },
            { from: 'NUMERO_FLOTANTE', to: 'HECHO', label: 'Otro carácter', arrows: 'to' },
            { from: 'LETRA_EXPONENTE', to: 'HECHO', label: 'Dígito', arrows: 'to' },
            { from: 'SIGNO_EXPONENTE', to: 'HECHO', label: 'Dígito', arrows: 'to' },
            { from: 'OP_RELACIONAL_PARCIAL', to: 'HECHO', label: 'Otro carácter', arrows: 'to' },
            { from: 'OP_RELACIONAL_DOBLE', to: 'HECHO', label: 'Aceptar', arrows: 'to' },
            { from: 'OP_ARITMETICO', to: 'HECHO', label: 'Aceptar', arrows: 'to' },
            { from: 'SIMBOLO_UNICO', to: 'HECHO', label: 'Aceptar', arrows: 'to' },
            { from: 'DIAGONAL_INICIAL', to: 'HECHO', label: 'Otro (Op. División)', arrows: 'to' },
            { from: 'COMENTARIO_LINEA', to: 'HECHO', label: 'Salto de Línea', arrows: 'to' },
            { from: 'ASTERISCO_CIERRE', to: 'HECHO', label: 'Diagonal Cierre ( / )', arrows: 'to' }
        ];

        var nodes = new vis.DataSet(nodesArray);
        var edges = new vis.DataSet(edgesArray);
        var container = document.getElementById('automata-network');
        
        var options = {
            physics: false, 
            interaction: {
                hover: true, 
                zoomView: true,
                navigationButtons: true,
                tooltipDelay: 30
            },
            nodes: {
                borderWidth: 2,
                font: { size: 18, face: 'arial', background: 'rgba(255,255,255,0.9)' }
            },
            edges: {
                smooth: false,
                color: { color: '#555', highlight: '#000', hover: '#000' },
                font: { align: 'middle', size: 16, background: 'rgba(255,255,255,0.95)' },
                arrows: { to: { scaleFactor: 1.2 } },
                width: 2
            }
        };

        var network = new vis.Network(container, { nodes: nodes, edges: edges }, options);
        
        // --- LÓGICA DE LA LUPA ---
        var miniNodes = new vis.DataSet();
        var miniEdges = new vis.DataSet();
        var tooltipContainer = document.getElementById('tooltip-container');
        var tooltipTitle = document.getElementById('tooltip-title');
        var infoText = document.getElementById('info-text');
        var tooltipNetwork = null;

        function initMiniNetwork() {
            if (!tooltipNetwork) {
                var miniContainer = document.getElementById('tooltip-network');
                var miniOptions = {
                    physics: { enabled: false },
                    interaction: { dragNodes: false, zoomView: false, hover: false, dragView: false },
                    nodes: { font: { size: 11, bold: true }, borderWidth: 2 },
                    edges: { 
                        smooth: { type: 'dynamic' }, 
                        font: { size: 10, align: 'middle', background: 'rgba(255,255,255,0.8)' }, 
                        arrows: { to: { scaleFactor: 0.7 } }
                    }
                };
                tooltipNetwork = new vis.Network(miniContainer, { nodes: miniNodes, edges: miniEdges }, miniOptions);
            }
        }

        function getFirstLine(text) {
            return text ? text.split('\n')[0] : '';
        }

        // EVENTO: HOVER EN FLECHA (TRANSICIÓN)
        network.on("hoverEdge", function (params) {
            initMiniNetwork();
            const edgeData = edges.get(params.edge);
            if(!edgeData) return;

            const fromNode = nodes.get(edgeData.from);
            const toNode = nodes.get(edgeData.to);

            tooltipTitle.innerText = "🔍 Transición Seleccionada";
            miniNodes.clear();
            miniEdges.clear();

            if (edgeData.from === edgeData.to) {
                miniNodes.add({...fromNode, id: 'm1', label: getFirstLine(fromNode.label), x: 0, y: 0});
                miniEdges.add({...edgeData, from: 'm1', to: 'm1'});
            } else {
                miniNodes.add({...fromNode, id: 'm1', label: getFirstLine(fromNode.label), x: -100, y: 0});
                miniNodes.add({...toNode, id: 'm2', label: getFirstLine(toNode.label), x: 100, y: 0});
                miniEdges.add({...edgeData, from: 'm1', to: 'm2'});
            }

            infoText.innerHTML = `<strong>${getFirstLine(fromNode.label)}</strong> <span class='tooltip-arrow'>→</span> <strong>${getFirstLine(toNode.label)}</strong><br>Entrada: <i>${edgeData.label}</i>`;
            showTooltip();
        });

        // EVENTO: HOVER EN NODO (ESTADO)
        network.on("hoverNode", function (params) {
            initMiniNetwork();
            const nodeId = params.node;
            const nodeData = nodes.get(nodeId);
            const connectedEdges = network.getConnectedEdges(nodeId);

            tooltipTitle.innerText = "🔍 Contexto del Estado";
            miniNodes.clear();
            miniEdges.clear();

            // Nodo central
            miniNodes.add({...nodeData, id: 'center', label: getFirstLine(nodeData.label), x: 0, y: 0, shadow: true});

            // Mostrar conexiones inmediatas (Entrantes y Salientes)
            connectedEdges.forEach((edgeId, idx) => {
                const edge = edges.get(edgeId);
                const isOut = edge.from === nodeId;
                const neighborId = isOut ? edge.to : edge.from;
                const neighborData = nodes.get(neighborId);
                
                // Evitar duplicar el nodo central en bucles
                if (neighborId !== nodeId) {
                    const angle = (idx / connectedEdges.length) * 2 * Math.PI;
                    const radius = 130;
                    const mId = 'neigh_' + edgeId;
                    
                    if (!miniNodes.get(mId)) {
                        miniNodes.add({
                            ...neighborData, 
                            id: mId, 
                            label: getFirstLine(neighborData.label),
                            x: Math.cos(angle) * radius,
                            y: Math.sin(angle) * radius,
                            size: 15,
                            font: {size: 9}
                        });
                    }
                    miniEdges.add({
                        ...edge, 
                        from: isOut ? 'center' : mId, 
                        to: isOut ? mId : 'center'
                    });
                } else {
                    // Es un bucle sobre sí mismo
                    miniEdges.add({...edge, from: 'center', to: 'center'});
                }
            });

            infoText.innerHTML = `Estado actual: <strong>${getFirstLine(nodeData.label)}</strong><br><small>Mostrando transiciones conectadas</small>`;
            showTooltip();
        });

        function showTooltip() {
            tooltipContainer.style.display = 'flex';
            setTimeout(() => {
                tooltipNetwork.fit();
            }, 50);
        }

        network.on("blurEdge", () => tooltipContainer.style.display = 'none');
        network.on("blurNode", () => tooltipContainer.style.display = 'none');

        network.once('afterDrawing', () => network.fit({ animation: { duration: 1000 } }));

    </script>
</body>
</html>

