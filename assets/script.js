const editor = document.getElementById('editor');
const highlighting = document.getElementById('highlighting');
const lineNumbers = document.getElementById('line_numbers');
const cursorPos = document.getElementById('cursor_pos');

let currentExt = document.getElementById('file_extension').value;
let currentOpenedFileAbsPath = '';
let fileHandle = null;
let currentLineCount = 0;
let typingTimer;
let saveInProgress = false;

const resizerV = document.getElementById('resizer_v');
const resizerExp = document.getElementById('resizer_explorer');
const resizerH = document.getElementById('resizer_h');
const editorSection = document.getElementById('editor_container');
const fileExplorer = document.getElementById('file_explorer');
const topLayout = document.querySelector('.top-layout');

let isResizingV = false;
let isResizingExp = false;
let isResizingH = false;
let animationFrameId = null;

// ==========================================================================
// SISTEMA DE MODALES
// ==========================================================================
const Modals = {
    show: function(titulo, htmlContent, buttons) {
        return new Promise((resolve) => {
            const existing = document.getElementById('ide_modal_overlay');
            if (existing) existing.remove();

            const overlay = document.createElement('div');
            overlay.id = 'ide_modal_overlay';
            overlay.style.cssText = [
                'position: fixed; top: 0; left: 0; width: 100%; height: 100%;',
                'background: rgba(0,0,0,0.6); z-index: 9999; display: flex;',
                'justify-content: center; align-items: center;'
            ].join(' ');
            
            const box = document.createElement('div');
            box.style.cssText = [
                'background: var(--bg-secondary); border: 1px solid var(--border-color);',
                'border-radius: 6px; padding: 20px; min-width: 350px; max-width: 500px;',
                'box-shadow: 0 5px 15px var(--shadow-color); font-family: var(--font-sans);'
            ].join(' ');
            
            let btnsHtml = buttons.map((b, i) => {
                const bg = b.primary ? 'var(--accent-primary)' : 'var(--bg-tertiary)';
                const fw = b.primary ? 'bold' : 'normal';
                return [
                    '<button id="mbtn_' + i + '" style="',
                    'padding: 8px 15px; cursor: pointer; background: ' + bg + ';',
                    'color: #fff; border: 1px solid var(--border-color); border-radius: 4px;',
                    'margin-left: 10px; font-weight: ' + fw + '; transition: background 0.2s;">',
                    b.text,
                    '</button>'
                ].join('');
            }).join('');

            box.innerHTML = [
                '<h3 style="margin-top: 0; color: var(--text-primary); margin-bottom: 15px;">', titulo, '</h3>',
                '<div style="color: var(--text-primary); margin-bottom: 20px; font-size: 14px; max-height: 60vh; overflow-y: auto;">',
                htmlContent, '</div>',
                '<div style="display: flex; justify-content: flex-end;">', btnsHtml, '</div>'
            ].join('');
            
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            buttons.forEach((b, i) => {
                const btn = document.getElementById('mbtn_' + i);
                btn.addEventListener('click', () => {
                    overlay.style.display = 'none';
                    resolve(b.value);
                    setTimeout(() => overlay.remove(), 50);
                });
            });
        });
    },
    prompt: async function(titulo, mensaje, valorPorDefecto = '') {
        const html = [
            '<p style="margin-bottom: 10px;">' + mensaje + '</p>',
            '<input type="text" id="modal_input" value="' + valorPorDefecto + '" style="',
            'width: 100%; padding: 8px; background: var(--bg-primary); color: var(--text-primary);',
            'border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: var(--font-mono);">'
        ].join('');
                     
        setTimeout(() => {
            const input = document.getElementById('modal_input');
            if(input) {
                input.focus();
                const dotIndex = input.value.lastIndexOf('.');
                if (dotIndex > 0) input.setSelectionRange(0, dotIndex);
                else input.select();
                input.addEventListener('keydown', (e) => {
                    if(e.key === 'Enter') document.getElementById('mbtn_1').click();
                });
            }
        }, 50);

        const res = await this.show(titulo, html, [
            {text: 'Cancelar', value: null},
            {text: 'Aceptar', value: 'OK', primary: true}
        ]);
        return res === 'OK' ? document.getElementById('modal_input').value : null;
    },
    confirm: async function(tit, msg, btnSi = 'Sí', btnNo = 'No') {
        return await this.show(tit, '<p>' + msg + '</p>', [
            {text: btnNo, value: false}, {text: btnSi, value: true, primary: true}
        ]);
    },
    alert: async function(tit, msg) {
        const html = '<pre style="white-space: pre-wrap; font-family: var(--font-mono); margin: 0;">' + msg + '</pre>';
        await this.show(tit, html, [{text: 'Aceptar', value: true, primary: true}]);
    }
};

