// ==========================================================================
// CACHÉ DEL DOM (Optimización de rendimiento)
// ==========================================================================
const DOM = {
    editor: document.getElementById('editor'),
    highlighting: document.getElementById('highlighting'),
    lineNumbers: document.getElementById('line_numbers'),
    cursorPos: document.getElementById('cursor_pos'),
    fileExt: document.getElementById('file_extension'),
    currentFilename: document.getElementById('current_filename'),
    fileIndicator: document.getElementById('file_indicator'),
    statusMsg: document.getElementById('status_msg'),
    workspacePath: document.getElementById('workspace_path'),
    explorerContent: document.getElementById('explorer_content'),
    fileExplorer: document.getElementById('file_explorer'),
    editorContainer: document.getElementById('editor_container'),
    rightPanels: document.getElementById('right_panels'),
    bottomPanels: document.getElementById('bottom_panels'),
    topLayout: document.querySelector('.top-layout'),
    resizerV: document.getElementById('resizer_v'),
    resizerExp: document.getElementById('resizer_explorer'),
    resizerH: document.getElementById('resizer_h')
};

let currentExt = DOM.fileExt.value;
let currentOpenedFileAbsPath = '';
let currentLineCount = 0;
let saveInProgress = false;

// ==========================================================================
// SISTEMA DE MODALES
// ==========================================================================
const Modals = {
    show: (titulo, htmlContent, buttons) => new Promise((resolve) => {
        document.getElementById('ide_modal_overlay')?.remove();

        const overlay = document.createElement('div');
        overlay.id = 'ide_modal_overlay';
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; display: flex; justify-content: center; align-items: center;';
        
        const btnsHtml = buttons.map((b, i) => {
            const bg = b.primary ? 'var(--accent-primary)' : 'var(--bg-tertiary)';
            const fw = b.primary ? 'bold' : 'normal';
            return `<button id="mbtn_${i}" style="padding: 8px 15px; cursor: pointer; background: ${bg}; color: #fff; border: 1px solid var(--border-color); border-radius: 4px; margin-left: 10px; font-weight: ${fw}; transition: background 0.2s;">${b.text}</button>`;
        }).join('');

        const box = document.createElement('div');
        box.style.cssText = 'background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; min-width: 350px; max-width: 500px; box-shadow: 0 5px 15px var(--shadow-color); font-family: var(--font-sans);';
        box.innerHTML = `<h3 style="margin-top: 0; color: var(--text-primary); margin-bottom: 15px;">${titulo}</h3><div style="color: var(--text-primary); margin-bottom: 20px; font-size: 14px; max-height: 60vh; overflow-y: auto;">${htmlContent}</div><div style="display: flex; justify-content: flex-end;">${btnsHtml}</div>`;
        
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        buttons.forEach((b, i) => {
            document.getElementById(`mbtn_${i}`).addEventListener('click', () => {
                overlay.style.display = 'none';
                resolve(b.value);
                setTimeout(() => overlay.remove(), 50);
            });
        });
    }),
    prompt: async (titulo, mensaje, def = '') => {
        const html = `<p style="margin-bottom: 10px;">${mensaje}</p><input type="text" id="modal_input" value="${def}" style="width: 100%; padding: 8px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: var(--font-mono);">`;
        setTimeout(() => {
            const input = document.getElementById('modal_input');
            if (input) {
                input.focus();
                const dotIndex = input.value.lastIndexOf('.');
                dotIndex > 0 ? input.setSelectionRange(0, dotIndex) : input.select();
                input.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('mbtn_1').click(); });
            }
        }, 50);
        return await Modals.show(titulo, html, [{text: 'Cancelar', value: null}, {text: 'Aceptar', value: 'OK', primary: true}]) === 'OK' ? document.getElementById('modal_input').value : null;
    },
    confirm: async (tit, msg, btnSi = 'Sí', btnNo = 'No') => await Modals.show(tit, `<p>${msg}</p>`, [{text: btnNo, value: false}, {text: btnSi, value: true, primary: true}]),
    alert: async (tit, msg) => await Modals.show(tit, `<pre style="white-space: pre-wrap; font-family: var(--font-mono); margin: 0;">${msg}</pre>`, [{text: 'Aceptar', value: true, primary: true}])
};

