<?php

use Tests\Support\FeatureTestCase;

/**
 * Casos de aceite críticos da Fase 3/Fase 5 para `/video`, `/feed/trending`, `/feed/channel`
 * — ver specs/acceptance/video-show.http, video-store.http, feed-trending.http,
 * feed-channel.http.
 *
 * @internal
 */
final class VideosTest extends FeatureTestCase
{
    public function testShowByYoutubeId(): void
    {
        // AcceptanceVideoSeeder: 20 vídeos "vidalpha01".."vidalpha20" em Canal Alpha.
        $result = $this->get('v1/video/vidalpha01');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('Canal Alpha', $body['channel']);
    }

    public function testShowReturnsNullForUnknownId(): void
    {
        $result = $this->get('v1/video/does-not-exist');

        $result->assertStatus(200);
        $this->assertSame('null', $result->getJSON());
    }

    public function testTrendingIsPaginated(): void
    {
        // 20 (Alpha) + 35 (Beta) = 55 vídeos — 2 páginas de 30.
        $result = $this->get('v1/feed/trending');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(55, $body['total']);
        $this->assertCount(30, $body['docs']);
    }

    public function testByChannelFiltersCorrectly(): void
    {
        $result = $this->get('v1/feed/channel?' . http_build_query(['channel_name' => 'Canal Beta']));

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(35, $body['total']);
    }

    public function testStoreRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')->post('v1/video', [
            'title'       => 'Video de teste',
            'url'         => 'https://www.youtube.com/watch?v=zzzFEATURE01',
            'channel'     => 'Canal Alpha',
            'channel_url' => 'https://youtube.com/alpha',
        ]);

        $result->assertStatus(401);
    }

    public function testStoreReturns409OnDuplicateUrl(): void
    {
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/video', [
                'title'       => 'Duplicado de teste',
                'url'         => 'https://www.youtube.com/watch?v=vidalpha01',
                'channel'     => 'Canal Alpha',
                'channel_url' => 'https://youtube.com/alpha',
            ]);

        $result->assertStatus(409);
    }

    public function testStoreCreatesNewVideo(): void
    {
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/video', [
                'title'       => 'Video novo de teste',
                'url'         => 'https://www.youtube.com/watch?v=zzzFEATURE01',
                'channel'     => 'Canal Alpha',
                'channel_url' => 'https://youtube.com/alpha',
            ]);

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('https://i.ytimg.com/vi/zzzFEATURE01/hqdefault.jpg', $body['thumbnail']);
    }
}
