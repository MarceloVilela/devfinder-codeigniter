<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 190],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'bio'        => ['type' => 'TEXT', 'null' => true],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('devs');
    }

    public function down()
    {
        $this->forge->dropTable('devs');
    }
}
