<?php

namespace App\Models;

use CodeIgniter\Model;

class DevReactionModel extends Model
{
    protected $table         = 'dev_reactions';
    protected $useTimestamps = false;
    protected $returnType    = 'array';
    protected $allowedFields = ['dev_id', 'target_dev_id', 'type'];

    /** @return array<int, int> ids dos devs alvo de um tipo de reação */
    public function targetIdsFor(int $devId, string $type): array
    {
        // array_map(intval...) — MySQLi devolve tudo como string; sem isso o _id (int) do
        // Dev/Channel não bateria por tipo com o valor dentro de likes/follow/etc no JS
        // (`.includes()` é ===), quebrando a comparação que devfinder-next já faz hoje.
        return array_map('intval', array_column(
            $this->select('target_dev_id')
                ->where('dev_id', $devId)
                ->where('type', $type)
                ->findAll(),
            'target_dev_id'
        ));
    }
}
