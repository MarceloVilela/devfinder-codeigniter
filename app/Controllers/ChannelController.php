<?php

namespace App\Controllers;

use App\Models\ChannelModel;

class ChannelController extends BaseController
{
    private ChannelModel $channels;

    public function __construct()
    {
        $this->channels = model(ChannelModel::class);
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
