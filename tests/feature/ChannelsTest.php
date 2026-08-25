<?php

use Tests\Support\FeatureTestCase;

/**
 * Casos de aceite críticos da Fase 3/Fase 5 para `/channels` — ver
 * specs/acceptance/channels-index.http, channel-show.http, channels-store.http.
 *
 * @internal
 */
final class ChannelsTest extends FeatureTestCase
{
    public function testIndexReturnsAllChannelsWithTags(): void
    {
        $result = $this->get('v1/channels');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertCount(3, $body);

        $alpha = current(array_filter($body, static fn (array $c) => $c['name'] === 'Canal Alpha'));
        $this->assertSame(['javascript', 'testes'], $alpha['tags']);
    }

    public function testShowByExactName(): void
    {
        // Só por nome, não por link — achado real documentado em
        // specs/acceptance/channel-show.http: um link com "/" como segmento de path não
        // sobrevive ao roteamento do CI4 mesmo url-encoded (`%2F` é repartido antes do regex
        // da rota rodar), e não é um caso real (o frontend nunca chama esta rota com link).
        $result = $this->get('v1/channels/' . rawurlencode('Canal Alpha'));

        $result->assertStatus(200);
        $this->assertSame('Canal Alpha', json_decode($result->getJSON(), true)['name']);
    }

    public function testShowReturnsNullWhenNotFound(): void
    {
        $result = $this->get('v1/channels/nao-existe');

        $result->assertStatus(200);
        $this->assertSame('null', $result->getJSON());
    }

    public function testStoreRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')->post('v1/channels', [
            'title'    => 'Canal Novo Teste',
            'link'     => 'https://youtube.com/canal-novo-teste',
            'category' => 'Testes',
        ]);

        $result->assertStatus(401);
    }

    public function testStoreCreatesNewChannel(): void
    {
        // Sem userGithub de propósito — evita a chamada real à API pública do GitHub
        // (ChannelController::store só a faz se userGithub vier preenchido), caminho já
        // validado manualmente na Fase 5 (execucao-fase-5.log).
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/channels', [
                'title'    => 'Canal Novo Teste',
                'link'     => 'https://youtube.com/canal-novo-teste',
                'category' => 'Testes',
                'tags'     => ['nova-tag'],
            ]);

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('Canal Novo Teste', $body['name']);
        $this->assertSame(['nova-tag'], $body['tags']);
    }

    public function testStoreUpdatesExistingChannelOnNameMatch(): void
    {
        // findForStoreDedup() casa por "contém" — mandar o título exato de um canal
        // existente aciona o caminho de update (200), não criação (201).
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/channels', [
                'title'    => 'Canal Alpha',
                'link'     => 'https://youtube.com/alpha',
                'category' => 'Tecnologia Atualizada',
            ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('Tecnologia Atualizada', $body['category']);
    }
}
