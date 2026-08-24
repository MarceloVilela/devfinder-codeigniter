<?php

namespace App\Commands;

use App\Models\ChannelModel;
use App\Models\ChannelReactionModel;
use App\Models\DevReactionModel;
use App\Models\VideoModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Popula devs/channels/videos a partir do dump real (`specs/seed/omni8.{devs,channels,videos}.json`,
 * Mongo Extended JSON) — equivalente a `../serverless/api/scripts/seed-local.ts`, adaptado pro
 * alvo relacional (MySQL, ver specs/fase-1-data-model.md): sem slug — channels/devs usam o `id`
 * auto_increment nativo — e dedup de channels é só por `name` (única colisão real do dump,
 * "CaquiCoders" 4x, é também a única colisão de `link`).
 *
 * Uso: php spark seed:local (dentro do container: docker compose exec app php spark seed:local)
 */
class SeedLocal extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'seed:local';
    protected $description = 'Popula devs/channels/videos a partir de specs/seed/*.json (dump real, gitignored).';

    public function run(array $params)
    {
        $seedDir = ROOTPATH . 'specs' . DIRECTORY_SEPARATOR . 'seed' . DIRECTORY_SEPARATOR;

        $rawDevs     = $this->loadJson($seedDir, 'omni8.devs.json');
        $rawChannels = $this->loadJson($seedDir, 'omni8.channels.json');
        $rawVideos   = $this->loadJson($seedDir, 'omni8.videos.json');

        if ($rawDevs === null || $rawChannels === null || $rawVideos === null) {
            return;
        }

        $db = Database::connect();

        CLI::write('Limpando tabelas...', 'yellow');
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['channel_tag', 'dev_reactions', 'channel_reactions', 'videos', 'tags', 'channels', 'devs'] as $table) {
            $db->table($table)->truncate();
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        [$channelIdByOid, $discarded] = $this->seedChannels($db, $rawChannels);
        CLI::write('Channels: ' . count($channelIdByOid) . ' inseridos.', 'green');
        foreach ($discarded as $row) {
            CLI::write("  - descartado por colisao de name: \"{$row['name']}\" _id={$row['oid']}", 'yellow');
        }

        $devIdByOid = $this->seedDevs($db, $rawDevs);
        CLI::write('Devs: ' . count($devIdByOid) . ' inseridos.', 'green');

        $this->seedReactions($rawDevs, $devIdByOid, $channelIdByOid);
        CLI::write('Reações (likes/dislikes/follow/ignore) processadas.', 'green');

        $skipped = $this->seedVideos($db, $rawVideos, $channelIdByOid);
        CLI::write('Videos: ' . (count($rawVideos) - $skipped) . ' inseridos (' . $skipped . ' descartado(s)).', 'green');
    }

    private function loadJson(string $seedDir, string $filename): ?array
    {
        $path = $seedDir . $filename;

        if (! is_file($path)) {
            CLI::error(
                "{$filename} nao encontrado em specs/seed/ — esses dumps sao gitignored (dados " .
                'reais de terceiros, LGPD) e nao vem no clone. Ver specs/seed/README.md pra ' .
                'reconstituir com dado sintetico proprio.'
            );

            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Dedup por `name` (achado da Fase 1: unica colisao real do dump — "CaquiCoders" 4x — e
     * tambem a unica colisao de `link`, mantem 1 por name, criterio updatedAt mais recente).
     *
     * @return array{0: array<string, int>, 1: array<int, array{name: string, oid: string}>}
     */
    private function seedChannels($db, array $rawChannels): array
    {
        $winnerByName = [];
        foreach ($rawChannels as $channel) {
            $name     = $channel['name'];
            $existing = $winnerByName[$name] ?? null;

            if ($existing === null || $channel['updatedAt']['$date'] > $existing['updatedAt']['$date']) {
                $winnerByName[$name] = $channel;
            }
        }

        $discarded = [];
        foreach ($rawChannels as $channel) {
            if ($winnerByName[$channel['name']]['_id']['$oid'] !== $channel['_id']['$oid']) {
                $discarded[] = ['name' => $channel['name'], 'oid' => $channel['_id']['$oid']];
            }
        }

        $channelIdByOid = [];
        $channelModel   = model(ChannelModel::class);

        foreach ($winnerByName as $channel) {
            $id = $db->table('channels')->insert([
                'name'             => $channel['name'],
                'link'             => $channel['link'],
                'alternative_link' => $channel['alternativeLink'] ?? null,
                'user_github'      => $channel['userGithub'] ?? null,
                'description'      => $channel['description'] ?? null,
                'category'         => trim($channel['category']),
                'avatar'           => $channel['avatar'] ?? null,
                'created_at'       => $this->toDatetime($channel['createdAt']['$date']),
                'updated_at'       => $this->toDatetime($channel['updatedAt']['$date']),
            ]) ? $db->insertID() : null;

            if ($id === null) {
                continue;
            }

            $channelIdByOid[$channel['_id']['$oid']] = $id;

            if (! empty($channel['tags'])) {
                $channelModel->syncTags($id, $channel['tags']);
            }
        }

        return [$channelIdByOid, $discarded];
    }

    /** @return array<string, int> mapa _id (Mongo) => devs.id */
    private function seedDevs($db, array $rawDevs): array
    {
        $devIdByOid = [];

        foreach ($rawDevs as $dev) {
            $id = $db->table('devs')->insert([
                'username'   => strtolower($dev['user']),
                'name'       => $dev['name'],
                'bio'        => $dev['bio'] ?? null,
                'avatar'     => $dev['avatar'],
                'created_at' => $this->toDatetime($dev['createdAt']['$date']),
                'updated_at' => $this->toDatetime($dev['updatedAt']['$date']),
            ]) ? $db->insertID() : null;

            if ($id !== null) {
                $devIdByOid[$dev['_id']['$oid']] = $id;
            }
        }

        return $devIdByOid;
    }

    /**
     * @param array<string, int> $devIdByOid
     * @param array<string, int> $channelIdByOid
     */
    private function seedReactions(array $rawDevs, array $devIdByOid, array $channelIdByOid): void
    {
        $devReactionModel     = model(DevReactionModel::class);
        $channelReactionModel = model(ChannelReactionModel::class);

        foreach ($rawDevs as $dev) {
            $devId = $devIdByOid[$dev['_id']['$oid']] ?? null;
            if ($devId === null) {
                continue;
            }

            foreach (['likes' => 'like', 'deslikes' => 'dislike'] as $field => $type) {
                foreach ($dev[$field] ?? [] as $ref) {
                    $targetId = $devIdByOid[$ref['$oid']] ?? null;
                    if ($targetId !== null) {
                        $devReactionModel->add($devId, $targetId, $type);
                    }
                }
            }

            foreach (['follow' => 'follow', 'ignore' => 'ignore'] as $field => $type) {
                foreach ($dev[$field] ?? [] as $ref) {
                    $channelId = $channelIdByOid[$ref['$oid']] ?? null;
                    if ($channelId !== null) {
                        $channelReactionModel->add($devId, $channelId, $type);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, int> $channelIdByOid
     *
     * @return int quantidade de videos descartados (sem youtube_id extraivel ou canal nao resolvido)
     */
    private function seedVideos($db, array $rawVideos, array $channelIdByOid): int
    {
        $skipped = 0;

        foreach ($rawVideos as $video) {
            $youtubeId = VideoModel::extractYoutubeId($video['url']);
            $channelId = $channelIdByOid[$video['channel_id']['$oid']] ?? null;

            if ($youtubeId === null || $channelId === null) {
                $skipped++;
                CLI::write("  - video descartado (sem youtube_id ou canal nao resolvido): \"{$video['title']}\"", 'yellow');

                continue;
            }

            $db->table('videos')->insert([
                'youtube_id'   => $youtubeId,
                'title'        => $video['title'],
                'url'          => $video['url'],
                'channel_id'   => $channelId,
                'thumbnail'    => $video['thumbnail'] ?? '',
                'viewnum'      => null,
                'published_at' => null,
                'created_at'   => $this->toDatetime($video['createdAt']['$date']),
                'updated_at'   => $this->toDatetime($video['updatedAt']['$date']),
            ]);
        }

        return $skipped;
    }

    private function toDatetime(string $isoDate): string
    {
        return (new \DateTimeImmutable($isoDate))->format('Y-m-d H:i:s');
    }
}
