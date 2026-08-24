<?php

namespace App\Libraries;

use App\Models\ChannelModel;
use App\Models\VideoModel;

/**
 * Núcleo da ingestão em lote de vídeos (Fase 6) — porta de
 * `devfinder-api/src/controllers/Video/VideoRefreshController.ts` (`addVideo`), extraído
 * como serviço compartilhado desde o início (diferente do projeto irmão, que precisou
 * refatorar depois — ver `../../serverless/specs/fase-6-ingestao-lote.md`, "Estrutura de
 * código proposta") pra nunca duplicar resolução de canal/dedup/thumbnail entre os dois
 * entrypoints que consomem isso:
 *
 * @see \App\Commands\VideoRefresh        php spark video:refresh (candidatos via JSONBin.io)
 * @see \App\Controllers\VideoController::refresh()  POST /video/refresh (candidatos via body)
 */
class VideoIngestor
{
    private VideoModel $videos;
    private ChannelModel $channels;

    public function __construct()
    {
        $this->videos   = model(VideoModel::class);
        $this->channels = model(ChannelModel::class);
    }

    /**
     * @param array<int, array{title?: string, url?: string, channel?: string, channel_url?: string, thumbnail?: string}> $candidates
     *
     * @return array{videosAdded: array<int, array>, videosFounded: array<int, array>, errors: array<int, array>}
     */
    public function ingest(array $candidates): array
    {
        $videosAdded   = [];
        $videosFounded = [];
        $errors        = [];

        foreach ($candidates as $item) {
            $title       = (string) ($item['title'] ?? '');
            $url         = VideoModel::stripTrackingParam((string) ($item['url'] ?? ''));
            $channelName = (string) ($item['channel'] ?? '');
            $channelUrl  = (string) ($item['channel_url'] ?? '');
            $thumbnail   = VideoModel::stripTrackingParam((string) ($item['thumbnail'] ?? ''));

            $channel = $this->channels->findForVideoLink($channelName, $channelUrl);

            if ($channel === null) {
                $errors[] = [
                    'errorMessage' => "channel({$channelName}) not found, for: {$title}",
                    'title'        => $title,
                    'url'          => $url,
                    'channel'      => $channelName,
                    'channel_url'  => $channelUrl,
                    'thumbnail'    => $thumbnail,
                ];

                continue;
            }

            $existing = $this->videos->findByExactUrl($url);
            if ($existing !== null) {
                $videosFounded[] = $this->videos->findByYoutubeId($existing['youtube_id']);

                continue;
            }

            $youtubeId = VideoModel::extractYoutubeId($url);

            $this->videos->insert([
                'youtube_id' => $youtubeId,
                'title'      => $title,
                'url'        => $url,
                'channel_id' => (int) $channel['id'],
                'thumbnail'  => VideoModel::resolveThumbnail($thumbnail, $url),
            ]);

            $videosAdded[] = $this->videos->findByYoutubeId($youtubeId);
        }

        return ['videosAdded' => $videosAdded, 'videosFounded' => $videosFounded, 'errors' => $errors];
    }
}
