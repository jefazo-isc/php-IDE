<?php

namespace App\AI;

interface AIProviderInterface {
    /**
     * Envía una consulta a la API de IA correspondiente y devuelve la respuesta.
     * 
     * @param string $prompt Mensaje o instrucción para la IA
     * @param array $options Opciones de configuración adicionales (temperatura, historial, etc.)
     * @return string Respuesta de la IA
     */
    public function generateResponse(string $prompt, array $options = []): string;
}