// ==========================================================================
// RESIZERS
// ==========================================================================
resizerV.addEventListener('mousedown', () => { isResizingV = true; document.body.style.cursor = 'col-resize'; editor.style.pointerEvents = 'none'; });
resizerExp.addEventListener('mousedown', () => { isResizingExp = true; document.body.style.cursor = 'col-resize'; editor.style.pointerEvents = 'none'; });
resizerH.addEventListener('mousedown', () => { isResizingH = true; document.body.style.cursor = 'row-resize'; editor.style.pointerEvents = 'none'; });

document.addEventListener('mousemove', (e) => {
    if (!isResizingV && !isResizingH && !isResizingExp) return;
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    
    animationFrameId = requestAnimationFrame(() => {
        if (isResizingV) {
            let width = document.body.clientWidth - e.clientX;
            if (width > 200 && width < window.innerWidth - 200) document.getElementById('right_panels').style.width = width + 'px';
        }
        if (isResizingExp) {
            let width = e.clientX;
            if (width > 150 && width < window.innerWidth / 2) fileExplorer.style.width = width + 'px';
        }
        if (isResizingH) {
            let newHeight = e.clientY - document.querySelector('.main-layout').getBoundingClientRect().top;
            if (newHeight > 100 && newHeight < window.innerHeight - 100) topLayout.style.flex = '0 0 ' + newHeight + 'px';
        }
    });
});

document.addEventListener('mouseup', () => {
    isResizingV = isResizingH = isResizingExp = false;
    document.body.style.cursor = 'default';
    editor.style.pointerEvents = 'auto';
});

window.addEventListener('resize', synchronizeScrollbarOffset);

// ==========================================================================
// ATAJOS Y EXPLORADOR
// ==========================================================================
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); guardarArchivo(); }
});

async function cargarExplorador(rutaSolicitada = null) {
    const content = document.getElementById('explorer_content');
    const inputPath = document.getElementById('workspace_path');
    let ruta = rutaSolicitada === null ? inputPath.value.trim() : rutaSolicitada;
    
    const formData = new URLSearchParams();
    formData.append('accion', 'explorar_directorio');
    if (ruta !== '') formData.append('ruta', ruta);

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const res = await response.json();
        if (res.success) {
            inputPath.value = res.ruta_actual;
            let html = '';
            res.elementos.forEach(el => {
                const rEsc = el.ruta.replace(/\\/g, '\\\\');
                const mName = el.nombre.replace(/'/g, "\\'");
                if (el.es_directorio) {
                    html += ['<div class="explorer-item folder" onclick="cargarExplorador(\'', rEsc, '\')">', el.nombre, '</div>'].join('');
                } else {
                    html += ['<div class="explorer-item file" onclick="abrirDesdeExplorador(\'', rEsc, '\', \'', mName, '\')">', el.nombre, '</div>'].join('');
                }
            });
            content.innerHTML = html || '<div style="padding: 10px; color: var(--text-muted);">Carpeta vacía</div>';
        }
    } catch (e) { console.error("Error explorador:", e); }
}

