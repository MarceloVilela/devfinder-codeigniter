<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevReactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'dev_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'target_dev_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'type'          => ['type' => 'ENUM', 'constraint' => ['like', 'dislike']],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['dev_id', 'target_dev_id', 'type'], true);
        // on_update RESTRICT (não CASCADE): MySQL/InnoDB proíbe CHECK constraint numa coluna
        // que também tenha ON UPDATE CASCADE — "Column 'dev_id' cannot be used in a check
        // constraint... needed in a foreign key constraint... referential action" (erro real,
        // reproduzido rodando a migration). ON DELETE CASCADE sozinho funciona normalmente;
        // RESTRICT no update não perde nada de prático (PK auto_increment nunca é atualizada).
        $this->forge->addForeignKey('dev_id', 'devs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('target_dev_id', 'devs', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('dev_reactions');

        $this->db->query(
            'ALTER TABLE dev_reactions ADD CONSTRAINT chk_dev_reactions_not_self ' .
            'CHECK (dev_id <> target_dev_id)'
        );
    }

    public function down()
    {
        $this->forge->dropTable('dev_reactions');
    }
}
