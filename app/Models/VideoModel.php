<?php

namespace App\Models;

use CodeIgniter\Model;

class VideoModel extends Model
{
    protected $table          = 'videos';
    protected $primaryKey     = 'id';
    protected $allowedFields  = [
        'youtube_id', 'title', 'url', 'channel_id', 'thumbnail', 'viewnum', 'published_at',
    ];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $returnType     = 'array';

    protected $validationRules = [
        'youtube_id' => 'required|is_unique[videos.youtube_id,id,{id}]',
        'url'        => 'required|is_unique[videos.url,id,{id}]',
        'title'      => 'required',
        'channel_id' => 'required|is_natural_no_zero',
        'thumbnail'  => 'required',
    ];

    private const SELECT_WITH_CHANNEL = 'videos.*, channels.name AS channel, channels.link AS channel_url';

    /**
     * Reconstrói channel/channel_url via JOIN (Fase 1: campos desnormalizados do Mongoose
     * viram JOIN, não coluna duplicada) — mantido pra paridade de contrato na resposta.
     */
    public function findByYoutubeId(string $youtubeId): ?array
    {
        return $this->select(self::SELECT_WITH_CHANNEL)
            ->join('channels', 'channels.id = videos.channel_id')
            ->where('videos.youtube_id', $youtubeId)
            ->first();
    }

    public function trendingQuery()
    {
        return $this->select(self::SELECT_WITH_CHANNEL)
            ->join('channels', 'channels.id = videos.channel_id')
            ->orderBy('videos.created_at', 'DESC');
    }

    public function byChannelQuery(int $channelId)
    {
        return $this->trendingQuery()->where('videos.channel_id', $channelId);
    }
}
