<?php

namespace App\Controllers;

use App\Models\ChannelReactionModel;
use App\Models\DevModel;
use App\Models\DevReactionModel;

class DevController extends BaseController
{
    private DevModel $devs;
    private DevReactionModel $devReactions;
    private ChannelReactionModel $channelReactions;

    public function __construct()
    {
        $this->devs             = model(DevModel::class);
        $this->devReactions     = model(DevReactionModel::class);
        $this->channelReactions = model(ChannelReactionModel::class);
    }

    public function index()
    {
        $rows  = $this->devs->orderBy('created_at', 'DESC')->paginate(30);
        $total = $this->devs->pager->getTotal();

        return $this->response->setJSON([
            'docs'         => array_map($this->present(...), $rows),
            'total'        => $total,
            'itemsPerPage' => 30,
        ]);
    }

    public function show(string $username)
    {
        $dev = $this->devs->findByUsername($username);

        // 200 + null (não 404) — paridade com `Dev.findOne` do Mongo original.
        return $this->response->setJSON($dev === null ? null : $this->present($dev));
    }

    private function present(array $dev): array
    {
        return [
            '_id'       => (int) $dev['id'],
            'name'      => $dev['name'],
            'user'      => $dev['username'],
            'bio'       => $dev['bio'] ?? '',
            'avatar'    => $dev['avatar'],
            'likes'     => $this->devReactions->targetIdsFor((int) $dev['id'], 'like'),
            'deslikes'  => $this->devReactions->targetIdsFor((int) $dev['id'], 'dislike'),
            'follow'    => $this->channelReactions->targetIdsFor((int) $dev['id'], 'follow'),
            'ignore'    => $this->channelReactions->targetIdsFor((int) $dev['id'], 'ignore'),
            'createdAt' => to_iso8601($dev['created_at']),
            'updatedAt' => to_iso8601($dev['updated_at']),
        ];
    }
}
