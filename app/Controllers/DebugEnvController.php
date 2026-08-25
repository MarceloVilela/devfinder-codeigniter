<?php

namespace App\Controllers;

/**
 * Diagnóstico temporário (Fase 7.5, troubleshooting do 500 em /v1/feed/trending no Render) —
 * NÃO expõe valores, só onde (se é que em algum lugar) o PHP está enxergando a env var
 * `database.defaultGroup`. Remover assim que o problema de env var no Render for resolvido —
 * ver specs/deploy/fase-7-deploy-render.md, "Causa raiz confirmada".
 */
class DebugEnvController extends BaseController
{
    public function env()
    {
        $needle = 'database';

        return $this->response->setJSON([
            'getenv_defaultGroup_dot'        => getenv('database.defaultGroup') !== false,
            'getenv_defaultGroup_underscore' => getenv('database_defaultGroup') !== false,
            'env_super_has_dot_key'          => array_key_exists('database.defaultGroup', $_ENV),
            'server_super_has_dot_key'       => array_key_exists('database.defaultGroup', $_SERVER),
            'getenv_keys_matching'           => array_values(array_filter(array_keys(getenv()), static fn ($k) => str_contains($k, $needle))),
            'env_super_keys_matching'        => array_values(array_filter(array_keys($_ENV), static fn ($k) => str_contains($k, $needle))),
            'server_super_keys_matching'     => array_values(array_filter(array_keys($_SERVER), static fn ($k) => str_contains($k, $needle))),
        ]);
    }
}
