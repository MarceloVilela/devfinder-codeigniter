<?php

namespace App\Filters;

use App\Libraries\Jwt;
use App\Models\DevModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Substitui `middlewares/auth.ts` — 401 se o Bearer token estiver ausente/inválido, senão
 * popula AuthContext e deixa a request seguir. Mensagens de erro em paridade com o original.
 */
class RequiredAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '') {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Token not provided.']);
        }

        $token = trim(substr($header, strlen('Bearer ')));
        $username = (new Jwt())->decode($token);

        if ($username === null) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Token invalid.']);
        }

        $dev = model(DevModel::class)->findByUsername($username);
        if ($dev === null) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Token invalid.']);
        }

        service('authContext')->set((int) $dev['id'], $dev['username']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
