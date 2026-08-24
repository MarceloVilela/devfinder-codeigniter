<?php

namespace App\Models;

use CodeIgniter\Model;

class ChannelReactionModel extends Model
{
    protected $table         = 'channel_reactions';
    protected $useTimestamps = false;
    protected $returnType    = 'array';
    protected $allowedFields = ['dev_id', 'channel_id', 'type'];

    /** @return array<int, int> ids dos channels alvo de um tipo de reação */
    public function targetIdsFor(int $devId, string $type): array
    {
        // ver DevReactionModel::targetIdsFor — mesmo motivo do array_map('intval', ...).
        return array_map('intval', array_column(
            $this->select('channel_id')
                ->where('dev_id', $devId)
                ->where('type', $type)
                ->findAll(),
            'channel_id'
        ));
    }
}
