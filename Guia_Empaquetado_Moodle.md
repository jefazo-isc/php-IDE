# Guía: Cómo crear un ejecutable portable de PHP en menos de 20MB para Moodle

Dado que Moodle tiene un límite estricto de **20MB**, pero necesitas que tu proyecto sea "portable" (es decir, que el profesor solo haga doble clic en un ejecutable en Windows y se levante el servidor), el reto principal no es tu código, sino **el peso del binario de PHP** de Windows.

Actualmente, tu código fuente pesa apenas unos **~5.8 MB** (de los cuales 4MB son el PDF de instrucciones que no hace falta incluir). El problema es que una instalación típica de PHP en Windows pesa más de 30MB comprimida y hasta 100MB descomprimida. 

Aquí te enseño la estrategia exacta para reducir todo a **menos de 10-15 MB en total**.

---

## 1. Limpieza de tu código base (Lo que NO debes subir)

Antes de empaquetar, asegúrate de **NO** incluir los siguientes archivos y carpetas en tu .zip final, ya que solo ocupan espacio innecesario:

- `Fase_2_Analisis_Sintactico_Extendida 2025.pdf` (¡Pesa 4MB!)
- La carpeta `.git/` (El historial de versiones puede pesar muchísimo).
- Archivos temporales o de registro de errores: `error_log.txt`, `_temp_code.tmp`, etc.
- La carpeta `.cursor/` o `.vscode/`.

Solo necesitas: `index.php`, `.env`, y las carpetas `src/`, `vistas/`, `assets/`, `compilador/` y `workspace/`.

---

## 2. Crear un entorno PHP Micro-Portable para Windows

Para que el servidor corra localmente necesitas descargar los binarios de PHP, pero los vamos a "adelgazar" al extremo.

### Paso 2.1: Descargar PHP NTS
1. Ve a [windows.php.net](https://windows.php.net/download/)
2. Descarga la versión **VS17 x86 Non Thread Safe** (NTS) en formato `.zip`. (Es la versión de 32 bits, ideal para máxima compatibilidad como mencionaste, y es más ligera).
3. Descomprime esto en una carpeta llamada `php/` dentro de la raíz de tu proyecto.

### Paso 2.2: Eliminar peso muerto en PHP (¡Muy Importante!)
El PHP recién descargado tiene muchas cosas que tu proyecto no necesita. Entra a la carpeta `php/` que acabas de crear y **elimina**:
- Archivos `.pdb` (No sirven para ejecución, solo para debug y pesan muchísimo).
- La carpeta `dev/`.
- Todos los archivos `.txt` o de licencias si quieres ahorrar KB (opcional).
- **En la carpeta `ext/` (Extensiones):** Borra **TODAS** las extensiones (archivos `.dll`) que NO uses en tu IDE. Por lo general, para un proyecto básico solo podrías llegar a necesitar `php_mbstring.dll` y `php_openssl.dll`. Borra todas las de bases de datos (`php_pdo_pgsql`, `php_oci8`, `php_mysqli`, etc.) si no usas base de datos.
- Archivos como `phpdbg.exe`, `php-cgi.exe`, `php-win.exe` (solo necesitas `php.exe` y la librería base `php8.dll`).

> **💡 Opcional Nivel Experto (UPX):** Si aún así el `php.exe` y `php8.dll` te parecen muy pesados, puedes descargar un programa gratuito llamado **UPX** y comprimir los `.dll` y el `.exe` desde la consola. Esto reduce el tamaño del binario a la mitad sin afectar su funcionamiento.

### Paso 2.3: Configurar el php.ini
Copia el archivo `php.ini-production`, renómbralo a `php.ini` y habilita solo las extensiones que dejaste en la carpeta `ext/`.

---

## 3. Crear el Ejecutable (.bat a .exe)

En la raíz de tu proyecto (fuera de la carpeta `php/`), crea un archivo llamado `iniciar.bat` con el siguiente código:

```bat
@echo off
echo Iniciando Servidor del IDE...
echo Por favor, no cierres esta ventana.
echo Abre tu navegador en http://localhost:8000

:: Abre el navegador predeterminado automáticamente
start http://localhost:8000

:: Levanta el servidor local apuntando a la raíz del proyecto
cd %~dp0
php\php.exe -S localhost:8000
```

### Convertir el .bat a .exe
Para que se vea profesional y no como un simple script:
1. Descarga una herramienta gratuita como **Bat To Exe Converter** (IExpress que viene en Windows también sirve, búscalo en el menú inicio).
2. Convierte tu `iniciar.bat` en `MiIDE.exe`.
3. *(Opcional)* Puedes agregarle un ícono `.ico` bonito en el convertidor.

---

## 4. Estructura Final y Compresión Máxima

Tu carpeta final antes de comprimir debería verse así:

```text
/Mi_Proyecto_Final
  /assets
  /compilador
  /php              <-- Tu PHP súper reducido (~15MB o menos)
  /src
  /vistas
  /workspace
  .env
  index.php
  MiIDE.exe         <-- Tu ejecutable (el .bat convertido)
```

**Para subir a Moodle:**
1. Selecciona todos estos archivos.
2. Haz clic derecho y envíalos a un archivo `.zip`.
3. Si usas **7-Zip**, elige el formato `.zip` y en Nivel de compresión selecciona **"Ultra"** (y el método "Deflate64" o "LZMA" si te lo permite, aunque "Deflate" normal en Ultra es más compatible con el descompresor nativo de Windows).

El código fuente de texto plano se comprime a casi el **10% de su tamaño original**, y el PHP ya lo adelgazaste. ¡El archivo `.zip` final pesará menos de 10 MB, perfecto para Moodle!
