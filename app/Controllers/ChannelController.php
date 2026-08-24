<?php

namespace App\Controllers;

use App\Models\ChannelModel;
use App\Models\DevModel;

class ChannelController extends BaseController
{
    private ChannelModel $channels;
    private DevModel $devs;

    public function __construct()
    {
        $this->channels = model(ChannelModel::class);
        $this->devs     = model(DevModel::class);
    }

    public function index()
    {
        // Contrato atual não pagina esta rota (ver fase-0-openapi.yaml / ArrayOfChannel).
        $rows = $this->channels->orderBy('name')->findAll();

        return $this->response->setJSON(array_map($this->present(...), $rows));
    }

    public function show(string $searchQuery)
    {
        $channel = $this->channels->findByNameOrLink($searchQuery);

        // 200 + null (não 404) — paridade com `Channel.findOne` do Mongo original.
        return $this->response->setJSON($channel === null ? null : $this->present($channel));
    }

    /**
     * POST /channels (auth) — paridade com `ChannelController.store` original: dedup por
     * "contém" (não igual), atualiza se achar (200) ou cria (201) + cria/reaproveita o Dev
     * associado a `userGithub` (só na criação, não no update).
     *
     * Divergência deliberada: o original faz `Dev.create({name, bio, avatar})` **sem**
     * `user`/`username` — campo obrigatório no schema Mongoose, essa chamada quebraria a
     * validação na prática (bug real, não replicável: nosso `devs.username` é `NOT NULL`).
     * Corrigido usando `DevModel::findOrCreate` com o `userGithub` como username — também
     * ganha idempotência de graça (original cria um Dev novo a cada `POST /channels` pro
     * mesmo `userGithub`, sem checar se já existe).
     */
    public function store()
    {
        $body = $this->request->getJSON(true);

        $name        = (string) $body['title'];
        $link        = (string) $body['link'];
        $userGithub  = (string) ($body['userGithub'] ?? '');
        $description = $body['description'] ?? null;
        $category    = strip_channel_emoji((string) $body['category']);
        $tags        = $body['tags'] ?? [];
        $avatar      = $body['avatar'] ?? null;

        $existing = $this->channels->findForStoreDedup($name, $link);

        if ($existing !== null) {
            // 'id' no array de dados (não só no 1º argumento) — achado real: o placeholder
            // {id} do is_unique só resolve a partir dos dados validados, não do argumento
            // de update(). Ver ChannelModel::$validationRules.
            $this->channels->update((int) $existing['id'], [
                'id'          => (int) $existing['id'],
                'name'        => $name,
                'link'        => $link,
                'user_github' => $userGithub !== '' ? $userGithub : null,
                'description' => $description,
                'category'    => $category,
                'avatar'      => $avatar,
            ]);
            $this->channels->syncTags((int) $existing['id'], $tags);

            return $this->response->setJSON($this->present($this->channels->find($existing['id'])));
        }

        $channelId = $this->channels->insert([
            'name'        => $name,
            'link'        => $link,
            'user_github' => $userGithub !== '' ? $userGithub : null,
            'description' => $description,
            'category'    => $category,
            'avatar'      => $avatar,
        ]);
        $this->channels->syncTags((int) $channelId, $tags);

        if ($userGithub !== '') {
            $response = service('curlrequest')->get("https://api.github.com/users/{$userGithub}", [
                'headers'     => ['User-Agent' => 'devfinder-codeigniter', 'Accept' => 'application/json'],
                'http_errors' => false,
            ]);
            $profile = json_decode((string) $response->getBody(), true) ?? [];

            if ($response->getStatusCode() === 200 && ! empty($profile['login'])) {
                $this->devs->findOrCreate([
                    'username' => $userGithub,
                    'name'     => (string) ($profile['name'] ?? $userGithub),
                    'bio'      => (string) ($profile['bio'] ?? ''),
                    'avatar'   => (string) ($profile['avatar_url'] ?? ''),
                ]);
            }
        }

        return $this->response->setStatusCode(201)->setJSON($this->present($this->channels->find($channelId)));
    }

    private function present(array $channel): array
    {
        return [
            '_id'         => (int) $channel['id'],
            'name'        => $channel['name'],
            'userGithub'  => $channel['user_github'],
            'link'        => $channel['link'],
            'description' => $channel['description'],
            'category'    => $channel['category'],
            'tags'        => $this->channels->tagsFor((int) $channel['id']),
            'avatar'      => $channel['avatar'],
            // Campos vestigiais — ver fase-1-data-model.md: nenhum controller da origem
            // escreve neles, mantidos sempre vazios só por paridade de contrato.
            'likes'       => [],
            'deslikes'    => [],
            'createdAt'   => to_iso8601($channel['created_at']),
            'updatedAt'   => to_iso8601($channel['updated_at']),
        ];
    }
}
