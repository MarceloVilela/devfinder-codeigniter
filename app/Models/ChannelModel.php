<?php

namespace App\Models;

use CodeIgniter\Model;

class ChannelModel extends Model
{
    protected $table          = 'channels';
    protected $primaryKey     = 'id';
    protected $allowedFields  = [
        'name', 'link', 'alternative_link', 'user_github', 'description', 'category', 'avatar',
    ];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $returnType     = 'array';

    protected $validationRules = [
        'name'     => 'required|is_unique[channels.name,id,{id}]',
        'link'     => 'required|is_unique[channels.link,id,{id}]',
        'category' => 'required',
    ];

    /**
     * GET /channels/{searchQuery} — lookup exato por name OU link (ver
     * specs/fase-1-data-model.md, decisão 2: nunca precisa de "contains").
     */
    public function findByNameOrLink(string $searchQuery): ?array
    {
        return $this->groupStart()
            ->where('name', $searchQuery)
            ->orWhere('link', $searchQuery)
            ->orWhere('alternative_link', $searchQuery)
            ->groupEnd()
            ->first();
    }

    /**
     * @return array<int, string> nomes de tags, ordenados
     */
    public function tagsFor(int $channelId): array
    {
        return array_column(
            $this->db->table('channel_tag')
                ->select('tags.name')
                ->join('tags', 'tags.id = channel_tag.tag_id')
                ->where('channel_tag.channel_id', $channelId)
                ->orderBy('tags.name')
                ->get()
                ->getResultArray(),
            'name'
        );
    }
}
