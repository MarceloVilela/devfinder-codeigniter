<?php

namespace App\Controllers;

use App\Models\ChannelModel;
use App\Models\VideoModel;

class DescriptionController extends BaseController
{
    private ChannelModel $channels;
    private VideoModel $videos;

    public function __construct()
    {
        $this->channels = model(ChannelModel::class);
        $this->videos   = model(VideoModel::class);
    }

    /**
     * GET /description/feed — texto compartilhável com os canais dos vídeos em alta.
     * Fonte de dados: Models direto, sem HTTP self-call (o Node original faz `axios.get`
     * pra própria API — desnecessário num monolito, correção de arquitetura, não de dado).
     */
    public function feed()
    {
        $channels = $this->channels->orderBy('name')->findAll();
        $trending = $this->videos->trendingQuery()->paginate(30);

        $channelNames = array_unique(array_column($trending, 'channel'));

        $hashtags = [];
        foreach ($channelNames as $name) {
            $matches = array_values(array_filter($channels, static fn (array $c) => $c['name'] === $name));
            if (count($matches) === 1) {
                foreach ($this->channels->tagsFor((int) $matches[0]['id']) as $tag) {
                    $hashtags['#' . str_replace(' ', '-', $tag)] = true;
                }
            }
        }

        $text = 'https://devfinder.vercel.app | Adicionados novos vídeos | <br /><br />';
        $text .= implode('<br />', $channelNames) . '<br /><br />';
        $text .= 'Repositório da aplicação web: https://github.com/marcelovilela/devfinder-next <br /><br />';
        $text .= 'Meu github: https://github.com/marcelovilela <br /><br />';
        $text .= implode('<br />', array_keys($hashtags));

        return $this->response->setContentType('text/html')->setBody($text);
    }

    /**
     * GET /description/category — texto compartilhável agrupado por categoria de canal.
     * Decisão desta fase (contrato original é ambíguo aqui — ver fase-0-openapi.yaml):
     * descrições unidas com `\n`, não o `Array.prototype.toString()` (vírgula sem espaço) que
     * o `res.send(array)` do Express original produz — normalização deliberada, contrato não
     * documentava um formato exato a preservar.
     */
    public function category()
    {
        $channels = $this->channels->orderBy('category')->orderBy('name')->findAll();

        $byCategory = [];
        foreach ($channels as $channel) {
            $byCategory[$channel['category']][] = $channel;
        }

        $descriptions = [];
        foreach ($byCategory as $categoryName => $items) {
            $names    = array_column($items, 'name');
            $hashtags = [];
            foreach ($items as $item) {
                foreach ($this->channels->tagsFor((int) $item['id']) as $tag) {
                    $hashtags['#' . str_replace(' ', '-', $tag)] = true;
                }
            }

            $description = "Encontre canais sobre {$categoryName} em https://devfinder.vercel.app/channel <br /><br />";
            $description .= implode('<br />', $names) . '<br /><br />';
            $description .= 'Repositório da aplicação web: https://github.com/marcelovilela/devfinder-next <br /><br />';
            $description .= 'Meu github: https://github.com/marcelovilela <br /><br />';
            $description .= implode('<br />', array_keys($hashtags));
            $description .= '<br /><br />--------------------<br /><br />';

            $descriptions[] = $description;
        }

        return $this->response->setContentType('text/html')->setBody(implode("\n", $descriptions));
    }
}
