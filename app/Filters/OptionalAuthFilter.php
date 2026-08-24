<?php

namespace App\Filters;

use App\Libraries\Jwt;
use App\Models\DevModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Substitui `middlewares/optionalAuth.ts` — nunca bloqueia. Se vier um Bearer token válido,
 * popula AuthContext (personaliza a resposta); token ausente/inválido segue como anônimo,
 * sem erro. Ao contrário do Lambda Authorizer da versão serverless (que tem o problema real
 * de `identitySource` rejeitar antes de invocar — ver ../../../serverless/specs/fase-4-auth.md,
 * "Descoberta não antecipada"), um Filter do CI4 roda como código PHP normal, sem esse
 * problema — "opcional" aqui é trivial.
 */
class OptionalAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '') {
            return;
        }

        $token = trim(substr($header, strlen('Bearer ')));
        $username = (new Jwt())->decode($token);
        if ($username === null) {
            return;
        }

        $dev = model(DevModel::class)->findByUsername($username);
        if ($dev === null) {
            return;
        }

        service('authContext')->set((int) $dev['id'], $dev['username']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