function subirDirectorio() {
    const rutaActual = document.getElementById('workspace_path').value;
    if (rutaActual.trim() !== '') {
        let partes = rutaActual.split(/[\\/]/);
        partes.pop();
        cargarExplorador(partes.length <= 1 ? '/' : partes.join('/'));
    }
}

async function abrirDesdeExplorador(rutaAbsoluta, nombre) {
    try {
        const response = await fetch('index.php?accion=cargar_archivo&ruta_absoluta=' + encodeURIComponent(rutaAbsoluta));
        const data = await response.json();
        if (data.success) {
            editor.value = decodeURIComponent(escape(atob(data.contenido)));
            setFilename(data.nombre);
            currentOpenedFileAbsPath = data.ruta_absoluta;
            fileHandle = null;
            procesarCambiosPesados();
            limpiarPaneles();
            mostrarNotificacionGuardado('📂 Abierto');
        }
    } catch (e) { mostrarError("Error al abrir"); }
}

function toggleExplorer() {
    fileExplorer.classList.toggle('panel-hidden');
    resizerExp.classList.toggle('panel-hidden');
}

// ==========================================================================
// RESALTADO DE SINTAXIS
// ==========================================================================
function applySyntaxHighlighting(text) {
    let html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    let regex;
    const kw_c = ['int','float','void','if','else','while','for','return','printf','scanf','char','double','struct','include'].join('|');
    const kw_web = ['if','else','while','for','return','echo','public','private','protected','function','class','require','include','isset','empty','let','const','var','console','document','window','await','async','new','true','false','null','switch','case','break','default'].join('|');

    if (currentExt === 'c' || currentExt === 'cpp') {
        regex = new RegExp(["(\\/\\*[\\s\\S]*?\\*\\/|\\/\\/.*)", "(\"[^\"]*\"|'[^']*')", "\\b(" + kw_c + ")\\b", "\\b\\d+(\\.\\d+)?\\b"].join('|'), 'g');
    } else if (currentExt === 'php' || currentExt === 'html' || currentExt === 'js') {
        regex = new RegExp(["(\\/\\*[\\s\\S]*?\\*\\/|\\/\\/.*|&lt;!--[\\s\\S]*?--&gt;)", "(\"[^\"]*\"|'[^']*'|`[^`]*`)", "(&lt;\\?php|&lt;\\?=|\\?&gt;)", "(&lt;\\/?[a-zA-Z0-9\\-]+|&gt;)", "(\\$[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)", "\\b(" + kw_web + ")\\b", "\\b\\d+(\\.\\d+)?\\b"].join('|'), 'g');
    } else {
        regex = new RegExp(["(\\/\\*[\\s\\S]*?\\*\\/|\\/\\/.*)", "(\"[^\"]*\"|'[^']*')", "\\b(if|else|while|for|return)\\b", "\\b\\d+(\\.\\d+)?\\b"].join('|'), 'g');
    }

    return html.replace(regex, function(m) {
        if (m.startsWith('/*') || m.startsWith('//') || m.startsWith('&lt;!--')) return '<span class="syntax-comment">' + m + '</span>';
        if (m.startsWith('"') || m.startsWith("'") || m.startsWith('`')) return '<span class="syntax-string">' + m + '</span>';
        if (m.startsWith('&lt;?') || m.startsWith('?&gt;')) return '<span class="syntax-keyword-alt">' + m + '</span>';
        if (m.startsWith('&lt;') || m.startsWith('&gt;')) return '<span class="syntax-html">' + m + '</span>';
        if (m.startsWith('$')) return '<span class="syntax-var">' + m + '</span>';
        if (/^\d+(\.\d+)?$/.test(m)) return '<span class="syntax-number">' + m + '</span>';
        return '<span class="syntax-keyword">' + m + '</span>';
    });
}

// ==========================================================================
// EDITOR CORE
// ==========================================================================
function setFilename(filename) {
    document.getElementById('current_filename').value = filename;
    document.getElementById('file_indicator').textContent = 'Archivo: ' + filename;
    setExtension(filename);
}

