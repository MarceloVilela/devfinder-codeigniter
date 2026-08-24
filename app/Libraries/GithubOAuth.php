<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Auth as AuthConfig;
use RuntimeException;

/**
 * Troca `code`↔`access_token` e busca o perfil no GitHub — o que o Passport
 * (`passport-github`) fazia magicamente no devfinder-api original. Mesmo mecanismo já
 * validado em ../../../serverless/specs/fase-4-auth.md, achado #1.
 */
class GithubOAuth
{
    private CURLRequest $client;
    private string $clientId;
    private string $clientSecret;

    public function __construct(?AuthConfig $config = null, ?CURLRequest $client = null)
    {
        $config = $config ?? config(AuthConfig::class);
        $this->clientId = $config->githubClientId;
        $this->clientSecret = $config->githubClientSecret;
        $this->client = $client ?? service('curlrequest');
    }

    public function authorizeUrl(string $redirectUri): string
    {
        // Sem `scope` — paridade com o original (`passport-github`, socialLoginGithub.ts,
        // não configurava nenhum). Perfil público (login/name/bio/avatar_url) já vem sem
        // escopo nenhum; read:user só seria necessário pra dado privado (ex. email).
        return 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id'    => $this->clientId,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /** @throws RuntimeException se o GitHub recusar o code (ex.: já usado/expirado) */
    public function exchangeCodeForToken(string $code): string
    {
        $response = $this->client->post('https://github.com/login/oauth/access_token', [
            'headers'     => ['Accept' => 'application/json'],
            'json'        => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
            ],
            // false: o endpoint de exchange do GitHub devolve HTTP 200 mesmo em erro
            // (ex.: code já usado), com { "error": "bad_verification_code" } no corpo —
            // não é um caso 4xx/5xx, mas o profile fetch abaixo pode ser (token inválido).
            'http_errors' => false,
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (empty($body['access_token'])) {
            throw new RuntimeException('GitHub OAuth exchange failed: ' . ($body['error'] ?? 'unknown_error'));
        }

        return $body['access_token'];
    }

    /** @return array{username: string, name: string, bio: string, avatar: string} */
    public function fetchProfile(string $accessToken): array
    {
        $response = $this->client->get('https://api.github.com/user', [
            'headers'     => [
                'Authorization' => "Bearer {$accessToken}",
                'User-Agent'    => 'devfinder-codeigniter',
                'Accept'        => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $profile = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() !== 200 || empty($profile['login'])) {
            throw new RuntimeException('GitHub profile fetch failed: ' . ($profile['message'] ?? 'unknown_error'));
        }

        return [
            'username' => (string) $profile['login'],
            'name'     => (string) ($profile['name'] ?? $profile['login']),
            'bio'      => (string) ($profile['bio'] ?? ''),
            'avatar'   => (string) ($profile['avatar_url'] ?? ''),
        ];
    }
}
