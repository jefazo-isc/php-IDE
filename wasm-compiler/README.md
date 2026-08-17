# IDE Indómito - Motor WebAssembly (Rust)

Este es el núcleo del compilador reescrito en Rust para ser compilado a WebAssembly.

## Requisitos Previos
1. Instalar Rust: `curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh`
2. Instalar wasm-pack: `curl https://rustwasm.github.io/wasm-pack/installer/init.sh -sSf | sh`

## Compilación
Para compilar el proyecto a WebAssembly, ejecuta en este directorio:
```bash
wasm-pack build --target web --out-dir ../dist
```

Esto generará `ide_indomito_compiler.js` y `ide_indomito_compiler_bg.wasm` en la carpeta `dist/` en la raíz de tu proyecto, que luego pueden ser cargados en el frontend.
