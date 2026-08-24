<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class VideoRefresh extends BaseConfig
{
    /**
     * Credenciais do bin em jsonbin.io que alimenta `php spark video:refresh` e
     * `POST /video/refresh` — mesmo bin já usado pelo devfinder-api original (ver
     * ../devfinder-api/src/task.ts). Variáveis de ambiente (.env), sem valor de fallback:
     * sem elas, o comando roda com 0 candidatos, sem erro (ver App\Commands\VideoRefresh).
     */
    public string $jsonbinApiKey = '';
    public string $jsonbinIdSubs = '';
}
