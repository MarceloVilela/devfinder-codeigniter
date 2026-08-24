<?php

namespace App\Controllers;

use App\Models\DevModel;
use App\Models\DevReactionModel;
use App\Presenters\DevPresenter;

/**
 * Substitui `LikeController`/`DislikeController` (devfinder-api) — like/dislike entre Devs.
 * Resposta sempre o Dev autenticado (`DevPresenter::present`), paridade com
 * `res.json(loggedDev)` do original.
 */
class DevReactionController extends BaseController
{
    private DevModel $devs;
    private DevReactionModel $reactions;

    public function __construct()
    {
        $this->devs      = model(DevModel::class);
        $this->reactions = model(DevReactionModel::class);
    }

    public function likeStore(string $username)
    {
        return $this->toggle($username, 'like', true);
    }

    public function likeDelete(string $username)
    {
        return $this->toggle($username, 'like', false);
    }

    public function dislikeStore(string $username)
    {
        return $this->toggle($username, 'dislike', true);
    }

    public function dislikeDelete(string $username)
    {
        return $this->toggle($username, 'dislike', false);
    }

    /** GET /likes/devs (auth) */
    public function likedDevs()
    {
        return $this->list('like');
    }

    /** GET /dislikes/devs (auth) */
    public function dislikedDevs()
    {
        return $this->list('dislike');
    }

    private function toggle(string $username, string $type, bool $add)
    {
        $target = $this->devs->findByUsername($username);
        if ($target === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Dev not exists']);
        }

        $devId = service('authContext')->devId();
        $add
            ? $this->reactions->add($devId, (int) $target['id'], $type)
            : $this->reactions->remove($devId, (int) $target['id'], $type);

        return $this->response->setJSON(DevPresenter::present($this->devs->find($devId)));
    }

    private function list(string $type)
    {
        $devId = service('authContext')->devId();
        $ids   = $this->reactions->targetIdsFor($devId, $type);

        $devs = $ids === [] ? [] : $this->devs->whereIn('id', $ids)->findAll();

        return $this->response->setJSON(array_map(DevPresenter::present(...), $devs));
    }
}
