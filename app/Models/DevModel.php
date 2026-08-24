<?php

namespace App\Models;

use CodeIgniter\Model;

class DevModel extends Model
{
    protected $table         = 'devs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['username', 'name', 'bio', 'avatar'];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $returnType    = 'array';

    protected $validationRules = [
        'username' => 'required|is_unique[devs.username,id,{id}]',
        'name'     => 'required',
        'avatar'   => 'required',
    ];

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }
}
