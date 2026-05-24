<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

class DocumentSearchService
{
    private Client $client;

    public function __construct()
    {
        $baseUri = rtrim($_ENV['BRAIN_API_URL'] ?? 'http://brainnexora-web', '/');
        
        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout' => 10,
        ]);
    }

    public static function make(): self
    {
        return new self();
    }

    public function search(string $query, int $limit = 5): array
    {
        $keywords = array_values(array_filter(preg_split('/\s+/', trim(mb_strtolower($query))) ?: []));

        if ($keywords === []) {
            return [];
        }

        try {
            $response = $this->client->get('/', [
                'query' => [
                    'query' => $query,
                    'limit' => $limit
                ]
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            
            if (is_array($payload)) {
                return $payload;
            }

            return [];
        } catch (Throwable $exception) {
            // Se falhar a comunicação com o cérebro, retorna um array vazio ou loga o erro
            error_log("Erro ao conectar no BrainNexora API: " . $exception->getMessage());
            return [];
        }
    }
}
