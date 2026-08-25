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
        return $this->response->setJSON([
            'getenv_CI_ENVIRONMENT' => getenv('CI_ENVIRONMENT') !== false ? getenv('CI_ENVIRONMENT') : null,
            'getenv_PORT'           => getenv('PORT') !== false ? getenv('PORT') : null,
            // Só nomes de chave, nenhum valor — diagnóstico de quais env vars custom (as que
            // a gente setou no Render, não as de sistema/imagem) estão chegando de fato.
            'all_getenv_keys'  => array_values(array_keys(getenv())),
            'all_env_keys'     => array_values(array_keys($_ENV)),
            'all_server_keys'  => array_values(array_keys($_SERVER)),
        ]);
    }
}
