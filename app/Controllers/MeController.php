<?php

namespace App\Controllers;

use App\Models\DevModel;
use App\Presenters\DevPresenter;

/**
 * GET /me — protegida por RequiredAuthFilter. Substitui ProfileController.show. O caso
 * "DevProfile not exists" do original não é alcançável aqui: RequiredAuthFilter já valida
 * que o Dev existe antes de deixar a request chegar até aqui (retorna 401 caso contrário),
 * então não há um segundo "dev não encontrado" pra tratar depois.
 */
class MeController extends BaseController
{
    public function show()
    {
        $dev = model(DevModel::class)->findByUsername(service('authContext')->username());

        return $this->response->setJSON(DevPresenter::present($dev));
    }
}
