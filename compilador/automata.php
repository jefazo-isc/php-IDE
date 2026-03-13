<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDE Indómito - Autómata Léxico</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        :root {
            --bg-primary: #f4f4f4; 
            --bg-secondary: #ffffff;
            --text-primary: #111111;
            --accent-primary: #C70039;
            --font-sans: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            font-family: var(--font-sans);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background-color: var(--bg-secondary);
            padding: 15px 20px;
            border-bottom: 2px solid #ccc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .header h1 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        .instrucciones {
            font-size: 0.9rem;
            color: #555;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-captura { background-color: var(--accent-primary); }
        .btn-acomodar { background-color: #0277bd; }

        #automata-network {
            flex: 1;
            width: 100%;
            height: 100%;
            background-color: var(--bg-primary);
        }

        .vis-network:focus {
            outline: none;
        }

        .oculto {
            display: none !important;
        }
    </style>
</head>
<body>

    <div class="header" id="top-header">
        <h1>🕸️ Autómata Finito Determinista Completo</h1>
        <span class="instrucciones">Diseño estático optimizado. Mueve los nodos libremente; se quedarán donde los dejes.</span>
        <div class="btn-group">
            <button class="btn btn-acomodar" onclick="restablecerNodos()">✨ Restablecer Posiciones</button>
            <button class="btn btn-captura" onclick="prepararCaptura()">📷 Modo Captura</button>
        </div>
    </div>

    <div id="automata-network"></div>

    <script type="text/javascript">
        function prepararCaptura() {
            document.getElementById('top-header').classList.add('oculto');
        }

        // Distribución manual basada en el pizarrón original para asegurar legibilidad
        const posicionesBase = {
            'INICIO':        { x: -600, y: 0 },
            'HECHO':         { x: 600,  y: 0 },
            'NUMERO':        { x: -100, y: -300 },
            'NUM_REAL':      { x: 200,  y: -200 },
            'ID':            { x: -200, y: -100 },
            'ASIGNACION':    { x: -400, y: 100 },
            'OP_LOGICO':     { x: -250, y: 250 },
            'OP_RELACIONAL': { x: 50,   y: 150 },
            'SIMBOLO':       { x: 250,  y: 200 },
            'STRING':        { x: -100, y: 350 },
            'CHAR':          { x: 250,  y: 350 },
            'COM_SIMPLE':    { x: -300, y: 400 },
            'COM_MULTI':     { x: 100,  y: 450 },
            'ERROR':         { x: 450,  y: -350 },
            'EOF':           { x: 450,  y: 350 }
        };

        function restablecerNodos() {
            // Devuelve los nodos a su posición original codificada en duro
            let updates = [];
            nodes.forEach(node => {
                if (posicionesBase[node.id]) {
                    updates.push({ id: node.id, x: posicionesBase[node.id].x, y: posicionesBase[node.id].y });
                }
            });
            nodes.update(updates);
            network.fit({ animation: { duration: 800, easingFunction: 'easeInOutQuad' } });
        }

        const colorInicio = { background: '#ffffff', border: '#333333' }; 
        const colorTransicion = { background: '#e1f5fe', border: '#0277bd' }; 
        const colorAceptacion = { background: '#c8e6c9', border: '#2e7d32' }; 
        const colorError = { background: '#ffcdd2', border: '#c62828' };

        // Definición de nodos con coordenadas X e Y inyectadas
        var nodesArray = [
            { id: 'INICIO', label: 'INICIO', shape: 'circle', color: colorInicio, font: { color: 'black' } },
            
            { id: 'ASIGNACION', label: 'ASIGNACION', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'OP_LOGICO', label: 'OP.LOGICO', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'OP_RELACIONAL', label: 'OP.RELACIONAL', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'ID', label: 'ID', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'NUMERO', label: 'NUMERO', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'NUM_REAL', label: 'NUM.REAL', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'STRING', label: 'STRING', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'CHAR', label: 'CHAR', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'SIMBOLO', label: 'SIMBOLO', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'COM_SIMPLE', label: '// comentario simple\nhasta \\n', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            { id: 'COM_MULTI', label: '/* comentario\nmulti-linea */', shape: 'ellipse', color: colorTransicion, font: { color: 'black' } },
            
            { id: 'ERROR', label: 'ERROR LÉXICO', shape: 'ellipse', color: colorError, font: { color: 'black' } },
            { id: 'EOF', label: 'EOF\n(Fin Archivo)', shape: 'circle', color: colorAceptacion, borderWidth: 3, font: { color: 'black' } },
            { id: 'HECHO', label: 'HECHO', shape: 'circle', color: colorAceptacion, borderWidth: 3, font: { color: 'black', size: 18 } }
        ];

        // Mapeamos las posiciones base al arreglo de nodos antes de dárselo a vis.js
        nodesArray.forEach(node => {
            if (posicionesBase[node.id]) {
                node.x = posicionesBase[node.id].x;
                node.y = posicionesBase[node.id].y;
            }
        });

        var nodes = new vis.DataSet(nodesArray);

        var edges = new vis.DataSet([
            { from: 'INICIO', to: 'INICIO', label: 'espacio en blanco\nignorar', arrows: 'to', font: { align: 'top' }, selfReferenceSize: 40 },
            
            { from: 'INICIO', to: 'ASIGNACION', label: '=', arrows: 'to' },
            { from: 'ASIGNACION', to: 'OP_RELACIONAL', label: '=', arrows: 'to' },
            { from: 'ASIGNACION', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'OP_LOGICO', label: '!', arrows: 'to' },
            { from: 'OP_LOGICO', to: 'OP_RELACIONAL', label: '=', arrows: 'to' },
            { from: 'OP_LOGICO', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'OP_RELACIONAL', label: '<, >', arrows: 'to' },
            { from: 'OP_RELACIONAL', to: 'OP_RELACIONAL', label: '=', arrows: 'to' },
            { from: 'OP_RELACIONAL', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'ID', label: 'letra', arrows: 'to' },
            { from: 'ID', to: 'ID', label: 'letra, dígito, _', arrows: 'to', selfReferenceSize: 30 },
            { from: 'ID', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'NUMERO', label: 'dígito', arrows: 'to' },
            { from: 'NUMERO', to: 'NUMERO', label: 'dígito', arrows: 'to', selfReferenceSize: 30 },
            { from: 'NUMERO', to: 'NUM_REAL', label: '.', arrows: 'to' },
            { from: 'NUMERO', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'NUM_REAL', to: 'NUM_REAL', label: 'dígito', arrows: 'to', selfReferenceSize: 30 },
            { from: 'NUM_REAL', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'STRING', label: '"', arrows: 'to' },
            { from: 'STRING', to: 'STRING', label: 'cualquier char', arrows: 'to', selfReferenceSize: 30 },
            { from: 'STRING', to: 'HECHO', label: '"', arrows: 'to' },
            
            { from: 'INICIO', to: 'CHAR', label: "\\'", arrows: 'to' },
            { from: 'CHAR', to: 'CHAR', label: 'cualquier char', arrows: 'to', selfReferenceSize: 30 },
            { from: 'CHAR', to: 'HECHO', label: "\\'", arrows: 'to' },
            
            { from: 'INICIO', to: 'SIMBOLO', label: '{ } [ ] ( ) ; , + - *', arrows: 'to' },
            { from: 'SIMBOLO', to: 'HECHO', label: '[otro]', arrows: 'to' },
            
            { from: 'INICIO', to: 'COM_SIMPLE', label: '/', arrows: 'to' },
            { from: 'COM_SIMPLE', to: 'COM_SIMPLE', label: 'texto', arrows: 'to', selfReferenceSize: 30 },
            { from: 'COM_SIMPLE', to: 'HECHO', label: '\\n', arrows: 'to' },
            
            { from: 'INICIO', to: 'COM_MULTI', label: '/*', arrows: 'to' },
            { from: 'COM_MULTI', to: 'COM_MULTI', label: 'texto', arrows: 'to', selfReferenceSize: 30 },
            { from: 'COM_MULTI', to: 'HECHO', label: '*/', arrows: 'to' },

            { from: 'INICIO', to: 'ERROR', label: 'char inválido', arrows: 'to', font: { color: 'red' } },
            { from: 'ERROR', to: 'HECHO', label: 'recuperación', arrows: 'to' },
            { from: 'INICIO', to: 'EOF', label: 'fin de archivo', arrows: 'to' }
        ]);

        var container = document.getElementById('automata-network');
        var data = { nodes: nodes, edges: edges };
        
        var options = {
            // FÍSICAS APAGADAS: Esto garantiza que los nodos se queden donde los definimos
            physics: {
                enabled: false
            },
            interaction: {
                hover: true,
                navigationButtons: false,
                keyboard: false,
                zoomView: true,
                dragNodes: true // Permitimos moverlos manualmente sin que reboten
            },
            edges: {
                smooth: { 
                    type: 'curvedCW', 
                    roundness: 0.2 
                },
                color: { color: '#666666', highlight: '#000000' },
                font: { size: 12, background: 'rgba(255,255,255,0.8)' }
            }
        };

        var network = new vis.Network(container, data, options);
        
        // Centrar la cámara en el primer renderizado
        network.once('afterDrawing', function() {
            network.fit({ animation: false });
        });
    </script>
</body>
</html>