// ==========================================================================
// RESIZERS
// ==========================================================================
let isResizing = { v: false, exp: false, h: false };
let animFrame = null;

const enableResize = (type, cursor) => {
    isResizing[type] = true;
    document.body.style.cursor = cursor;
    DOM.editor.style.pointerEvents = 'none';
};

DOM.resizerV.addEventListener('mousedown', () => enableResize('v', 'col-resize'));
DOM.resizerExp.addEventListener('mousedown', () => enableResize('exp', 'col-resize'));
DOM.resizerH.addEventListener('mousedown', () => enableResize('h', 'row-resize'));

document.addEventListener('mousemove', e => {
    if (!isResizing.v && !isResizing.h && !isResizing.exp) return;
    if (animFrame) cancelAnimationFrame(animFrame);
    
    animFrame = requestAnimationFrame(() => {
        if (isResizing.v) {
            let w = document.body.clientWidth - e.clientX;
            if (w > 200 && w < window.innerWidth - 200) DOM.rightPanels.style.width = w + 'px';
        }
        if (isResizing.exp) {
            let w = e.clientX;
            if (w > 150 && w < window.innerWidth / 2) DOM.fileExplorer.style.width = w + 'px';
        }
        if (isResizing.h) {
            let h = e.clientY - document.querySelector('.main-layout').getBoundingClientRect().top;
            if (h > 100 && h < window.innerHeight - 100) DOM.topLayout.style.flex = `0 0 ${h}px`;
        }
    });
});

document.addEventListener('mouseup', () => {
    isResizing = { v: false, exp: false, h: false };
    document.body.style.cursor = 'default';
    DOM.editor.style.pointerEvents = 'auto';
});
window.addEventListener('resize', synchronizeScrollbarOffset);

// ==========================================================================
// EXPLORADOR Y ATAJOS
// ==========================================================================
document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); guardarArchivo(); } });

