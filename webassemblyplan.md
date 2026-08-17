# 🚀 Plan de Migración del IDE Indómito a WebAssembly (Wasm) y Arquitectura 100% Client-Side

Este documento describe la hoja de ruta, arquitectura y estrategia técnica para migrar el **IDE Indómito** desde un backend monolítico en **PHP 8.4** hacia una aplicación web estática de alto rendimiento impulsada por **WebAssembly (Wasm)** y **JavaScript moderno**. 

El objetivo principal es **eliminar por completo la dependencia del servidor PHP y dependencias del sistema (como `php-curl`)**, permitiendo que el proyecto se ejecute directamente en el navegador del cliente y pueda alojarse de forma 100% gratuita y portable en **GitHub Pages**, Vercel, Netlify o cualquier CDN estático.

---

## 🔍 1. Diagnóstico del Error Actual (`curl_init`)

### Causa Raíz
```text
Uncaught Error: Call to undefined function App\AI\Providers\curl_init()
```
El servidor PHP en ejecución en Linux (Devuan/Debian) no tiene cargada la extensión `php-curl` en su `php.ini`. Por tanto, cualquier llamada `curl_init()` dentro de `GeminiProvider.php` o `MercuryProvider.php` produce un error fatal 500.

### Solución Inmediata (Entorno Local PHP)
Si se desea seguir probando en PHP temporalmente:
```bash
sudo apt update && sudo apt install php-curl php8.4-curl
```

---

## 🎯 2. Visión y Objetivos de la Migración

| Característica | Estado Actual (PHP) | Estado Futuro (Wasm / Static SPA) |
| :--- | :--- | :--- |
| **Infraestructura** | Requiere PHP 8.4 CLI / Apache / Nginx | Cero servidores; 100% archivos estáticos (HTML/CSS/JS/WASM) |
| **Alojamiento** | Requiere VPS o Hosting con PHP | **GitHub Pages**, Cloudflare Pages o Git Clone con apertura directa |
| **Pipeline Compilador** | Scripts PHP ejecutados vía `shell_exec()` | Módulos compilados en **WebAssembly (WASM)** ejecutados en sub-milisegundos en el hilo o Web Worker |
| **Manejo de Archivos** | `FileController.php` (escritura en carpeta local) | **File System Access API** (acceso a archivos reales del disco) + **IndexedDB** (workspace virtual en navegador) |
| **Llamadas a IA** | Proxy cURL en PHP | `fetch()` directo desde el navegador a la API de Gemini/Groq/Mercury con llaves en `localStorage` |
| **Visualizador Autómata** | `automata.php` | `automata.html` independiente con Vis-Network |

---

## 🏗️ 3. Arquitectura del Sistema Propuesto

```mermaid
graph TD
    subgraph Navegador / Cliente (GitHub Pages)
        UI[Frontend UI: Editor, Tabs, Lucide, D3.js]
        
        subgraph Almacenamiento
            FSA[File System Access API / IndexedDB Workspace]
        end
        
        subgraph Motor del Compilador
            WASM[Módulo WebAssembly: Léxico, Sintáctico AST, Semántico, 3AC]
            WORKER[Web Worker: Ejecución en segundo plano sin congelar UI]
        end
        
        subgraph Módulo IA
            AIFETCH[Client-side Fetch: Gemini / Grok / Mercury / WebLLM]
        end
        
        subgraph Visualización
            VIS[Vis-Network Autómata + D3.js Árbol Sintáctico]
        end
    end

    UI --> FSA
    UI --> WORKER
    WORKER --> WASM
    WASM --> VIS
    UI --> AIFETCH
    AIFETCH -->|HTTPS Directo| API_GEMINI[Google Gemini API]
    AIFETCH -->|HTTPS Directo| API_INCEPTION[Mercury / Grok API]
```

---

## ⚙️ 4. Estrategia de Migración por Componente

### 4.1. Pipeline del Compilador (Léxico, Sintáctico, Semántico, Símbolos, 3AC)

Actualmente, las fases del compilador están escritas en PHP procedimental y orientado a objetos (`lexico.php`, `sintactico.php`, `semantico.php`, `simbolos.php`, `intermedio.php`).

Existen 3 opciones para la migración a WebAssembly:

#### 🥇 Opción A: Compilador Nativo en Rust compilado a Wasm (Recomendada)
* **Herramientas:** `Rust` + `wasm-bindgen` + `wasm-pack`.
* **Ventajas:**
  * Rendimiento extremo y uso de memoria casi nulo.
  * Soporte nativo para árboles de sintaxis abstracta (AST) usando `enum` y `match`.
  * Generación de bindings automáticos de TypeScript/JavaScript.
  * El bundle final `.wasm` pesa menos de **150 KB**.
