<div class="ai-panel oculto" id="ai_panel">
    <div class="ai-panel-header">
        <div class="ai-header-title">
            <span><i data-lucide="bot"></i> Asistente de IA</span>
        </div>
        <div class="ai-header-controls">
            <select id="ai_provider_selector" class="ai-select" onchange="cambiarProveedorIA(this.value)">
                <option value="gemini" selected>Google Gemini 1.5</option>
                <option value="grok" style="display:none;">xAI Grok 2</option>
                <option value="mercury">Mercury 2</option>
            </select>
            <button class="ai-close-btn" onclick="toggleAIPanel()" title="Cerrar Panel"><i data-lucide="x"></i></button>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="ai-quick-actions">
        <button class="ai-action-btn" onclick="ejecutarAccionIA('explicar')" title="Explicar el código seleccionado o completo">
            <i data-lucide="lightbulb"></i> Explicar
        </button>
        <button class="ai-action-btn" onclick="insertarEnEditor()" title="Insertar en editor de código">
            <i data-lucide="code"></i> Insertar
        </button>
        <button class="ai-action-btn" onclick="auditarGemini()" title="Auditar todos los modelos de Google Gemini" style="display:none;">
            <i data-lucide="search"></i> Auditar Gemini
        </button>
        <button class="ai-action-btn" onclick="ejecutarAccionIA('optimizar')" title="Optimizar rendimiento/legibilidad">
            <i data-lucide="zap"></i> Optimizar
        </button>
    </div>

    <!-- Área de Chat -->
    <div class="ai-chat-body" id="ai_chat_body">
        <div class="ai-message ai-message-assistant">
            <div class="ai-message-avatar"><i data-lucide="bot"></i></div>
            <div class="ai-message-content">
                ¡Hola! Soy tu asistente de IA. Puedo ayudarte a explicar código, resolver errores de compilación o sugerir mejoras. 
                <br><br>
                Prueba seleccionando un bloque de código y haciendo clic en <strong><i data-lucide="lightbulb"></i> Explicar</strong>, o escríbeme una pregunta abajo.
            </div>
        </div>
    </div>

    <!-- Cargando / Estado -->
    <div class="ai-status oculto" id="ai_status">
        <div class="ai-spinner"></div>
        <span id="ai_status_text">Generando respuesta...</span>
    </div>

    <!-- Input de Chat -->
    <div class="ai-input-area">
        <textarea id="ai_chat_input" placeholder="Pregúntame algo sobre tu código... (Ctrl+Enter para enviar)" onkeydown="if(event.key === 'Enter' && event.ctrlKey) enviarMensajeIA()"></textarea>
        <button class="ai-send-btn" onclick="enviarMensajeIA()" title="Enviar mensaje">
            <i data-lucide="send"></i>
        </button>
    </div>
</div>
