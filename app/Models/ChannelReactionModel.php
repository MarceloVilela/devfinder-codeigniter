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

    /** Idempotente — ver DevReactionModel::add() para o raciocínio completo. */
    public function add(int $devId, int $channelId, string $type): void
    {
        if ($this->where('dev_id', $devId)->where('channel_id', $channelId)->where('type', $type)->first() === null) {
            $this->insert(['dev_id' => $devId, 'channel_id' => $channelId, 'type' => $type]);
        }
    }

    public function remove(int $devId, int $channelId, string $type): void
    {
        $this->where('dev_id', $devId)->where('channel_id', $channelId)->where('type', $type)->delete();
    }
}
