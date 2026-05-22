<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

class AuthProxyService
{
    private Client $client;

    public function __construct()
    {
        $baseUrl = rtrim($_ENV['AUTH_API_URL'] ?? '', '/');
        if (empty($baseUrl)) {
            throw new \RuntimeException('A variável de ambiente AUTH_API_URL não está configurada no arquivo .env.');
        }
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 15,
            'http_errors' => false,
        ]);
    }

    /**
     * POST /auth/login
     */
    public function login(string $email, string $password): array
    {
        return $this->post('/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * POST /auth/signup
     */
    public function signup(string $name, string $email, string $academyName, string $password, string $confirmPassword): array
    {
        return $this->post('/auth/signup', [
            'name' => $name,
            'email' => $email,
            'academy_name' => $academyName,
            'password' => $password,
            'confirmPassword' => $confirmPassword,
        ]);
    }

    /**
     * POST /auth/forgot-password
     */
    public function forgotPassword(string $email): array
    {
        return $this->post('/auth/forgot-password', [
            'email' => $email,
        ]);
    }

    /**
     * POST /auth/reset-password
     */
    public function resetPassword(string $token, string $newPassword, string $confirmPassword): array
    {
        return $this->post('/auth/reset-password', [
            'token' => $token,
            'newPassword' => $newPassword,
            'confirmPassword' => $confirmPassword,
        ]);
    }

    /**
     * Faz uma requisição POST genérica para a AuthNexora
     */
    private function post(string $uri, array $data): array
    {
        try {
            $response = $this->client->post($uri, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true) ?? [];
            $statusCode = $response->getStatusCode();

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'data' => $body,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'data' => ['error' => 'Falha ao conectar com o serviço de autenticação: ' . $e->getMessage()],
            ];
        }
    }
}
