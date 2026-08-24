<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Roda toda a fixture sintética usada pelos casos de aceite em specs/acceptance/. Uso:
 * `php spark db:seed AcceptanceSeeder` contra um banco recém-migrado e vazio.
 */
class AcceptanceSeeder extends Seeder
{
    public function run()
    {
        $this->call(AcceptanceDevSeeder::class);
        $this->call(AcceptanceChannelSeeder::class);
        $this->call(AcceptanceVideoSeeder::class);
        $this->call(AcceptanceReactionSeeder::class);
    }
}
