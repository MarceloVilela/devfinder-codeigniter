<?php

namespace App\Controllers;

use App\Models\DevModel;
use App\Models\DevReactionModel;
use App\Presenters\DevPresenter;

class DevController extends BaseController
{
    private DevModel $devs;
    private DevReactionModel $devReactions;

    public function __construct()
    {
        $this->devs = model(DevModel::class);
        $this->devReactions = model(DevReactionModel::class);
    }

    public function index()
    {
        $query = $this->devs->orderBy('created_at', 'DESC');

        $auth = service('authContext');
        if ($auth->isAuthenticated()) {
            // Autenticado: exclui o próprio Dev + quem já foi curtido/descurtido — paridade
            // com o `$nin` do Mongo original (DevController.index, devfinder-api).
            $excludedIds = array_merge(
                [$auth->devId()],
                $this->devReactions->targetIdsFor($auth->devId(), 'like'),
                $this->devReactions->targetIdsFor($auth->devId(), 'dislike'),
            );
            $query->whereNotIn('id', $excludedIds);
        }

        $rows  = $query->paginate(30);
        $total = $this->devs->pager->getTotal();

        return $this->response->setJSON([
            'docs'         => array_map(DevPresenter::present(...), $rows),
            'total'        => $total,
            'itemsPerPage' => 30,
        ]);
    }

    public function show(string $username)
    {
        $dev = $this->devs->findByUsername($username);

        // 200 + null (não 404) — paridade com `Dev.findOne` do Mongo original.
        return $this->response->setJSON($dev === null ? null : DevPresenter::present($dev));
    }

    /**
     * POST /devs (auth) — paridade com `DevController.store` original: sempre 201, mesmo
     * se o Dev já existir (`findOrCreateDev` não distingue os dois casos na resposta).
     */
    public function store()
    {
        $username = (string) $this->request->getJSON(true)['username'];
        $dev = $this->devs->findOrCreate(['username' => $username]);

        return $this->response->setStatusCode(201)->setJSON(DevPresenter::present($dev));
    }
}
