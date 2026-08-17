<?php

namespace App\Utils;

use Exception;

class GeminiAuditor {
    /**
     * Obtiene y prueba los modelos generativos de Google Gemini.
     * Utiliza curl_multi para realizar pruebas de latencia en paralelo (stress test).
     */
    public static function auditModels(string $apiKey): array {
        // Aumentar el límite de tiempo ya que el Rate Limiter puede hacernos esperar >60s
        set_time_limit(150);

        // 1. Obtener lista de todos los modelos disponibles
        $urlList = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
        $chList = curl_init($urlList);
        curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chList, CURLOPT_TIMEOUT, 10);
        $responseList = curl_exec($chList);
        $httpCodeList = curl_getinfo($chList, CURLINFO_HTTP_CODE);
        curl_close($chList);

        if ($httpCodeList !== 200 || !$responseList) {
            throw new Exception("Error al conectar con Google. Verifica tu conexión o tu API Key.");
        }

        $dataList = json_decode($responseList, true);
        if (empty($dataList['models'])) {
            throw new Exception("No se encontraron modelos bajo esta API Key.");
        }

        // 2. Filtrar solo los que soportan "generateContent" (modelos generativos)
        $generativos = [];
        foreach ($dataList['models'] as $model) {
            if (isset($model['supportedGenerationMethods']) && in_array('generateContent', $model['supportedGenerationMethods'])) {
                $generativos[] = [
                    'name' => $model['name'],
                    'displayName' => $model['displayName'] ?? $model['name'],
                    'description' => $model['description'] ?? '',
                ];
            }
        }

        // 3. Ejecutar peticiones de prueba en paralelo respetando la cuota
        $rateLimiter = new RateLimiter(14, 60.0, 'gemini_api');

        $resultados = [
            'total_encontrados' => count($dataList['models']),
            'total_generativos' => count($generativos),
            'funcionales' => [],
            'fallidos' => []
        ];

        // Lotes de 14 para respetar la cuota de RPM gratuita
        $lotes = array_chunk($generativos, 14);

        foreach ($lotes as $lote) {
            foreach ($lote as $model) {
                $rateLimiter->wait();
            }

            $mh = curl_multi_init();
            $curlHandles = [];
            $payload = json_encode([
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => "Hola, responde únicamente con la palabra 'Activo'."]]]
                ]
            ]);

            foreach ($lote as $model) {
                $name = $model['name'];
                $url = "https://generativelanguage.googleapis.com/v1beta/{$name}:generateContent?key=" . $apiKey;
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                curl_multi_add_handle($mh, $ch);
                $curlHandles[$name] = [
                    'ch' => $ch,
                    'model' => $model,
                    'start_time' => microtime(true)
                ];
            }

            $active = null;
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK) {
                if (curl_multi_select($mh) == -1) {
                    usleep(100);
                }
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }

            foreach ($curlHandles as $name => $info) {
                $ch = $info['ch'];
                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $tiempo = round(microtime(true) - $info['start_time'], 2);
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if ($httpCode === 200) {
                    $resData = json_decode($response, true);
                    if (!empty($resData['candidates'][0]['content']['parts'][0]['text'])) {
                        $resultados['funcionales'][] = [
                            'name' => $name,
                            'displayName' => $info['model']['displayName'] ?? $name,
                            'description' => $info['model']['description'] ?? '',
                            'tiempo' => $tiempo,
                            'respuesta' => trim($resData['candidates'][0]['content']['parts'][0]['text'])
                        ];
                        continue;
                    }
                }
                
                $errData = json_decode($response, true);
                $errMsg = $errData['error']['message'] ?? "HTTP $httpCode";
                $resultados['fallidos'][] = [
                    'name' => $name,
                    'error' => $errMsg
                ];
            }
            curl_multi_close($mh);
        }

        // Ordenar funcionales por tiempo de respuesta (los más rápidos primero)
        usort($resultados['funcionales'], fn($a, $b) => $a['tiempo'] <=> $b['tiempo']);

        return $resultados;
    }
}
