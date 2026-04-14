<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDE Indómito - Compiladores</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="menubar">
        <div class="menu-left">
            <div class="dropdown">
                <button class="dropbtn">📁 Archivo</button>
                <div class="dropdown-content">
                    <button onclick="nuevoArchivo()">📄 Nuevo</button>
                    <button onclick="abrirArchivo()">📂 Abrir Local</button>
                    <button onclick="cerrarArchivo()">❌ Cerrar Archivo</button>
                    <div class="separator"></div>
                    <button onclick="guardarArchivo()">💾 Guardar</button>
                    <button onclick="guardarComoArchivo()">💾 Guardar como</button>
                    <button onclick="verLogErrores()">📜 Log</button>
                    <div class="separator"></div>
                    <button onclick="salirIDE()">🚪 Salir</button>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="dropbtn">⚙️ Compilar</button>
                <div class="dropdown-content">
                    <button onclick="compilarFase('lexico')">🔍 Léxico</button>
                    <button onclick="compilarFase('sintactico')">🌳 Sintáctico</button>
                    <button onclick="compilarFase('semantico')">🧠 Semántico</button>
                    <button onclick="compilarFase('intermedio')">⚙️ Intermedio</button>
                    <button onclick="compilarFase('simbolos')">📊 Símbolos</button>
                    <div class="separator"></div>
                    <button onclick="compilarFase('ejecucion')" class="btn-ejecutar">▶️ Ejecutar</button>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="dropbtn">👁️ Vista</button>
                <div class="dropdown-content">
                    <button onclick="toggleExplorer()">🗂️ Alternar Explorador</button>
                    <button onclick="toggleRightPanel()">🗂️ Panel Compilador</button>
                    <button onclick="toggleBottomPanel()">🗄️ Panel Inferior</button>
                    <div class="separator"></div>
                    <button onclick="toggleTheme()">🌓 Tema Claro/Oscuro</button>
                    <button onclick="toggleRGB()">🌈 Tema RGB (Glow)</button>
                    <div class="separator"></div>
                    <button onclick="abrirAutomata()">🕸️ Ver Autómata Léxico</button>
                </div>
            </div>
        </div>
        
        <div class="menu-right">
            <button class="menu-btn" onclick="nuevoArchivo()" title="Nuevo">📄</button>
            <button class="menu-btn" onclick="guardarArchivo()" title="Guardar">💾</button>
            <button class="menu-btn" onclick="toggleTheme()" title="Tema">🌓</button>
            <button class="menu-btn btn-ejecutar" onclick="compilarFase('ejecucion')">▶</button>
            <select id="file_extension" class="extension-selector" onchange="setExtension(DOM.currentFilename.value + '.' + this.value)">
                <option value="txt">TXT</option>
                <option value="c">C</option>
                <option value="cpp">CPP</option>
                <option value="php">PHP</option>
                <option value="js">JS</option>
                <option value="html">HTML</option>
                <option value="css">CSS</option>
            </select>
        </div>
    </div>

    <div class="main-layout">
        <div class="top-layout">
            <div class="file-explorer" id="file_explorer">
                <div class="explorer-header">Proyecto</div>
                <div class="explorer-path-bar">
                    <button class="up-btn" onclick="subirDirectorio()" title="Subir nivel">⬆️</button>
                    <input type="text" id="workspace_path" placeholder="Ruta..." onkeypress="if(event.key === 'Enter') cargarExplorador()">
                    <button class="go-btn" onclick="cargarExplorador()">Ir</button>
                </div>
                <div class="explorer-content" id="explorer_content">Cargando archivos...</div>
            </div>
            
            <div class="resizer-vertical" id="resizer_explorer"></div>

            <div class="editor-section" id="editor_container">
                <div class="editor-wrapper">
                    <div class="line-numbers" id="line_numbers">1</div>
                    <div class="code-area">
                        <textarea id="editor" spellcheck="false" oninput="handleInput()" onscroll="syncScroll()" onkeyup="actualizarCursorRápido()" onclick="actualizarCursorRápido()"></textarea>
                        <pre id="highlighting"></pre>
                    </div>
                </div>
                <div class="status-bar">
                    <div class="status-info">
                        <span id="cursor_pos">Ln 1, Col 1</span>
                        <span id="file_indicator">Archivo: Sin título</span>
                    </div>
                    <div class="status-info">
                        <span id="status_msg">Sistema listo.</span>
                    </div>
                </div>
            </div>

            <div class="resizer-vertical" id="resizer_v"></div>

            <div class="right-panels" id="right_panels">
                <div class="tabs">
                    <div class="tab active" onclick="showRightPanel(event, 'lexico')">Léxico</div>
                    <div class="tab" onclick="showRightPanel(event, 'sintactico')">Sintáctico</div>
                    <div class="tab" onclick="showRightPanel(event, 'semantico')">Semántico</div>
                    <div class="tab" onclick="showRightPanel(event, 'intermedio')">Intermedio</div>
                    <div class="tab" onclick="showRightPanel(event, 'simbolos')">Símbolos</div>
                    <div class="tab" onclick="showRightPanel(event, 'ejecucion')">Resultados</div>
                </div>
                <div class="panel-content right-content" id="panel_lexico">Esperando...</div>
                <div class="panel-content right-content oculto" id="panel_sintactico">Esperando...</div>
                <div class="panel-content right-content oculto" id="panel_semantico">Esperando...</div>
                <div class="panel-content right-content oculto" id="panel_intermedio">Esperando...</div>
                <div class="panel-content right-content oculto" id="panel_simbolos">Esperando...</div>
                <div class="panel-content right-content oculto" id="panel_ejecucion">Esperando...</div>
            </div>
        </div>

        <div class="resizer-horizontal" id="resizer_h"></div>

        <div class="bottom-panels" id="bottom_panels">
            <div class="tabs">
                <div class="tab active" onclick="showBottomPanel(event, 'err_lex')">Errores Léxicos</div>
                <div class="tab" onclick="showBottomPanel(event, 'err_sin')">Errores Sintácticos</div>
                <div class="tab" onclick="showBottomPanel(event, 'err_sem')">Errores Semánticos</div>
            </div>
            <div class="panel-content bottom-content" id="panel_err_lex">Sin errores.</div>
            <div class="panel-content bottom-content oculto" id="panel_err_sin">Sin errores.</div>
            <div class="panel-content bottom-content oculto" id="panel_err_sem">Sin errores.</div>
        </div>
    </div>

    <input type="hidden" id="current_filename" value="Sin título">
    <script src="assets/script.js"></script>
</body>
</html>