<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

class GeminiService implements AIService
{
    private Client $client;

    public function __construct()
    {
        $baseUri = rtrim($_ENV['GEMINI_BASE_URL'] ?? '', '/');
        if (empty($baseUri)) {
            throw new \RuntimeException('A variável de ambiente GEMINI_BASE_URL não está configurada no arquivo .env.');
        }
        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout' => 30,
        ]);
    }

    public function sendMessage(string $message, string $context, array $history = []): array
    {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $model = $_ENV['GEMINI_MODEL'] ?? '';
        $systemPrompt = $_ENV['GEMINI_SYSTEM_PROMPT'] ?? '';

        if ($apiKey === '') {
            return ['provider' => 'gemini', 'answer' => 'Gemini não configurado. Defina GEMINI_API_KEY no .env.'];
        }
        if ($model === '') {
            return ['provider' => 'gemini', 'answer' => 'Modelo Gemini não configurado. Defina GEMINI_MODEL no .env.'];
        }
        if ($systemPrompt === '') {
            return ['provider' => 'gemini', 'answer' => 'System Prompt não configurado. Defina GEMINI_SYSTEM_PROMPT no .env.'];
        }

        // Monta os 'contents' incluindo o histórico
        $contents = [];
        
        // Adiciona histórico anterior
        foreach ($history as $chat) {
            $contents[] = [
                'role' => $chat['role'],
                'parts' => [['text' => $chat['content']]]
            ];
        }

        // Adiciona a pergunta atual com o contexto de documentos
        $prompt = "Contexto disponível:\n{$context}\n\nPergunta do usuário:\n{$message}";
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        try {
            $baseUri = rtrim($_ENV['GEMINI_BASE_URL'] ?? '', '/');
            $url = "{$baseUri}/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = $this->client->post($url, [
                'json' => [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemPrompt]
                        ]
                    ],
                    'contents' => $contents,
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            $answer = $payload['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta do modelo.';

            return ['provider' => 'gemini', 'answer' => $answer, 'raw' => $payload];
        } catch (Throwable $exception) {
            return ['provider' => 'gemini', 'answer' => 'Falha na comunicação com Gemini.', 'error' => $exception->getMessage()];
        }
    }
}
