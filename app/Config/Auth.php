<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Auth extends BaseConfig
{
    /**
     * Secreto usado pra assinar/verificar o JWT. Variável de ambiente (.env) — sem
     * equivalente a Secrets Manager necessário aqui (ver ../plan.md, Fase 4: sem custo fixo
     * mensal a evitar, ao contrário do caso AWS/../serverless).
     */
    public string $jwtSecret = '';

    /**
     * Validade do JWT em segundos. 604800 = 7 dias — paridade com `authConfig.expiresIn`
     * ('7d') do devfinder-api original.
     */
    public int $jwtExpirySeconds = 604800;

    public string $githubClientId = '';
    public string $githubClientSecret = '';

    /**
     * Base URL do devfinder-next — pra onde o callback do GitHub redireciona depois de
     * emitir o token (`{webURL}/login?token=...`).
     */
    public string $webURL = '';
}
