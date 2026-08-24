<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChannelsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'link'             => ['type' => 'VARCHAR', 'constraint' => 500],
            'alternative_link' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'user_github'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'avatar'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addUniqueKey('link');
        $this->forge->addKey('alternative_link');
        $this->forge->createTable('channels');
    }

    public function down()
    {
        $this->forge->dropTable('channels');
    }
}
