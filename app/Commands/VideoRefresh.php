<?php

namespace App\Commands;

use App\Libraries\VideoIngestor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\VideoRefresh as VideoRefreshConfig;

/**
 * Ingestão em lote de vídeos — porta de `devfinder-api/src/task.ts`: busca candidatos num
 * bin do JSONBin.io (mesmo bin usado em produção pelo original) e ingere via VideoIngestor
 * (o mesmo serviço usado por `POST /video/refresh`, ver App\Controllers\VideoController::
 * refresh() e specs/fase-6-ingestao-lote.md). Substitui `video-refresh.yml` (GitHub Actions)
 * do original — aqui, agendado via cron nativo na VM do deploy real (ver ../plan.md, Fase 6).
 *
 * Uso: php spark video:refresh
 */
class VideoRefresh extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'video:refresh';
    protected $description = 'Ingestão em lote de vídeos a partir do bin do JSONBin.io (videoRefresh.jsonbin* no .env).';

    public function run(array $params)
    {
        $config = config(VideoRefreshConfig::class);

        if ($config->jsonbinApiKey === '' || $config->jsonbinIdSubs === '') {
            CLI::write(
                'videoRefresh.jsonbinApiKey / videoRefresh.jsonbinIdSubs não configurados no .env — nada a fazer.',
                'yellow'
            );

            return;
        }

        $response = service('curlrequest')->get(
            "https://api.jsonbin.io/v3/b/{$config->jsonbinIdSubs}",
            [
                'headers'     => ['Content-Type' => 'application/json', 'X-Master-Key' => $config->jsonbinApiKey],
                'http_errors' => false,
            ]
        );

        $body   = json_decode((string) $response->getBody(), true);
        $record = $body['record'] ?? null;

        if (! is_array($record)) {
            CLI::error('JSONBin retornou formato inesperado: ' . json_encode($body));

            return;
        }

        CLI::write('JSONBin: ' . count($record) . ' candidato(s) encontrado(s).');

        // O bin usa `channel_name` (não `channel`) — mesma divergência de nome de campo já
        // registrada em ../../serverless/specs/fase-6-ingestao-lote.md ("Achado não
        // antecipado pelo plano"): o contrato HTTP (AddVideo, fase-0-openapi.yaml) usa
        // `channel`, o candidato do JSONBin usa `channel_name`. Normalizado aqui, na borda,
        // pra VideoIngestor::ingest() receber sempre o mesmo shape.
        $candidates = array_map(static fn (array $item) => [
            'title'       => $item['title'] ?? '',
            'url'         => $item['url'] ?? '',
            'channel'     => $item['channel_name'] ?? '',
            'channel_url' => $item['channel_url'] ?? '',
            'thumbnail'   => $item['thumbnail'] ?? '',
        ], $record);

        $result = (new VideoIngestor())->ingest($candidates);

        CLI::write(sprintf(
            'Adicionados: %d | Já existiam: %d | Erros: %d',
            count($result['videosAdded']),
            count($result['videosFounded']),
            count($result['errors'])
        ), 'green');

        foreach ($result['errors'] as $error) {
            CLI::write('  - ' . ($error['errorMessage'] ?? json_encode($error)), 'yellow');
        }
    }
}
