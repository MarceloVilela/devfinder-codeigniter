<?php

namespace App\Libraries\Log;

use CodeIgniter\Log\Handlers\BaseHandler;

/**
 * Log estruturado — 1 objeto JSON por linha (JSON Lines), parseável por agregadores de log
 * (CloudWatch/Loki/ELK/etc) sem parsing de regex, diferente do FileHandler padrão do CI4
 * (texto livre: `ERROR - 2026-... --> mensagem`). Fase 7, plan.md: "Logs estruturados (JSON)
 * via handler de log do CodeIgniter 4".
 *
 * `$message` já chega interpolado (placeholders `{chave}` do PSR-3 já substituídos por
 * `Logger::interpolate()` antes de qualquer handler rodar) — o campo "estruturado" aqui é o
 * envelope (timestamp/level/message), não um contexto chave-valor separado.
 */
class JsonFileHandler extends BaseHandler
{
    private string $path;
    private int $filePermissions;

    /**
     * @param array{handles?: list<string>, path?: string, filePermissions?: int} $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->path = ($config['path'] ?? '') !== '' ? $config['path'] : WRITEPATH . 'logs/';
        $this->filePermissions = $config['filePermissions'] ?? 0644;
    }

    public function handle($level, $message): bool
    {
        $filepath = $this->path . 'log-' . date('Y-m-d') . '.json.log';
        $newfile  = ! is_file($filepath);

        $line = json_encode([
            'timestamp' => date($this->dateFormat),
            'level'     => $level,
            'message'   => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        if (! $fp = @fopen($filepath, 'ab')) {
            return false;
        }

        flock($fp, LOCK_EX);
        $result = fwrite($fp, $line);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($newfile) {
            chmod($filepath, $this->filePermissions);
        }

        return $result !== false;
    }
}
