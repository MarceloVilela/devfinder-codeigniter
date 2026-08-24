<?php

namespace App\Controllers;

use App\Models\ChannelModel;
use App\Models\VideoModel;

class VideoController extends BaseController
{
    private VideoModel $videos;
    private ChannelModel $channels;

    public function __construct()
    {
        $this->videos   = model(VideoModel::class);
        $this->channels = model(ChannelModel::class);
    }

    public function trending()
    {
        $model = $this->videos->trendingQuery();
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
