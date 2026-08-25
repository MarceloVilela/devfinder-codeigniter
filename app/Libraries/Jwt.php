<?php

namespace App\Libraries;

use Config\Auth as AuthConfig;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Throwable;

/**
 * Assina/verifica o JWT — payload `{ username }` (ver ../../plan.md, Fase 4, e
 * ../../../serverless/specs/fase-4-auth.md, achado #3: DynamoDB não tem ObjectId, decisão já
 * tomada lá e reaproveitada aqui; nesta stack MySQL até teria um `id` numérico disponível,
 * mas o `username` é o identificador estável e legível — sem motivo real pra divergir do
 * payload já adotado pelo projeto irmão).
 */
class Jwt
{
    private string $secret;
    private int $expirySeconds;

    public function __construct(?AuthConfig $config = null)
    {
        $config = $config ?? config(AuthConfig::class);
        $this->secret = $config->jwtSecret;
        $this->expirySeconds = $config->jwtExpirySeconds;
    }

    /**
     * @param int|null $expirySecondsOverride sobrescreve `auth.jwtExpirySeconds` só pra esta
     *                                         chamada — usado por `spark token:mint` pra gerar
     *                                         tokens de serviço com validade diferente da do
     *                                         login normal (ver App\Commands\TokenMint).
     */
    public function encode(string $username, ?int $expirySecondsOverride = null): string
    {
        $now = time();

        return FirebaseJwt::encode([
            'username' => $username,
            'iat'      => $now,
            'exp'      => $now + ($expirySecondsOverride ?? $this->expirySeconds),
        ], $this->secret, 'HS256');
    }

    /**
     * @return string|null username, ou null se o token estiver ausente/malformado/expirado/
     *                      com assinatura inválida — motivo exato não é exposto ao caller
     *                      (paridade com o `catch` genérico do `auth.ts`/`optionalAuth.ts`
     *                      original, que também não distingue o tipo de falha na resposta).
     */
    public function decode(string $token): ?string
    {
        try {
            $decoded = FirebaseJwt::decode($token, new Key($this->secret, 'HS256'));

            return $decoded->username ?? null;
        } catch (Throwable $e) {
            // Qualquer falha de decode (expirado, assinatura inválida, malformado,
            // algoritmo inesperado) vira "token inválido" — mesma granularidade do
            // catch genérico do auth.ts/optionalAuth.ts original, que também não
            // distingue o motivo exato na resposta.
            return null;
        }
    }
}
