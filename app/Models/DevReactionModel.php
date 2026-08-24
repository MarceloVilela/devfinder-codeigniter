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

    /**
     * Adiciona a reação se ainda não existir — idempotente (like duas vezes não duplica
     * linha nem estoura erro de unicidade), paridade com o `if (!includes) push+save` do
     * `LikeController`/`DislikeController` original. Auto-reação (dev_id === target) vira
     * no-op silencioso: a `CHECK` constraint da Fase 1 proíbe estruturalmente, e o original
     * nunca tinha essa proteção nem um fluxo real que gerasse esse caso.
     */
    public function add(int $devId, int $targetDevId, string $type): void
    {
        if ($devId === $targetDevId) {
            return;
        }

        if ($this->where('dev_id', $devId)->where('target_dev_id', $targetDevId)->where('type', $type)->first() === null) {
            $this->insert(['dev_id' => $devId, 'target_dev_id' => $targetDevId, 'type' => $type]);
        }
    }

    public function remove(int $devId, int $targetDevId, string $type): void
    {
        $this->where('dev_id', $devId)->where('target_dev_id', $targetDevId)->where('type', $type)->delete();
    }
}
