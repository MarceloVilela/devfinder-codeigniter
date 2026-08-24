<?php

namespace App\Controllers;

use App\Libraries\VideoIngestor;
use App\Models\ChannelModel;
use App\Models\ChannelReactionModel;
use App\Models\DevModel;
use App\Models\VideoModel;

class VideoController extends BaseController
{
    private VideoModel $videos;
    private ChannelModel $channels;
    private ChannelReactionModel $channelReactions;
    private DevModel $devs;

    public function __construct()
    {
        $this->videos           = model(VideoModel::class);
        $this->channels         = model(ChannelModel::class);
        $this->channelReactions = model(ChannelReactionModel::class);
        $this->devs             = model(DevModel::class);
    }

    public function trending()
    {
        $model = $this->videos->trendingQuery();

        // Personalização (Fase 4): autenticado (JWT) OU `?user=<username>` — paridade com
        // TrendingController.index original, que aceita os dois como formas de identificar
        // o Dev (o segundo não é autenticação, é identificação explícita sem token; decisão
        // de preservar já tomada em ../../serverless/specs/fase-4-auth.md, achado #9).
        $auth = service('authContext');
        $userIdentifier = $this->request->getGet('user');

        $devId = null;
        if ($auth->isAuthenticated()) {
            $devId = $auth->devId();
        } elseif ($userIdentifier) {
            $dev = $this->devs->findByUsername((string) $userIdentifier);
            $devId = $dev !== null ? (int) $dev['id'] : null;
        }

        if ($devId !== null) {
            $ignoredChannelIds = $this->channelReactions->targetIdsFor($devId, 'ignore');
            if ($ignoredChannelIds !== []) {
                $model->whereNotIn('videos.channel_id', $ignoredChannelIds);
            }
        }

        $rows  = $model->paginate(30);

        return $this->response->setJSON([
            'docs'         => array_map($this->present(...), $rows),
            'total'        => $model->pager->getTotal(),
            'itemsPerPage' => 30,
        ]);
    }

    public function byChannel()
    {
        $channelName = (string) $this->request->getGet('channel_name');
        $channel     = $this->channels->findByNameOrLink($channelName);

        if ($channel === null) {
            // Não propaga erro pra um canal inexistente (diferente do VideoController.index
            // original, que quebraria) — decisão registrada em specs/acceptance/feed-channel.http.
            return $this->response->setJSON(['docs' => [], 'total' => 0, 'itemsPerPage' => 30]);
        }

        $model = $this->videos->byChannelQuery((int) $channel['id']);
        $rows  = $model->paginate(30);

        return $this->response->setJSON([
            'docs'         => array_map($this->present(...), $rows),
            'total'        => $model->pager->getTotal(),
            'itemsPerPage' => 30,
        ]);
    }

    public function show(string $idYoutubeWatch)
    {
        $video = $this->videos->findByYoutubeId($idYoutubeWatch);

        // 200 + null (não 404) — paridade com `Video.findOne` do Mongo original.
        return $this->response->setJSON($video === null ? null : $this->present($video));
    }

    /**
     * POST /video (auth) — paridade com `VideoController.store` original: 400 se o canal
     * não existir, 409 (não 201) se o vídeo já existir (dedup por url exata, depois de
     * normalizar `&pp=`), thumbnail com fallback pro padrão do YouTube se vier vazia.
     */
    public function store()
    {
        $body = $this->request->getJSON(true);

        $title       = (string) $body['title'];
        $url         = VideoModel::stripTrackingParam((string) $body['url']);
        $channelName = (string) $body['channel'];
        $channelUrl  = (string) $body['channel_url'];
        $thumbnail   = VideoModel::stripTrackingParam((string) ($body['thumbnail'] ?? ''));

        $channel = $this->channels->findForVideoLink($channelName, $channelUrl);
        if ($channel === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'errorMessage' => "channel({$channelName}) not found, for: {$title}",
                'title'        => $title,
                'url'          => $url,
                'channel'      => $channelName,
                'channel_url'  => $channelUrl,
                'thumbnail'    => $thumbnail,
            ]);
        }

        $existing = $this->videos->findByExactUrl($url);
        if ($existing !== null) {
            $full = $this->videos->findByYoutubeId($existing['youtube_id']);

            return $this->response->setStatusCode(409)->setJSON(array_merge(
                ['errorMessage' => "video({$title}) already exists"],
                $this->present($full)
            ));
        }

        $youtubeId = VideoModel::extractYoutubeId($url);
        if ($thumbnail === '') {
            $thumbnail = "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg";
        }

        $this->videos->insert([
            'youtube_id' => $youtubeId,
            'title'      => $title,
            'url'        => $url,
            'channel_id' => (int) $channel['id'],
            'thumbnail'  => $thumbnail,
        ]);

        return $this->response->setStatusCode(201)->setJSON($this->present($this->videos->findByYoutubeId($youtubeId)));
    }

    /**
     * POST /video/refresh (auth) — ingestão em lote, mesmo serviço usado pelo Command
     * `spark video:refresh` (App\Libraries\VideoIngestor); só muda a origem dos candidatos:
     * aqui vem do body `{ record: AddVideo[] }` (contrato `fase-0-openapi.yaml`), lá vem do
     * JSONBin.io. Shape de resposta paridade com `VideoRefreshController.store` original:
     * `{ videosAdded, videosFounded, errors }`.
     */
    public function refresh()
    {
        $body       = $this->request->getJSON(true);
        $candidates = $body['record'] ?? [];

        $result = (new VideoIngestor())->ingest($candidates);

        return $this->response->setJSON([
            'videosAdded'   => array_map($this->present(...), $result['videosAdded']),
            'videosFounded' => array_map($this->present(...), $result['videosFounded']),
            'errors'        => $result['errors'],
        ]);
    }

    private function present(array $video): array
    {
        return [
            '_id'         => (int) $video['id'],
            'title'       => $video['title'],
            'url'         => $video['url'],
            'channel_id'  => (int) $video['channel_id'],
            'channel'     => $video['channel'],
            'channel_url' => $video['channel_url'],
            'thumbnail'   => $video['thumbnail'],
            'viewnum'     => $video['viewnum'] !== null ? (int) $video['viewnum'] : null,
            'date'        => to_iso8601($video['published_at']),
            'createdAt'   => to_iso8601($video['created_at']),
            'updatedAt'   => to_iso8601($video['updated_at']),
        ];
    }
}
