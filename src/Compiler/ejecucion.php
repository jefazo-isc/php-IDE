<?php
if ($argc < 2) {
    die("Error: Archivo fuente o ejecutable no especificado.\n");
}

$archivo = $argv[1];

if (!file_exists($archivo)) {
    die("Error: El archivo no existe o no se puede leer.\n");
}

// Aquí va la simulación de la máquina virtual o ejecución final
echo "--- EJECUCIÓN ---\n";
echo "> Iniciando proceso...\n";
echo "Salida del programa: Operación exitosa.\n";
echo "> Proceso finalizado con código 0.\n";
?>