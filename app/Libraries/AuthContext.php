<?php

namespace App\Libraries;

/**
 * Guarda o Dev identificado na request atual — populado por RequiredAuthFilter/
 * OptionalAuthFilter (before), lido pelos Controllers. Instância compartilhada via Service
 * (app/Config/Services.php), uma por request (PHP-FPM não compartilha memória entre
 * requests, então não há risco de vazar entre usuários).
 */
class AuthContext
{
    private ?int $devId = null;
    private ?string $username = null;

    public function set(int $devId, string $username): void
    {
        $this->devId = $devId;
        $this->username = $username;
    }

    public function devId(): ?int
    {
        return $this->devId;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function isAuthenticated(): bool
    {
        return $this->devId !== null;
    }
}
