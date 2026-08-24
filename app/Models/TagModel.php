<?php

namespace App\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table          = 'tags';
    protected $primaryKey     = 'id';
    protected $allowedFields  = ['name'];
    protected $useTimestamps  = false;
    protected $useSoftDeletes = true;
    protected $returnType     = 'array';

    public function firstOrCreate(string $name): int
    {
        $existing = $this->where('name', $name)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $this->insert(['name' => $name]);
    }
}
