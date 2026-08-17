<?php

namespace App\Utils;

class RateLimiter {
    private int $maxCalls;
    private float $period;
    private string $lockFile;

    /**
     * Controlador de peticiones estricto para evitar errores 429 (Too Many Requests).
     * Utiliza un archivo temporal y flock() para asegurar exclusión mutua
     * entre múltiples procesos/peticiones PHP (equivalente a threading.Lock).
     */
    public function __construct(int $maxCalls = 14, float $period = 60.0, string $identifier = 'gemini') {
        $this->maxCalls = $maxCalls;
        $this->period = $period;
        // Archivo único por identificador en el directorio temporal del sistema
        $this->lockFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier) . '.lock';
    }

    public function wait(): void {
        $fp = fopen($this->lockFile, 'c+');
        if (!$fp) {
            return;
        }

        // Adquirir bloqueo exclusivo (espera si otro proceso lo tiene)
        flock($fp, LOCK_EX);

        $size = filesize($this->lockFile);
        $content = $size > 0 ? fread($fp, $size) : '';
        $calls = $content ? json_decode($content, true) : [];
        if (!is_array($calls)) {
            $calls = [];
        }

        $now = microtime(true);

        // Limpiamos el registro de llamadas que ya salieron de la ventana de tiempo
        $calls = array_filter($calls, function($t) use ($now) {
            return ($now - $t) < $this->period;
        });
        $calls = array_values($calls); // Reindexar

        // Si alcanzamos el tope, bloqueamos la ejecución el tiempo exacto necesario
        if (count($calls) >= $this->maxCalls) {
            $sleepTime = $this->period - ($now - $calls[0]);
            if ($sleepTime > 0) {
                Logger::log("Semáforo API en rojo. Límite de {$this->maxCalls} RPM alcanzado. Esperando " . round($sleepTime, 2) . "s...");
                usleep((int)($sleepTime * 1000000));
            }
            
            // Después de la pausa, actualizamos el tiempo y volvemos a limpiar
            $now = microtime(true);
            $calls = array_filter($calls, function($t) use ($now) {
                return ($now - $t) < $this->period;
            });
            $calls = array_values($calls);
        }

        // Registramos la llamada actual que está a punto de ejecutarse
        $calls[] = $now;

        // Sobrescribir el archivo
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($calls));
        fflush($fp);
        
        // Liberar lock
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
