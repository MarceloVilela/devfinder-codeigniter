<?php

use Tests\Support\FeatureTestCase;

/**
 * Casos de aceite críticos da Fase 3 (leitura)/Fase 5 (escrita) para `/devs` — ver
 * specs/acceptance/devs-index.http, dev-show.http, devs-store.http (já validados manualmente
 * contra Docker Compose real; aqui viram automação de CI, Fase 7).
 *
 * @internal
 */
final class DevsTest extends FeatureTestCase
{
    public function testIndexReturnsPaginatedDocs(): void
    {
        // AcceptanceDevSeeder cria 35 devs — o suficiente pra exercitar 2 páginas de 30.
        $result = $this->get('v1/devs');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame(35, $body['total']);
        $this->assertSame(30, $body['itemsPerPage']);
        $this->assertCount(30, $body['docs']);
    }

    public function testShowReturnsDevByUsername(): void
    {
        // dev01 tem reações de baseline (AcceptanceReactionSeeder): like em dev02, dislike em
        // dev03, follow em Canal Alpha, ignore em Canal Beta.
        $result = $this->get('v1/devs/dev01');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('dev01', $body['user']);
        $this->assertNotEmpty($body['likes']);
        $this->assertNotEmpty($body['deslikes']);
        $this->assertNotEmpty($body['follow']);
        $this->assertNotEmpty($body['ignore']);
    }

    public function testShowReturnsNullForUnknownUsername(): void
    {
        // 200 + null, não 404 — paridade com `Dev.findOne` do Mongo original.
        $result = $this->get('v1/devs/does-not-exist');

        $result->assertStatus(200);
        $this->assertSame('null', $result->getJSON());
    }

    public function testStoreRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')->post('v1/devs', ['username' => 'dev01']);

        $result->assertStatus(401);
    }

    public function testStoreIsIdempotentForExistingDev(): void
    {
        // dev01 já existe no seed — findOrCreate() retorna cedo, sem chamar a API pública do
        // GitHub (caminho sem dependência de rede, seguro pra CI). O caminho de criação real
        // via GitHub (username inexistente no banco) já foi validado manualmente na Fase 5
        // (specs/acceptance/execucao-fase-5.log) — não repetido aqui de propósito, dependeria
        // de rede externa real dentro do CI.
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders('dev01'))
            ->post('v1/devs', ['username' => 'dev01']);

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('dev01', $body['user']);
    }
}
