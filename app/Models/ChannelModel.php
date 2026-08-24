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
        // "id" precisa de regra própria pro placeholder {id} funcionar — achado real
        // (LogicException em runtime): sem isso, o CI4 recusa o `is_unique[...,{id}]`
        // mesmo passando 'id' no array de dados do update().
        'id'       => 'permit_empty|is_natural_no_zero',
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

    /**
     * POST /channels — dedup por "contém" (case-insensitive), paridade com
     * `new RegExp(name, 'i')`/`new RegExp(link, 'i')` do `ChannelController.store` original
     * (substring, não igualdade exata — diferente de `findByNameOrLink`, usado em GET).
     */
    public function findForStoreDedup(string $name, string $link): ?array
    {
        return $this->groupStart()
            ->like('name', $name)
            ->orLike('link', $link)
            ->groupEnd()
            ->first();
    }

    /**
     * POST /video — resolve o canal do vídeo por nome OU link/alternative_link exatos.
     * Paridade com `Channel.findOne({$or:[{name:{$eq:channel}}, {link:{$eq:channel_url}},
     * {alternativeLink:{$eq:channel_url}}]})` do `VideoController.store` original — dois
     * critérios de igualdade diferentes, não o mesmo termo nos dois lados.
     */
    public function findForVideoLink(string $channelName, string $channelUrl): ?array
    {
        return $this->groupStart()
            ->where('name', $channelName)
            ->orWhere('link', $channelUrl)
            ->orWhere('alternative_link', $channelUrl)
            ->groupEnd()
            ->first();
    }

    /**
     * Substitui por completo as tags do canal (mesma semântica de sobrescrever o array
     * `tags` inteiro a cada save, como o Mongoose original faz).
     *
     * @param array<int, string> $tagNames
     */
    public function syncTags(int $channelId, array $tagNames): void
    {
        $tagModel = model(TagModel::class);
        $tagIds = [];

        foreach (array_unique(array_filter($tagNames)) as $name) {
            $tagIds[] = $tagModel->firstOrCreate($name);
        }

        $this->db->table('channel_tag')->where('channel_id', $channelId)->delete();

        if ($tagIds !== []) {
            $rows = array_map(static fn (int $tagId) => ['channel_id' => $channelId, 'tag_id' => $tagId], $tagIds);
            $this->db->table('channel_tag')->insertBatch($rows);
        }
    }
}
