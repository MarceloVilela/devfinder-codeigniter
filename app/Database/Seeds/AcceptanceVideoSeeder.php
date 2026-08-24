<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fixture sintética para os casos de aceite da Fase 3: 20 vídeos em "Canal Alpha" + 35 em
 * "Canal Beta" (55 no total) — o suficiente pra exercitar paginação de GET /feed/trending
 * (2 páginas) e de GET /feed/channel (2 páginas só para Beta). Inseridos em ordem
 * cronológica crescente (Alpha primeiro, Beta depois) para que a ordenação por created_at
 * DESC seja determinística nos casos de aceite.
 */
class AcceptanceVideoSeeder extends Seeder
{
    public function run()
    {
        $alphaId = (int) $this->db->table('channels')->where('name', 'Canal Alpha')->get()->getRow('id');
        $betaId  = (int) $this->db->table('channels')->where('name', 'Canal Beta')->get()->getRow('id');

        $base = strtotime('2026-02-01 00:00:00');
        $rows = [];
        $t    = 0;

        for ($i = 1; $i <= 20; $i++) {
            $n      = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'youtube_id'   => "vidalpha{$n}",
                'title'        => "Vídeo Alpha {$n}",
                'url'          => "https://www.youtube.com/watch?v=vidalpha{$n}",
                'channel_id'   => $alphaId,
                'thumbnail'    => "https://i.ytimg.com/vi/vidalpha{$n}/hqdefault.jpg",
                'published_at' => null,
                'created_at'   => date('Y-m-d H:i:s', $base + $t++ * 60),
                'updated_at'   => date('Y-m-d H:i:s', $base + $t * 60),
            ];
        }

        for ($i = 1; $i <= 35; $i++) {
            $n      = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'youtube_id'   => "vidbeta{$n}",
                'title'        => "Vídeo Beta {$n}",
                'url'          => "https://www.youtube.com/watch?v=vidbeta{$n}",
                'channel_id'   => $betaId,
                'thumbnail'    => "https://i.ytimg.com/vi/vidbeta{$n}/hqdefault.jpg",
                'published_at' => null,
                'created_at'   => date('Y-m-d H:i:s', $base + $t++ * 60),
                'updated_at'   => date('Y-m-d H:i:s', $base + $t * 60),
            ];
        }

        $this->db->table('videos')->insertBatch($rows);
    }
}
