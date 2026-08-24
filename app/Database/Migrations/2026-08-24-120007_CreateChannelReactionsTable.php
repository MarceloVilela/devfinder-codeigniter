<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelReactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'dev_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'channel_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['follow', 'ignore']],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['dev_id', 'channel_id', 'type'], true);
        $this->forge->addForeignKey('dev_id', 'devs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('channel_reactions');
    }

    public function down()
    {
        $this->forge->dropTable('channel_reactions');
    }
}