* **Estructura Rust sugerida:**
  ```rust
  // src/lib.rs
  use wasm_bindgen::prelude::*;
  
  #[wasm_bindgen]
  pub fn analizar_lexico(codigo: &str) -> JsValue {
      let tokens = Lexer::new(codigo).tokenize();
      serde_wasm_bindgen::to_value(&tokens).unwrap()
  }

  #[wasm_bindgen]
  pub fn analizar_sintactico(codigo: &str) -> JsValue {
      let ast = Parser::new(codigo).parse();
      serde_wasm_bindgen::to_value(&ast).unwrap()
  }
  ```

#### 🥈 Opción B: Compilador en C / C++ con Emscripten
* **Herramientas:** `clang` / `emcc` (Emscripten).
* **Ventajas:** Si ya se tienen analizadores basados en Flex / Bison o código C puro.
* **Salida:** Archivos `compilador.js` y `compilador.wasm`.

#### 🥉 Opción C: PHP en WebAssembly (`@php-wasm/web`)
* **Herramientas:** WordPress Playground / `@php-wasm/web`.
* **Ventajas:** **Cero refactorización inicial**. Se cargan los mismos archivos `.php` actuales y se ejecutan dentro de una máquina virtual PHP compilada en Wasm en el navegador.
* **Desventajas:** El runtime de PHP Wasm pesa entre 5 MB y 10 MB al descargar por primera vez.

---

### 4.2. Sistema de Archivos y Workspace

Eliminar `FileController.php` reemplazándolo por APIs nativas del navegador:

1. **File System Access API (`window.showOpenFilePicker` / `window.showSaveFilePicker`):**
   * Permite al usuario abrir y guardar directamente en su disco duro local como un IDE de escritorio real (VS Code Web).
2. **IndexedDB (Virtual Workspace):**
   * Estructura de árbol de directorios guardada en la base de datos local del navegador.
   * Persiste carpetas, proyectos y archivos aunque el usuario recargue o cierre el navegador.
3. **Exportación / Importación ZIP (con JSZip):**
   * Botón para descargar el workspace completo como `.zip` y cargarlo con Drag & Drop.

---

### 4.3. Asistente de Inteligencia Artificial (Gemini / Grok / Mercury)

Eliminar `AIController.php` y las llamadas cURL del servidor:

1. **Llamadas Directas con `fetch()`:**
   * La API de Google Gemini (`https://generativelanguage.googleapis.com/v1beta/models/...`) y las APIs compatibles con OpenAI/InceptionLabs soportan CORS y pueden ser llamadas directamente desde el navegador del cliente.
2. **Gestión Segura de API Keys del Usuario:**
   * Modal de Configuración en la interfaz ("⚙️ Ajustes de API").
   * Las claves se guardan en el `localStorage` o `sessionStorage` del navegador del usuario.
   * **Cero riesgo de filtración de claves en el repositorio de Git**.