async function cargarExplorador(rutaSolicitada = null) {
    const ruta = rutaSolicitada ?? DOM.workspacePath.value.trim();
    const formData = new URLSearchParams({ accion: 'explorar_directorio', ...(ruta && { ruta }) });

    try {
        const res = await (await fetch('index.php', { method: 'POST', body: formData })).json();
        if (res.success) {
            DOM.workspacePath.value = res.ruta_actual;
            DOM.explorerContent.innerHTML = res.elementos.length ? res.elementos.map(el => {
                const rEsc = el.ruta.replace(/\\/g, '\\\\'), mName = el.nombre.replace(/'/g, "\\'");
                return el.es_directorio 
                    ? `<div class="explorer-item folder" onclick="cargarExplorador('${rEsc}')">${el.nombre}</div>`
                    : `<div class="explorer-item file"><div class="file-name" onclick="abrirDesdeExplorador('${rEsc}', '${mName}')">${el.nombre}</div><button class="btn-delete-file" onclick="borrarArchivo(event, '${rEsc}', '${mName}')" title="Eliminar archivo">🗑️</button></div>`;
            }).join('') : '<div style="padding: 10px; color: var(--text-muted);">Carpeta vacía</div>';
        }
    } catch (e) { console.error("Error explorador:", e); }
}

function subirDirectorio() {
    const rutaActual = DOM.workspacePath.value.trim();
    if (rutaActual) {
        let partes = rutaActual.split(/[\\/]/);
        partes.pop();
        cargarExplorador(partes.length <= 1 ? '/' : partes.join('/'));
    }
}

async function abrirDesdeExplorador(rutaAbsoluta, nombre) {
    try {
        const data = await (await fetch(`index.php?accion=cargar_archivo&ruta_absoluta=${encodeURIComponent(rutaAbsoluta)}`)).json();
        if (data.success) {
            DOM.editor.value = decodeURIComponent(escape(atob(data.contenido)));
            setFilename(data.nombre);
            currentOpenedFileAbsPath = data.ruta_absoluta;
            procesarCambiosPesados();
            limpiarPaneles();
            mostrarNotificacion('📂 Abierto');
        }
    } catch (e) { mostrarNotificacion("Error al abrir", true); }
}

const togglePanel = (el, resizer) => { el.classList.toggle('panel-hidden'); resizer.classList.toggle('panel-hidden'); };
function toggleExplorer() { togglePanel(DOM.fileExplorer, DOM.resizerExp); }
function toggleRightPanel() { togglePanel(DOM.rightPanels, DOM.resizerV); }
function toggleBottomPanel() { togglePanel(DOM.bottomPanels, DOM.resizerH); }

// ==========================================================================
// EDITOR Y SINTAXIS
// ==========================================================================
function applySyntaxHighlighting(text) {
    const html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const kw = 'if|else|end|do|while|switch|case|int|float|main|cin|cout';
    const regex = new RegExp(`(\\/\\*[\\s\\S]*?\\*\\/|\\/\\/.*)|("[^"]*"|'[^']*')|\\b(${kw})\\b|(&lt;=|&gt;=|!=|==|&lt;|&gt;|&amp;&amp;|\\|\\||!)|(\\+\\+|--|\\+|-|\\*|\\/|%|\\^)|\\b\\d+(\\.\\d+)?\\b|\\b([a-zA-Z_][a-zA-Z0-9_]*)\\b`, 'g');

    return html.replace(regex, (m, p1, p2, p3, p4, p5, p6, p7) => {
        if (p1) return `<span class="syntax-color3">${m}</span>`;
        if (p2) return `<span class="syntax-string">${m}</span>`;
        if (p3) return `<span class="syntax-color4">${m}</span>`;
        if (p4) return `<span class="syntax-color6">${m}</span>`;
        if (p5) return `<span class="syntax-color5">${m}</span>`;
        if (p6) return `<span class="syntax-color1">${m}</span>`;
        if (p7) return `<span class="syntax-color2">${m}</span>`;
        return m;
    });
}

function setFilename(filename) {
    DOM.currentFilename.value = filename;
    DOM.fileIndicator.textContent = 'Archivo: ' + filename;
    setExtension(filename);
}

function setExtension(filename) {
    currentExt = filename.includes('.') ? filename.split('.').pop().toLowerCase() : 'txt';
    DOM.fileExt.value = currentExt;
    procesarCambiosPesados();
}

function syncScroll() {
    DOM.lineNumbers.scrollTop = DOM.highlighting.scrollTop = DOM.editor.scrollTop;
    DOM.highlighting.scrollLeft = DOM.editor.scrollLeft;
}

function handleInput() {
    DOM.highlighting.innerHTML = applySyntaxHighlighting(DOM.editor.value) + '\n';
    actualizarContadorLineas();
    actualizarCursorRápido();
    syncScroll();
}

function actualizarContadorLineas() {
    const lines = DOM.editor.value.split('\n').length;
    if (lines !== currentLineCount) {
        currentLineCount = lines;
        DOM.lineNumbers.textContent = Array.from({length: lines}, (_, i) => i + 1).join('\n');
        synchronizeScrollbarOffset();
    }
}

function procesarCambiosPesados() { currentLineCount = -1; handleInput(); }

function actualizarCursorRápido() {
    const pos = DOM.editor.selectionStart;
    const lines = DOM.editor.value.substring(0, pos).split('\n');
    DOM.cursorPos.textContent = `Ln ${lines.length}, Col ${lines[lines.length - 1].length + 1}`;
}

function synchronizeScrollbarOffset() { DOM.lineNumbers.style.paddingBottom = `${10 + DOM.editor.offsetHeight - DOM.editor.clientHeight}px`; }

function limpiarPaneles() {
    ['lexico','sintactico','semantico','intermedio','simbolos','ejecucion'].forEach(id => { const p = document.getElementById('panel_'+id); if(p) p.textContent = "Esperando..."; });
    ['lex','sin','sem'].forEach(id => { const p = document.getElementById('panel_err_'+id); if(p) p.textContent = "Sin errores."; });
}

function irALinea(linea) {
    const lineas = DOM.editor.value.split('\n');
    if (linea < 1 || linea > lineas.length) return;
    
    let pos = lineas.slice(0, linea - 1).reduce((acc, l) => acc + l.length + 1, 0);
    DOM.editor.focus();
    DOM.editor.setSelectionRange(pos, pos);
    DOM.editor.scrollTop = (linea - 1) * 21 - (DOM.editor.clientHeight / 2) + 30;
    
    const prevShadow = DOM.editorContainer.style.boxShadow;
    DOM.editorContainer.style.boxShadow = "inset 0 0 15px var(--accent-primary)";
    setTimeout(() => DOM.editorContainer.style.boxShadow = prevShadow, 300);
}

// ==========================================================================
// COMPILADOR Y GENERACIÓN DE TABLAS (JSON)
// ==========================================================================
function getLexicalClass(tipo) {
    if(tipo === 'EOF') return 'badge-default';
    if(tipo.includes('ERR')) return 'badge-error';
    switch(tipo) {
        case 'RESERVADA': return 'badge-keyword';
        case 'ID': return 'badge-id';
        case 'NUM_REAL':
        case 'NUM_ENTERO': return 'badge-number';
        case 'CADENA':
        case 'CARACTER': return 'badge-string';
        case 'COM_MULTI':
        case 'COM_SIMPLE': return 'badge-comment';
        case 'OP_RELACIONAL':
        case 'OP_LOGICO':
        case 'OP_ARITMETICO':
        case 'ASIGNACION': return 'badge-operator';
        default: return 'badge-default';
    }
}

async function compilarFase(fase) {
    document.querySelectorAll('.right-content').forEach(el => el.classList.add('oculto'));
    document.querySelectorAll('.right-panels .tab').forEach(el => el.classList.remove('active'));
    
    const panelActivo = document.getElementById('panel_' + fase);
    const tabActivo = document.querySelector(`.right-panels .tab[onclick*="${fase}"]`);
    
    if (panelActivo) panelActivo.classList.remove('oculto');
    if (tabActivo) tabActivo.classList.add('active');
    if (panelActivo && !panelActivo.querySelector('.panel-header-sticky')) panelActivo.textContent = "Procesando...";

    const formData = newSearchParams({ accion: fase, is_base64: '1', codigo_fuente: btoa(unescape(encodeURIComponent(DOM.editor.value))) });

    try {
        const resText = await (await fetch('index.php', { method: 'POST', body: formData })).text();
        
        if (fase === 'lexico') {
            try {
                const data = JSON.parse(resText);
                
                // RENDER DE LA TABLA LÉXICA
                let tablaHTML = `
                    <div class="panel-header-sticky">
                        <div>
                            <div class="panel-title-glow">=== TABLA DE TOKENS ===</div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top:2px;">Haz clic en una fila para saltar al código</div>
                        </div>
                        <button class="btn-reload" onclick="compilarFase('lexico')" title="Refrescar">🔄 Recargar</button>
                    </div>
                    <div class="table-container">
                        <table class="lexical-table">
                            <thead>
                                <tr><th>Línea</th><th>Col</th><th>Tipo</th><th>Lexema</th></tr>
                            </thead>
                            <tbody>
                `;
                
                data.tokens.forEach(tok => {
                    const cssClass = getLexicalClass(tok.tipo);
                    const safeLexema = tok.lexema.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    tablaHTML += `
                        <tr class="rgb-table-row" onclick="irALinea(${tok.linea})">
                            <td>${tok.linea}</td>
                            <td>${tok.col}</td>
                            <td><span class="badge-tipo ${cssClass}">${tok.tipo}</span></td>
                            <td class="lexema-cell">${safeLexema}</td>
                        </tr>
                    `;
                });
                
                tablaHTML += `</tbody></table></div>`;
                panelActivo.innerHTML = tablaHTML;

                // RENDER DEL PANEL DE ERRORES LÉXICOS
                const panelErrores = document.getElementById('panel_err_lex');
                if (panelErrores) {
                    if (data.errores.length === 0) {
                        panelErrores.innerHTML = '<div class="success-log">✅ Análisis léxico completado sin errores.</div>';
                    } else {
                        panelErrores.innerHTML = data.errores.map(err => {
                            const safeLex = err.lexema.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            return `<div class="interactive-log-line error-log" onclick="irALinea(${err.linea})">Ln ${err.linea} Col ${err.col} | ${err.tipo} | ${err.msg}: <b>${safeLex}</b></div>`;
                        }).join('');
                        
                        // Hacer visible el panel de errores si hubo fallos
                        document.querySelectorAll('.bottom-panels .tab').forEach(el => el.classList.remove('active'));
                        document.querySelectorAll('.bottom-content').forEach(el => el.classList.add('oculto'));
                        panelErrores.classList.remove('oculto');
                        document.querySelector(`.bottom-panels .tab[onclick*="err_lex"]`)?.classList.add('active');
                    }
                }
            } catch (jsonErr) {
                // Fallback si falla el parseo (por ejemplo si hay un warning de PHP atorado)
                panelActivo.textContent = "Error al leer el JSON del analizador léxico:\n" + resText;
            }
        } else if (panelActivo) {
            // Para las fases que aún devuelven texto plano
            panelActivo.textContent = resText;
        }
    } catch (e) { 
        if (panelActivo) panelActivo.textContent = "Fallo de conexión con el compilador."; 
    }
}

// Función auxiliar necesaria
function newSearchParams(obj) {
    const params = new URLSearchParams();
    for (const key in obj) { params.append(key, obj[key]); }
    return params;
}

function showRightPanel(e, id) {
    document.querySelectorAll('.right-content').forEach(el => el.classList.add('oculto'));
    document.querySelectorAll('.right-panels .tab').forEach(el => el.classList.remove('active'));
    document.getElementById('panel_' + id).classList.remove('oculto');
    e.target.classList.add('active');
    if (id === 'lexico') compilarFase('lexico');
}

function showBottomPanel(e, id) {
    document.querySelectorAll('.bottom-content').forEach(el => el.classList.add('oculto'));
    document.querySelectorAll('.bottom-panels .tab').forEach(el => el.classList.remove('active'));
    document.getElementById('panel_' + id).classList.remove('oculto');
    e.target.classList.add('active');
}

// ==========================================================================
// ARCHIVOS
// ==========================================================================
async function nuevoArchivo() {
    if (DOM.editor.value.trim() !== '' && !await Modals.confirm('Nuevo', '¿Crear nuevo? Se perderá lo no guardado.', 'Crear', 'Cancelar')) return;
    let nombre = await Modals.prompt("Nuevo Archivo", "Nombre del archivo:", "codigo." + currentExt);
    if (!nombre) return;
    DOM.editor.value = '';
    setFilename(nombre);
    currentOpenedFileAbsPath = DOM.workspacePath.value.trim() ? `${DOM.workspacePath.value.trim().replace(/\/$/, '')}/${nombre}` : '';
    limpiarPaneles(); procesarCambiosPesados();
    await guardarEnServidor(nombre, false);
    cargarExplorador();
}

async function cerrarArchivo() {
    if (DOM.editor.value.trim() !== '' && !await Modals.confirm('Cerrar', '¿Cerrar sin guardar?', 'Cerrar', 'Cancelar')) return;
    DOM.editor.value = '';
    setFilename('Sin título');
    currentOpenedFileAbsPath = '';
    limpiarPaneles(); procesarCambiosPesados();
}

async function guardarEnServidor(nombreArchivo, esGuardarComo = false) {
    if (saveInProgress) return;
    saveInProgress = true;
    const formData = new URLSearchParams({
        accion: 'guardar_servidor', nombre_archivo: nombreArchivo, is_base64: '1',
        codigo_fuente: btoa(unescape(encodeURIComponent(DOM.editor.value)))
    });
    if (currentOpenedFileAbsPath && !esGuardarComo) formData.append('ruta_absoluta', currentOpenedFileAbsPath);

    try {
        const res = await (await fetch('index.php', { method: 'POST', body: formData })).text();
        if (res.startsWith('SUCCESS')) {
            mostrarNotificacion(esGuardarComo ? '🌐 Guardado como' : '✅ Guardado');
            cargarExplorador();
        } else throw new Error(res);
    } catch (e) { mostrarNotificacion("Fallo al guardar", true); }
    finally { saveInProgress = false; }
}

async function guardarArchivo() {
    const name = DOM.currentFilename.value;
    (name === 'Sin título' || !name) ? guardarComoArchivo() : guardarEnServidor(name, false);
}

async function guardarComoArchivo() {
    let nombre = await Modals.prompt("Guardar Como", "Nombre:", DOM.currentFilename.value);
    if (!nombre) return;
    setFilename(nombre);
    currentOpenedFileAbsPath = DOM.workspacePath.value.trim() ? `${DOM.workspacePath.value.trim().replace(/\/$/, '')}/${nombre}` : '';
    guardarEnServidor(nombre, true);
}

function abrirArchivo() {
    const input = document.createElement('input');
    input.type = 'file';
    input.onchange = e => {
        const file = e.target.files[0];
        setFilename(file.name);
        const reader = new FileReader();
        reader.onload = re => { DOM.editor.value = re.target.result; currentOpenedFileAbsPath = ''; procesarCambiosPesados(); };
        reader.readAsText(file, 'UTF-8');
    };
    input.click();
}

async function borrarArchivo(event, rutaAbsoluta, nombre) {
    if (event) event.stopPropagation();
    if (!await Modals.confirm('Eliminar Archivo', `¿Eliminar permanentemente <b>${nombre}</b>?<br><br>No se puede deshacer.`, 'Eliminar', 'Cancelar')) return;

    try {
        const res = await (await fetch('index.php', { method: 'POST', body: new URLSearchParams({ accion: 'borrar_archivo', ruta_absoluta: rutaAbsoluta }) })).text();
        if (res.startsWith('SUCCESS')) {
            mostrarNotificacion('🗑️ Archivo eliminado');
            if (currentOpenedFileAbsPath === rutaAbsoluta) {
                DOM.editor.value = ''; setFilename('Sin título'); currentOpenedFileAbsPath = '';
                limpiarPaneles(); procesarCambiosPesados();
            }
            cargarExplorador();
        } else throw new Error();
    } catch (e) { mostrarNotificacion("Error al eliminar", true); }
}

// ==========================================================================
// UTILS
// ==========================================================================
function mostrarNotificacion(msg, esError = false) {
    DOM.statusMsg.textContent = `${esError ? '❌' : ''} ${msg} - ${new Date().toLocaleTimeString()}`;
    DOM.statusMsg.style.color = esError ? "#f92672" : "var(--text-primary)";
    DOM.statusMsg.style.fontWeight = esError ? "normal" : "bold";
    setTimeout(() => { DOM.statusMsg.style.fontWeight = "normal"; DOM.statusMsg.style.color = "var(--text-primary)"; DOM.statusMsg.textContent = "Sistema listo."; }, 3000);
}

function verLogErrores() { fetch('index.php?accion=ver_log').then(r => r.text()).then(l => Modals.alert("LOG", l)); }
function salirIDE() { window.location.href = "about:blank"; }

function toggleTheme() {
    document.body.classList.remove('rgb-theme');
    document.body.classList.toggle('light-theme');
    localStorage.setItem('ide_theme', document.body.classList.contains('light-theme') ? 'light' : 'dark');
}

function toggleRGB() {
    document.body.classList.remove('light-theme');
    document.body.classList.toggle('rgb-theme');
    localStorage.setItem('ide_theme', document.body.classList.contains('rgb-theme') ? 'rgb' : 'dark');
}

function abrirAutomata() { window.open('compilador/automata.php', '_blank', 'width=1100,height=800'); }

document.addEventListener('DOMContentLoaded', () => {
    const theme = localStorage.getItem('ide_theme');
    if (theme === 'light') document.body.classList.add('light-theme');
    else if (theme === 'rgb') document.body.classList.add('rgb-theme');
    
    setFilename(DOM.currentFilename.value);
    procesarCambiosPesados();
    cargarExplorador();
});