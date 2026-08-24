<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fixture sintética: algumas reações pra `dev01`, o suficiente pra GET /devs/dev01 devolver
 * likes/deslikes/follow/ignore não-vazios nos casos de aceite (ver dev-show.http).
 */
class AcceptanceReactionSeeder extends Seeder
{
    public function run()
    {
        $devs = $this->db->table('devs')->select('id, username')->get()->getResultArray();
        $byUsername = array_column($devs, 'id', 'username');

        $alphaId = (int) $this->db->table('channels')->where('name', 'Canal Alpha')->get()->getRow('id');
        $betaId  = (int) $this->db->table('channels')->where('name', 'Canal Beta')->get()->getRow('id');

        $this->db->table('dev_reactions')->insertBatch([
            ['dev_id' => $byUsername['dev01'], 'target_dev_id' => $byUsername['dev02'], 'type' => 'like', 'created_at' => date('Y-m-d H:i:s')],
            ['dev_id' => $byUsername['dev01'], 'target_dev_id' => $byUsername['dev03'], 'type' => 'dislike', 'created_at' => date('Y-m-d H:i:s')],
        ]);

        $this->db->table('channel_reactions')->insertBatch([
            ['dev_id' => $byUsername['dev01'], 'channel_id' => $alphaId, 'type' => 'follow', 'created_at' => date('Y-m-d H:i:s')],
            ['dev_id' => $byUsername['dev01'], 'channel_id' => $betaId, 'type' => 'ignore', 'created_at' => date('Y-m-d H:i:s')],
        ]);
    }
}