```javascript
// assets/js/ai_client.js
async function consultarGeminiDirecto(prompt, apiKey, model = 'gemini-1.5-flash') {
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`;
    const payload = {
        contents: [{ role: "user", parts: [{ text: prompt }] }]
    };

    const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    return data.candidates[0].content.parts[0].text;
}
```

---

### 4.4. Vistas y Componentes UI

Convertir las vistas PHP en una Single Page Application (SPA) en HTML5 puro:

1. `vistas/vista.php` ➔ `index.html`
2. `vistas/componentes/panel_ia.php` ➔ Componente HTML integrado en `index.html`
3. `src/Compiler/automata.php` ➔ `automata.html` (o modal incrustado)

---

## 🗺️ 5. Plan de Ejecución por Fases

```
┌───────────────────────────────────────────────────────────────────────────────────┐
│                              CRONOGRAMA DE MIGRACIÓN                              │
├─────────────────┬─────────────────────────────────────────────────┬───────────────┤
│ Fase            │ Tareas Clave                                    │ Duración Est. │
├─────────────────┼─────────────────────────────────────────────────┼───────────────┤
│ 1. Frontend SPA │ Convertir index.php y vista.php a index.html    │ Día 1         │
│                 │ Desacoplar panel IA a fetch() y localStorage    │               │
├─────────────────┼─────────────────────────────────────────────────┼───────────────┤
│ 2. Filesystem   │ Implementar File System Access API + IndexedDB  │ Día 2         │
│                 │ Reemplazar FileController.php en el explorador  │               │
├─────────────────┼─────────────────────────────────────────────────┼───────────────┤
│ 3. Core Wasm    │ Implementar Léxico y Sintáctico en Rust/Wasm    │ Días 3 - 5    │
│                 │ Conectar salida JSON del Wasm con D3.js y Tablas│               │
├─────────────────┼─────────────────────────────────────────────────┼───────────────┤
│ 4. Fases Extras │ Semántico, Símbolos, 3AC y Máquina Virtual Wasm │ Días 6 - 7    │
│                 │ Web Worker para análisis en tiempo real         │               │
├─────────────────┼─────────────────────────────────────────────────┼───────────────┤
│ 5. Despliegue   │ GitHub Actions para compilar Wasm + Pages       │ Día 8         │
└─────────────────┴─────────────────────────────────────────────────┴───────────────┘
```

### Detalle de las Fases:

### Fase 1: Desacople de Vistas e Inteligencia Artificial
* [ ] Crear `index.html` fusionando `vistas/vista.php` y sus componentes.
* [ ] Migrar `assets/js/ai.js` para usar `fetch()` directo a las APIs de Gemini y Mercury.
* [ ] Crear un modal para configurar y guardar llaves de API en `localStorage`.
* [ ] Convertir `automata.php` en `automata.html`.

### Fase 2: Explorador de Archivos Client-Side
* [ ] Implementar adaptador de almacenamiento con IndexedDB.
* [ ] Integrar botones "Abrir Carpeta Local" y "Guardar Archivo" usando `showOpenFilePicker()` y `showSaveFilePicker()`.
* [ ] Añadir soporte para exportar/importar proyectos en `.zip`.

### Fase 3: Núcleo del Compilador en WebAssembly
* [ ] Crear proyecto Rust con `wasm-pack` en `wasm-compiler/`.
* [ ] Portar el tokenizador de expresiones regulares de `lexico.php` a Rust.
* [ ] Portar el Parser de Descenso Recursivo con modo pánico de `sintactico.php` a Rust.
* [ ] Exportar funciones `analizar_lexico()` y `analizar_sintactico()` con salida JSON idéntica a la actual para mantener total compatibilidad con las tablas y el árbol D3.js.

### Fase 4: Semántico, Símbolos y Código Intermedio
* [ ] Portar validación de tipos y declaraciones de `semantico.php`.
* [ ] Portar recolección de símbolos de `simbolos.php`.
* [ ] Portar generador de tres direcciones (3AC) de `intermedio.php`.
* [ ] Empaquetar la ejecución en un **Web Worker** para que el compilador analice código en tiempo real conforme el usuario escribe sin bloquear la interfaz gráfica.

### Fase 5: CI/CD y Despliegue en GitHub Pages
* [ ] Crear `.github/workflows/deploy.yml` para compilar el código Rust a Wasm automáticamente en cada `git push`.
* [ ] Publicar el sitio resultante en GitHub Pages con HTTPS activo.

---

## 📁 6. Nueva Estructura de Directorios Propuesta

```text
IDE/
├── .github/
│   └── workflows/
│       └── deploy.yml          # Compilación de Wasm y despliegue a GitHub Pages
├── assets/
│   ├── css/
│   │   └── style.css           # Estilos del IDE
│   └── js/
│       ├── ai_client.js        # Cliente directo de IA (Gemini/Grok/etc)
│       ├── compiler_bridge.js  # Puente de comunicación JS <-> Wasm
│       ├── fs_virtual.js       # Manejo de Workspace con IndexedDB y File System API
│       ├── lucide.min.js       # Iconos
│       └── main.js             # Lógica principal del editor y UI
├── wasm-compiler/              # Código fuente del compilador en Rust (o C++)
│   ├── Cargo.toml
│   └── src/
│       ├── lexer.rs            # Analizador Léxico
│       ├── parser.rs           # Analizador Sintáctico (AST)
│       ├── semantic.rs         # Analizador Semántico
│       ├── symbols.rs          # Tabla de Símbolos
│       ├── intermediate.rs     # Generador 3AC
│       └── lib.rs              # Punto de entrada WebAssembly (wasm-bindgen)
├── dist/                       # Artefactos compilados para la web
│   ├── wasm_compiler.js        # Glue code JS generado por wasm-bindgen
│   └── wasm_compiler_bg.wasm   # Binario WebAssembly de alto rendimiento
├── automata.html               # Visualizador interactivo de autómatas léxicos
├── index.html                  # Punto de entrada único del IDE (SPA)
├── README.md                   # Documentación del proyecto
└── webassemblyplan.md          # Este documento
```

---

## 💡 7. Beneficios Clave del Cambio

1. **Despliegue Inmediato:** Con un solo enlace de GitHub (`https://usuario.github.io/php-IDE/`), cualquier persona podrá usar el compilador desde su computadora, tablet o móvil.
2. **Cero Mantenimiento de Servidores:** Sin costos de hosting, sin configuración de PHP, Apache o extensiones cURL.
3. **Seguridad y Privacidad:** El código del usuario y sus API keys nunca viajan a servidores intermediarios; todo se procesa en la memoria del navegador.
4. **Velocidad Extrema:** WebAssembly se ejecuta a velocidad casi nativa (C/Rust), permitiendo análisis sintáctico y renderizado de árboles en tiempo real mientras se teclea.
