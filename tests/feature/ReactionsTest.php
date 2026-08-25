<?php

use App\Models\ChannelModel;
use App\Models\DevModel;
use Tests\Support\FeatureTestCase;

/**
 * Casos de aceite críticos da Fase 5 (escrita/relacionamento) — toggle idempotente de
 * like/dislike (devs) e follow/ignore (channels), e o no-op de auto-reação garantido pela
 * `CHECK` da Fase 1. Ver specs/acceptance/likes-dislikes-devs.http,
 * likes-dislikes-channels.http. `dislikeStore`/`ignoreStore` reusam o mesmo método privado
 * `toggle()` de `likeStore`/`followStore` (App\Controllers\{Dev,Channel}ReactionController)
 * — não repetidos aqui um a um, cobertos pela mesma cobertura de código.
 *
 * @internal
 */
final class ReactionsTest extends FeatureTestCase
{
    public function testLikeDevIsIdempotent(): void
    {
        // dev10 não tem reação de baseline com dev01 (AcceptanceReactionSeeder só toca
        // dev02/dev03) — alvo limpo para testar o toggle sem interferência.
        $headers = $this->authHeaders('dev01');

        $first = $this->withHeaders($headers)->post('v1/likes/devs/dev10');
        $first->assertStatus(200);

        $second = $this->withHeaders($headers)->post('v1/likes/devs/dev10');
        $second->assertStatus(200);

        $body = json_decode($second->getJSON(), true);
        // Idempotente: dev10 aparece uma única vez em likes, não duas.
        $this->assertCount(1, array_keys($body['likes'], $this->devId('dev10'), true));

        $delete = $this->withHeaders($headers)->delete('v1/likes/devs/dev10');
        $delete->assertStatus(200);
        $afterDelete = json_decode($delete->getJSON(), true);
        $this->assertNotContains($this->devId('dev10'), $afterDelete['likes']);
    }

    public function testSelfLikeIsNoop(): void
    {
        // CHECK (dev_id <> target_dev_id) da Fase 1 — DevReactionModel::add() já trata como
        // no-op silencioso antes de tentar o INSERT. Resposta continua 200, sem erro.
        $headers = $this->authHeaders('dev01');

        $result = $this->withHeaders($headers)->post('v1/likes/devs/dev01');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertNotContains($this->devId('dev01'), $body['likes']);
    }

    public function testFollowChannelIsIdempotent(): void
    {
        // Canal Zeta não tem reação de baseline (AcceptanceChannelSeeder) — dedicado a este
        // teste, mesmo critério já usado nos casos de aceite manuais.
        $headers = $this->authHeaders('dev01');

        $first = $this->withHeaders($headers)->post('v1/likes/channels/Canal%20Zeta');
        $first->assertStatus(200);

        $second = $this->withHeaders($headers)->post('v1/likes/channels/Canal%20Zeta');
        $second->assertStatus(200);

        $body = json_decode($second->getJSON(), true);
        $this->assertCount(1, array_keys($body['follow'], $this->channelId('Canal Zeta'), true));

        $delete = $this->withHeaders($headers)->delete('v1/likes/channels/Canal%20Zeta');
        $delete->assertStatus(200);
        $afterDelete = json_decode($delete->getJSON(), true);
        $this->assertNotContains($this->channelId('Canal Zeta'), $afterDelete['follow']);
    }

    public function testToggleOnUnknownTargetReturns400(): void
    {
        $result = $this->withHeaders($this->authHeaders('dev01'))->post('v1/likes/devs/nao-existe');

        $result->assertStatus(400);
    }

    private function devId(string $username): int
    {
        return (int) model(DevModel::class)->findByUsername($username)['id'];
    }

    private function channelId(string $name): int
    {
        return (int) model(ChannelModel::class)->where('name', $name)->first()['id'];
    }
}