function setExtension(filename) {
    currentExt = filename.includes('.') ? filename.split('.').pop().toLowerCase() : 'txt';
    document.getElementById('file_extension').value = currentExt;
    procesarCambiosPesados();
}

function syncScroll() {
    lineNumbers.scrollTop = editor.scrollTop;
    highlighting.scrollTop = editor.scrollTop;
    highlighting.scrollLeft = editor.scrollLeft;
}
editor.addEventListener('scroll', syncScroll);

function handleInput() {
    const text = editor.value;
    highlighting.innerHTML = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '\n';
    
    actualizarContadorLineas();
    actualizarCursorRápido();
    syncScroll();

    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => { 
        highlighting.innerHTML = applySyntaxHighlighting(text) + '\n'; 
    }, 150);
}

function actualizarContadorLineas() {
    const lines = editor.value.split('\n').length;
    if (lines !== currentLineCount) {
        currentLineCount = lines;
        lineNumbers.textContent = Array.from({length: lines}, (_, i) => i + 1).join('\n');
        synchronizeScrollbarOffset();
    }
}

function procesarCambiosPesados() {
    currentLineCount = -1;
    handleInput();
}

function actualizarCursorRápido() {
    const pos = editor.selectionStart;
    const lines = editor.value.substring(0, pos).split('\n');
    cursorPos.textContent = 'Ln ' + lines.length + ', Col ' + (lines[lines.length - 1].length + 1);
}

function synchronizeScrollbarOffset() {
    lineNumbers.style.paddingBottom = (10 + editor.offsetHeight - editor.clientHeight) + 'px';
}

function limpiarPaneles() {
    ['lexico','sintactico','semantico','intermedio','simbolos','ejecucion'].forEach(id => {
        const p = document.getElementById('panel_'+id);
        if(p) p.textContent = "Esperando...";
    });
    ['lex','sin','sem'].forEach(id => {
        const p = document.getElementById('panel_err_'+id);
        if(p) p.textContent = "Sin errores.";
    });
}

function encodeToB64(str) { return btoa(unescape(encodeURIComponent(str))); }

function compilarFase(fase) {
    document.getElementById('hidden_code').value = encodeToB64(editor.value);
    document.getElementById('is_base64').value = '1';
    let form = document.getElementById('formCompilar');
    document.getElementById('temp_accion').value = fase;
    form.submit();
}

function showRightPanel(e, id) {
    document.querySelectorAll('.right-content').forEach(el => el.classList.add('oculto'));
    document.querySelectorAll('.right-panels .tab').forEach(el => el.classList.remove('active'));
    document.getElementById('panel_' + id).classList.remove('oculto');
    e.target.classList.add('active');
}

function showBottomPanel(e, id) {
    document.querySelectorAll('.bottom-content').forEach(el => el.classList.add('oculto'));
    document.querySelectorAll('.bottom-panels .tab').forEach(el => el.classList.remove('active'));
    document.getElementById('panel_' + id).classList.remove('oculto');
    e.target.classList.add('active');
}

function toggleRightPanel() {
    document.getElementById('right_panels').classList.toggle('panel-hidden');
    resizerV.classList.toggle('panel-hidden');
}

function toggleBottomPanel() {
    document.getElementById('bottom_panels').classList.toggle('panel-hidden');
    resizerH.classList.toggle('panel-hidden');
}

// ==========================================================================
// NUEVO, CERRAR Y GUARDAR
// ==========================================================================
async function nuevoArchivo() {
    if (editor.value.trim() !== '') {
        if (!await Modals.confirm('Nuevo', '¿Crear nuevo? Se perderá lo no guardado.', 'Crear', 'Cancelar')) return;
    }
    let nombre = await Modals.prompt("Nuevo Archivo", "Nombre del archivo:", "codigo." + currentExt);
    if (!nombre) return;
    editor.value = '';
    setFilename(nombre);
    const rutaBase = document.getElementById('workspace_path').value.trim();
    currentOpenedFileAbsPath = rutaBase ? (rutaBase.replace(/\/$/, '') + '/' + nombre) : '';
    limpiarPaneles();
    procesarCambiosPesados();
    await guardarEnServidor(nombre, false);
    cargarExplorador();
}

