<?php

namespace App\Models;

use CodeIgniter\Model;

class DevModel extends Model
{
    protected $table         = 'devs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['username', 'name', 'bio', 'avatar'];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $returnType    = 'array';

    protected $validationRules = [
        'username' => 'required|is_unique[devs.username,id,{id}]',
        'name'     => 'required',
        'avatar'   => 'required',
    ];

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Find-or-create por username (case-insensitive) — mesma semântica de
     * `findOrCreateDev.ts` do devfinder-api original: se `name`/`bio`/`avatar` vierem
     * vazios, completa com uma chamada pública (não autenticada) à API do GitHub antes de
     * criar. Usado pelo callback do OAuth (Fase 4) e por `POST /devs` (Fase 5).
     *
     * @param array{username: string, name?: string, bio?: string, avatar?: string} $profile
     */
    public function findOrCreate(array $profile): array
    {
        $username = strtolower($profile['username']);

        $existing = $this->findByUsername($username);
        if ($existing !== null) {
            return $existing;
        }

        $name   = $profile['name'] ?? '';
        $bio    = $profile['bio'] ?? '';
        $avatar = $profile['avatar'] ?? '';

        if ($name === '' || $bio === '' || $avatar === '') {
            $response = service('curlrequest')->get("https://api.github.com/users/{$username}", [
                'headers'     => ['User-Agent' => 'devfinder-codeigniter', 'Accept' => 'application/json'],
                'http_errors' => false,
            ]);
            $public = json_decode((string) $response->getBody(), true) ?? [];

            $name   = $name !== '' ? $name : (string) ($public['name'] ?? $username);
            $bio    = $bio !== '' ? $bio : (string) ($public['bio'] ?? '');
            $avatar = $avatar !== '' ? $avatar : (string) ($public['avatar_url'] ?? '');
        }

        $id = $this->insert([
            'username' => $username,
            'name'     => $name,
            'bio'      => $bio,
            'avatar'   => $avatar,
        ]);

        return $this->find($id);
    }
}
