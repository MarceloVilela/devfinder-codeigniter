<?php

namespace Tests\Support;

use App\Database\Seeds\AcceptanceSeeder;
use App\Libraries\AuthContext;
use App\Libraries\Jwt;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Base para os testes de feature (Fase 7, plan.md) — HTTP real via FeatureTestTrait, contra o
 * banco real (`database.tests`, MySQL) via DatabaseTestTrait, reaproveitando a mesma fixture
 * sintética já usada pelos casos de aceite manuais das Fases 3/5 (`AcceptanceSeeder`) em vez
 * de duplicar dado de teste.
 *
 * `$namespace = null` migra TODAS as namespaces antes de cada teste (não só `Tests\Support`,
 * que é o default do CI4) — sem isso, as 7 migrations reais de `App\Database\Migrations`
 * nunca rodariam contra `database.tests`, só o `example_migration` do scaffold.
 */
abstract class FeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;
    protected $seed      = AcceptanceSeeder::class;

    /**
     * Achado real (não previsível só lendo código): `App\Libraries\AuthContext` é um serviço
     * compartilhado (singleton) cujo comentário assume "PHP-FPM não compartilha memória entre
     * requests" — verdade em produção, falso aqui: `FeatureTestTrait::call()` simula requests
     * dentro do MESMO processo PHPUnit, e reseta `request`/`filters`/`validation` entre
     * chamadas mas não serviços customizados da aplicação. Sem este reset, autenticar como
     * `dev01` num teste vazava pro próximo teste (mesmo sem token) — reproduzido de verdade:
     * `GET /devs` sem token devolvia `total=32` em vez de 35 (excluía dev01 + seus 2 alvos de
     * like/dislike), `GET /feed/trending` devolvia `total=20` em vez de 55 (excluía os 35
     * vídeos do canal que `dev01` ignora) — os dois filtros de personalização de
     * `DevController::index`/`VideoController::trending` disparando "por acidente".
     */
    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('authContext', new AuthContext());
    }

    /** @return array{Authorization: string} */
    protected function authHeaders(string $username = 'dev01'): array
    {
        return ['Authorization' => 'Bearer ' . (new Jwt())->encode($username)];
    }
}