async function cerrarArchivo() {
    if (editor.value.trim() !== '' && !await Modals.confirm('Cerrar', '¿Cerrar sin guardar?', 'Cerrar', 'Cancelar')) return;
    editor.value = '';
    setFilename('Sin título');
    currentOpenedFileAbsPath = '';
    limpiarPaneles();
    procesarCambiosPesados();
}

async function guardarEnServidor(nombreArchivo, esGuardarComo = false) {
    if (saveInProgress) return;
    saveInProgress = true;
    const formData = new URLSearchParams();
    formData.append('accion', 'guardar_servidor');
    formData.append('nombre_archivo', nombreArchivo);
    formData.append('codigo_fuente', encodeToB64(editor.value));
    formData.append('is_base64', '1');
    if (currentOpenedFileAbsPath !== '' && !esGuardarComo) formData.append('ruta_absoluta', currentOpenedFileAbsPath);

    try {
        const response = await fetch('index.php', { method: 'POST', body: formData });
        const res = await response.text();
        if (res.startsWith('SUCCESS')) {
            mostrarNotificacionGuardado(esGuardarComo ? '🌐 Guardado como' : '✅ Guardado');
            cargarExplorador();
        }
    } catch (e) { mostrarError("Fallo al guardar"); }
    finally { saveInProgress = false; }
}

async function guardarArchivo() {
    let name = document.getElementById('current_filename').value;
    if (name === 'Sin título' || name === '') return guardarComoArchivo();
    guardarEnServidor(name, false);
}

async function guardarComoArchivo() {
    let nombre = await Modals.prompt("Guardar Como", "Nombre:", document.getElementById('current_filename').value);
    if (!nombre) return;
    setFilename(nombre);
    const rutaBase = document.getElementById('workspace_path').value.trim();
    currentOpenedFileAbsPath = rutaBase ? (rutaBase.replace(/\/$/, '') + '/' + nombre) : '';
    guardarEnServidor(nombre, true);
}

async function abrirArchivo() {
    const input = document.createElement('input');
    input.type = 'file';
    input.onchange = e => {
        const file = e.target.files[0];
        setFilename(file.name);
        const reader = new FileReader();
        reader.readAsText(file, 'UTF-8');
        reader.onload = re => { 
            editor.value = re.target.result; 
            currentOpenedFileAbsPath = ''; 
            procesarCambiosPesados(); 
        }
    };
    input.click();
}

function mostrarNotificacionGuardado(msg) {
    const s = document.getElementById('status_msg');
    s.textContent = msg + ' - ' + new Date().toLocaleTimeString();
    s.style.fontWeight = "bold";
    setTimeout(() => { s.style.fontWeight = "normal"; s.textContent = "Sistema listo."; }, 3000);
}

function mostrarError(msg) {
    const s = document.getElementById('status_msg');
    s.textContent = '❌ ' + msg;
    s.style.color = "#f92672";
    setTimeout(() => { s.style.color = "var(--text-primary)"; s.textContent = "Sistema listo."; }, 4000);
}

function verLogErrores() { fetch('index.php?accion=ver_log').then(r => r.text()).then(l => Modals.alert("LOG", l)); }
function salirIDE() { window.location.href = "about:blank"; }
function toggleTheme() {
    document.body.classList.toggle('light-theme');
    localStorage.setItem('ide_theme', document.body.classList.contains('light-theme') ? 'light' : 'dark');
}

function abrirAutomata() {
    // Lo abrimos en pestaña nueva para que puedas hacer F11 y tomar la foto limpia.
    window.open('compilador/automata.php', '_blank', 'width=1100,height=800');
}

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('ide_theme') === 'light') document.body.classList.add('light-theme');
    setFilename(document.getElementById('current_filename').value);
    procesarCambiosPesados();
    cargarExplorador();
});
