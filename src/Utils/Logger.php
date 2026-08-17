<?php

namespace App\Utils;

class Logger {
    /**
     * Registra un mensaje de error o evento en el archivo de log.
     * 
     * @param string $mensaje Mensaje a registrar
     */
    public static function log(string $mensaje): void {
        $archivo = dirname(dirname(__DIR__)) . '/error_log.txt';
        $fecha = date('Y-m-d H:i:s');
        file_put_contents($archivo, "[$fecha] $mensaje\n", FILE_APPEND);
    }
}
