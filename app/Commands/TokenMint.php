<?php

namespace App\Commands;

use App\Libraries\Jwt;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Auth as AuthConfig;

/**
 * Minta um JWT fora do fluxo normal de login — pra tokens de serviço de vida longa (ex.:
 * `APP_API_TOKEN` do `.github/workflows/video-refresh.yml`, que precisa sobreviver mais que
 * os `auth.jwtExpirySeconds` normais de produção — 604800s/7 dias, ver `render.yaml`).
 *
 * Não confere se `username` existe no banco — quem confere isso é o `RequiredAuthFilter`, no
 * momento da request real contra a API, não este comando.
 *
 * `--secret` sobrescreve `auth.jwtSecret` só pra esta execução, sem gravar em nenhum arquivo
 * — necessário pra mintar contra o secret de produção (Render, `sync: false`, nunca vai pro
 * `.env` local) rodando este comando localmente.
 *
 * Achado real (testado via `docker compose exec app`): o parser de opções do `spark` desta
 * versão do CI4 só reconhece `--opcao valor` (espaço) — `--opcao=valor` (igual) é ignorado
 * silenciosamente, sem erro, e a opção cai no default. Por isso o uso abaixo é com espaço,
 * não com `=`.
 *
 * Uso: php spark token:mint <username> [--days 365] [--secret <jwtSecret>]
 */
class TokenMint extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'token:mint';
    protected $description = 'Gera um JWT com expiracao customizavel (ex.: token de servico p/ ingestao agendada).';
    protected $usage       = 'token:mint <username> [--days 365] [--secret <jwtSecret>]';

    public function run(array $params)
    {
        $username = $params[0] ?? CLI::prompt('Username');

        if ($username === '') {
            CLI::error('Username e obrigatorio.');

            return;
        }

        $days   = (int) (CLI::getOption('days') ?? 365);
        $secret = CLI::getOption('secret');

        $config = clone config(AuthConfig::class);
        if ($secret !== null) {
            $config->jwtSecret = (string) $secret;
        }

        $token = (new Jwt($config))->encode($username, $days * 86400);

        CLI::write('username: ' . $username, 'yellow');
        CLI::write('expira em: ' . $days . ' dia(s)', 'yellow');
        CLI::write('secret usado: ' . ($secret !== null ? '--secret informado' : 'auth.jwtSecret do .env local'), 'yellow');
        CLI::newLine();
        CLI::write($token);
    }
}
