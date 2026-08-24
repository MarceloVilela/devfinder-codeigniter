<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fixture sintética (sem dado pessoal real) para os casos de aceite da Fase 3.
 * 35 devs — o suficiente para exercitar paginação de GET /devs (30 + 30) em 2 páginas.
 */
class AcceptanceDevSeeder extends Seeder
{
    public function run()
    {
        $base = strtotime('2026-01-01 00:00:00');
        $rows = [];

        for ($i = 1; $i <= 35; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'username'   => "dev{$n}",
                'name'       => "Dev {$n}",
                'bio'        => $i % 3 === 0 ? null : "Bio sintética do dev {$n}.",
                'avatar'     => "https://example.test/avatar/dev{$n}.png",
                'created_at' => date('Y-m-d H:i:s', $base + $i * 60),
                'updated_at' => date('Y-m-d H:i:s', $base + $i * 60),
            ];
        }

        $this->db->table('devs')->insertBatch($rows);
    }
}
