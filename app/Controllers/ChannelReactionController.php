<?php

namespace App\Controllers;

use App\Models\ChannelModel;
use App\Models\ChannelReactionModel;
use App\Models\DevModel;
use App\Presenters\DevPresenter;

/**
 * Substitui `FollowController`/`IgnoreController` (devfinder-api) — follow/ignore de
 * canais. Resposta sempre o Dev autenticado, paridade com `res.json(loggedDev)` do original.
 * Alvo resolvido por `channels.name` exato — mesmo critério de `Channel.findOne({name:
 * username})` do original (não usa `findByNameOrLink`, que também aceita link).
 */
class ChannelReactionController extends BaseController
{
    private ChannelModel $channels;
    private ChannelReactionModel $reactions;
    private DevModel $devs;

    public function __construct()
    {
        $this->channels  = model(ChannelModel::class);
        $this->reactions = model(ChannelReactionModel::class);
        $this->devs      = model(DevModel::class);
    }

    public function followStore(string $channelName)
    {
        return $this->toggle($channelName, 'follow', true);
    }

    public function followDelete(string $channelName)
    {
        return $this->toggle($channelName, 'follow', false);
    }

    public function ignoreStore(string $channelName)
    {
        return $this->toggle($channelName, 'ignore', true);
    }

    public function ignoreDelete(string $channelName)
    {
        return $this->toggle($channelName, 'ignore', false);
    }

    private function toggle(string $channelName, string $type, bool $add)
    {
        $target = $this->channels->where('name', $channelName)->first();
        if ($target === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Channel not exists']);
        }

        $devId = service('authContext')->devId();
        $add
            ? $this->reactions->add($devId, (int) $target['id'], $type)
            : $this->reactions->remove($devId, (int) $target['id'], $type);

        return $this->response->setJSON(DevPresenter::present($this->devs->find($devId)));
    }
}
