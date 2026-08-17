<?php

namespace App\AI;

use Exception;

class AIService {
    /**
     * Devuelve una instancia del proveedor de IA solicitado.
     * 
     * @param string $provider Nombre del proveedor ('gemini', 'grok', 'mercury')
     * @return AIProviderInterface
     * @throws Exception Si el proveedor no existe o falla su inicialización
     */
    public static function getProvider(string $provider): AIProviderInterface {
        $providerLower = strtolower(trim($provider));

        if ($providerLower === 'gemini' || strpos($providerLower, 'models/gemini') === 0) {
            return new Providers\GeminiProvider($provider);
        }

        switch ($providerLower) {
            case 'grok':
                return new Providers\GrokProvider();
            case 'mercury':
                return new Providers\MercuryProvider();
            default:
                throw new Exception("El proveedor de IA '$provider' no está soportado o no es válido.");
        }
    }

    /**
     * Devuelve una lista de los proveedores soportados y su configuración.
     * 
     * @return array
     */
    public static function getAvailableProviders(): array {
        return [
            [
                'id' => 'gemini',
                'name' => 'Google Gemini 1.5 Flash',
                'description' => 'Ideal para explicaciones detalladas y análisis rápido.',
                'enabled' => !empty(getenv('GEMINI_API_KEY') ?: (getenv('GEMINI_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_ENV['GEMINI_KEY'] ?? ''))))
            ],
            [
                'id' => 'grok',
                'name' => 'xAI Grok 2',
                'description' => 'Excelente para lógica matemática, programación y depuración.',
                'enabled' => !empty(getenv('GROK_API_KEY') ?: (getenv('GROK_KEY') ?: (getenv('XAI_API_KEY') ?: ($_ENV['GROK_API_KEY'] ?? ($_ENV['GROK_KEY'] ?? ($_ENV['XAI_API_KEY'] ?? ''))))))
            ],
            [
                'id' => 'mercury',
                'name' => 'Inception Labs Mercury 2',
                'description' => 'Extremadamente rápido con generación basada en difusión.',
                'enabled' => !empty(getenv('MERCURY_API_KEY') ?: (getenv('MERCURY_KEY') ?: ($_ENV['MERCURY_API_KEY'] ?? ($_ENV['MERCURY_KEY'] ?? ''))))
            ]
        ];
    }
}
