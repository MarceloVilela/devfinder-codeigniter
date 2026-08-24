<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVideosTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'youtube_id'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'url'          => ['type' => 'VARCHAR', 'constraint' => 500],
            'channel_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'thumbnail'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'viewnum'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('youtube_id');
        $this->forge->addUniqueKey('url');
        $this->forge->addKey('channel_id');
        $this->forge->addForeignKey('channel_id', 'channels', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('videos');
    }

    public function down()
    {
        $this->forge->dropTable('videos');
    }
}
