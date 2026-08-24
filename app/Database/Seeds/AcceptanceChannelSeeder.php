<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fixture sintética para os casos de aceite da Fase 3: 2 canais + tags, o suficiente pra
 * exercitar GET /channels, GET /channels/{searchQuery} (por nome e por link) e a montagem
 * de `tags` via channel_tag.
 */
class AcceptanceChannelSeeder extends Seeder
{
    public function run()
    {
        $base = strtotime('2026-01-01 00:00:00');

        $this->db->table('channels')->insertBatch([
            [
                'name'        => 'Canal Alpha',
                'link'        => 'https://youtube.com/alpha',
                'category'    => 'Tecnologia',
                'description' => 'Canal sintético Alpha, usado nos casos de aceite.',
                'avatar'      => 'https://example.test/avatar/canal-alpha.png',
                'created_at'  => date('Y-m-d H:i:s', $base),
                'updated_at'  => date('Y-m-d H:i:s', $base),
            ],
            [
                'name'        => 'Canal Beta',
                'link'        => 'https://youtube.com/beta',
                'category'    => 'Educação',
                'description' => 'Canal sintético Beta, usado nos casos de aceite.',
                'avatar'      => 'https://example.test/avatar/canal-beta.png',
                'created_at'  => date('Y-m-d H:i:s', $base + 1),
                'updated_at'  => date('Y-m-d H:i:s', $base + 1),
            ],
        ]);

        $alphaId = (int) $this->db->table('channels')->where('name', 'Canal Alpha')->get()->getRow('id');
        $betaId  = (int) $this->db->table('channels')->where('name', 'Canal Beta')->get()->getRow('id');

        $this->db->table('tags')->insertBatch([
            ['name' => 'javascript'],
            ['name' => 'testes'],
            ['name' => 'react'],
        ]);

        $jsId     = (int) $this->db->table('tags')->where('name', 'javascript')->get()->getRow('id');
        $testesId = (int) $this->db->table('tags')->where('name', 'testes')->get()->getRow('id');
        $reactId  = (int) $this->db->table('tags')->where('name', 'react')->get()->getRow('id');

        $this->db->table('channel_tag')->insertBatch([
            ['channel_id' => $alphaId, 'tag_id' => $jsId],
            ['channel_id' => $alphaId, 'tag_id' => $testesId],
            ['channel_id' => $betaId, 'tag_id' => $reactId],
        ]);
    }
}
