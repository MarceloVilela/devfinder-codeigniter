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
        // 'id' com regra própria — sem isso o placeholder {id} do is_unique quebra em
        // runtime numa futura chamada a update() (achado real, ver ChannelModel).
        'id'         => 'permit_empty|is_natural_no_zero',
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

    /** POST /video — dedup por url exata (depois de normalizar &pp=), sem JOIN. */
    public function findByExactUrl(string $url): ?array
    {
        return $this->where('url', $url)->first();
    }

    /**
     * Extrai o id do YouTube da URL (`v=` param) — mesma regra já usada na Fase 1
     * (`videoId = url.split('v=')[1].split('&')[0]`, evidência: 0 falhas em 500 vídeos
     * reais do dump do projeto irmão).
     */
    public static function extractYoutubeId(string $url): ?string
    {
        if (! preg_match('/[?&]v=([^&]+)/', $url, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Remove parâmetro de tracking `&pp=...` — paridade com o `.replace()` global por
     * `&pp=` seguido de qualquer coisa até o próximo `&`, do `VideoController.store`
     * original.
     */
    public static function stripTrackingParam(string $url): string
    {
        return preg_replace('/&pp=[^&]*/', '', $url);
    }
}
