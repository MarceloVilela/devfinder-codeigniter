<?php

use Tests\Support\FeatureTestCase;

/**
 * Casos de aceite críticos da Fase 6 (ingestão em lote) — ver
 * specs/acceptance/video-refresh-http.http, já validado manualmente contra o bin real do
 * JSONBin.io (specs/acceptance/execucao-fase-6.log). Aqui só o núcleo compartilhado
 * (App\Libraries\VideoIngestor, via POST /video/refresh) é exercitado — a busca real no
 * JSONBin.io não entra no CI de propósito (dependência de rede externa + credenciais reais,
 * mesmo motivo já registrado para o caminho GitHub em DevsTest/ChannelsTest).
 *
 * @internal
 */
final class VideoRefreshTest extends FeatureTestCase
{
    public function testRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')->post('v1/video/refresh', ['record' => []]);

        $result->assertStatus(401);
    }

    public function testEmptyRecordReturnsEmptyArrays(): void
    {
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/video/refresh', []);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(['videosAdded' => [], 'videosFounded' => [], 'errors' => []], $body);
    }

    public function testMixedBatch(): void
    {
        $result = $this->withBodyFormat('json')
            ->withHeaders($this->authHeaders())
            ->post('v1/video/refresh', [
                'record' => [
                    [
                        'title'       => 'Video novo via refresh',
                        'url'         => 'https://www.youtube.com/watch?v=zzzREFRESH01',
                        'channel'     => 'Canal Alpha',
                        'channel_url' => 'https://youtube.com/alpha',
                    ],
                    [
                        'title'       => 'Video ja existente',
                        'url'         => 'https://www.youtube.com/watch?v=vidalpha01',
                        'channel'     => 'Canal Alpha',
                        'channel_url' => 'https://youtube.com/alpha',
                    ],
                    [
                        'title'       => 'Video de canal inexistente',
                        'url'         => 'https://www.youtube.com/watch?v=zzzREFRESH02',
                        'channel'     => 'Canal Que Nao Existe',
                        'channel_url' => 'https://youtube.com/nao-existe',
                    ],
                ],
            ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertCount(1, $body['videosAdded']);
        $this->assertSame('zzzREFRESH01', $this->extractYoutubeId($body['videosAdded'][0]['url']));

        $this->assertCount(1, $body['videosFounded']);
        $this->assertSame('vidalpha01', $this->extractYoutubeId($body['videosFounded'][0]['url']));

        $this->assertCount(1, $body['errors']);
        $this->assertStringContainsString('Canal Que Nao Existe', $body['errors'][0]['errorMessage']);
    }

    private function extractYoutubeId(string $url): ?string
    {
        return \App\Models\VideoModel::extractYoutubeId($url);
    }
}
