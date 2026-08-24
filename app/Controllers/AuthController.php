<?php

namespace App\Controllers;

use App\Libraries\GithubOAuth;
use App\Libraries\Jwt;
use App\Models\DevModel;
use Config\Auth as AuthConfig;

class AuthController extends BaseController
{
    private GithubOAuth $github;
    private DevModel $devs;
    private AuthConfig $config;

    public function __construct()
    {
        $this->config = config(AuthConfig::class);
        $this->github = new GithubOAuth($this->config);
        $this->devs   = model(DevModel::class);
    }

    /**
     * GET /auth/github — substitui `passport.authenticate('github')`: redirect 302 pra tela
     * de autorização do GitHub.
     */
    public function github()
    {
        $redirectUri = base_url('v1/auth/github/callback');

        return redirect()->to($this->github->authorizeUrl($redirectUri));
    }

    /**
     * GET /auth/github/callback — troca code→access_token→profile, upsert do Dev, emite
     * JWT, redirect pro devfinder-next com o token na query string.
     */
    public function callback()
    {
        $code = $this->request->getGet('code');

        if (empty($code)) {
            // Falha (ex.: usuário negou autorização no GitHub) — redireciona pro app web,
            // não para um `/login` relativo à própria API. O original
            // (`failureRedirect: '/login'`) tem exatamente esse bug sem consumidor real;
            // corrigido em vez de replicado (mesma decisão já tomada em
            // ../../serverless/specs/fase-4-auth.md, "Registro de execução").
            return redirect()->to($this->config->webURL . '/login');
        }

        $accessToken = $this->github->exchangeCodeForToken((string) $code);
        $profile     = $this->github->fetchProfile($accessToken);
        $dev         = $this->devs->findOrCreate($profile);

        $token = (new Jwt($this->config))->encode($dev['username']);

        // Só `token=` na query string — `id=` do redirect original não é lido em nenhum
        // lugar do devfinder-next (confirmado lendo hooks/auth.tsx e pages/login/index.tsx:
        // só `router.query.token`), não replicado.
        return redirect()->to($this->config->webURL . '/login?token=' . urlencode($token));
    }
}
