<?php

namespace App\Presenters;

use App\Models\ChannelReactionModel;
use App\Models\DevReactionModel;

/**
 * Serialização de Dev pro contrato público (fase-0-openapi.yaml) — extraído de
 * DevController porque MeController (Fase 4) precisa do mesmo formato exato.
 */
class DevPresenter
{
    public static function present(array $dev): array
    {
        $devReactions = model(DevReactionModel::class);
        $channelReactions = model(ChannelReactionModel::class);

        return [
            '_id'       => (int) $dev['id'],
            'name'      => $dev['name'],
            'user'      => $dev['username'],
            'bio'       => $dev['bio'] ?? '',
            'avatar'    => $dev['avatar'],
            'likes'     => $devReactions->targetIdsFor((int) $dev['id'], 'like'),
            'deslikes'  => $devReactions->targetIdsFor((int) $dev['id'], 'dislike'),
            'follow'    => $channelReactions->targetIdsFor((int) $dev['id'], 'follow'),
            'ignore'    => $channelReactions->targetIdsFor((int) $dev['id'], 'ignore'),
            'createdAt' => to_iso8601($dev['created_at']),
            'updatedAt' => to_iso8601($dev['updated_at']),
        ];
    }
}
