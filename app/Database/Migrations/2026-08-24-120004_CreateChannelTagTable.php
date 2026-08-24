<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelTagTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'channel_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'tag_id'     => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['channel_id', 'tag_id'], true);
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('channel_tag');
    }

    public function down()
    {
        $this->forge->dropTable('channel_tag');
    }
}
