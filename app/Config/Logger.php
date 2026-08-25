<?php

namespace Config;

use App\Libraries\Log\JsonFileHandler;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\ErrorlogHandler;
use CodeIgniter\Log\Handlers\HandlerInterface;

class Logger extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Error Logging Threshold
     * --------------------------------------------------------------------------
     *
     * You can enable error logging by setting a threshold over zero. The
     * threshold determines what gets logged. Any values below or equal to the
     * threshold will be logged.
     *
     * Threshold options are:
     *
     * - 0 = Disables logging, Error logging TURNED OFF
     * - 1 = Emergency Messages - System is unusable
     * - 2 = Alert Messages - Action Must Be Taken Immediately
     * - 3 = Critical Messages - Application component unavailable, unexpected exception.
     * - 4 = Runtime Errors - Don't need immediate action, but should be monitored.
     * - 5 = Warnings - Exceptional occurrences that are not errors.
     * - 6 = Notices - Normal but significant events.
     * - 7 = Info - Interesting events, like user logging in, etc.
     * - 8 = Debug - Detailed debug information.
     * - 9 = All Messages
     *
     * You can also pass an array with threshold levels to show individual error types
     *
     *     array(1, 2, 3, 8) = Emergency, Alert, Critical, and Debug messages
     *
     * For a live site you'll usually enable Critical or higher (3) to be logged otherwise
     * your log files will fill up very fast.
     *
     * @var int|list<int>
     */
    public $threshold = (ENVIRONMENT === 'production') ? 4 : 9;

    /**
     * --------------------------------------------------------------------------
     * Date Format for Logs
     * --------------------------------------------------------------------------
     *
     * Each item that is logged has an associated date. You can use PHP date
     * codes to set your own date formatting
     */
    public string $dateFormat = 'Y-m-d H:i:s';

    /**
     * --------------------------------------------------------------------------
     * Log Handlers
     * --------------------------------------------------------------------------
     *
     * The logging system supports multiple actions to be taken when something
     * is logged. This is done by allowing for multiple Handlers, special classes
     * designed to write the log to their chosen destinations, whether that is
     * a file on the getServer, a cloud-based service, or even taking actions such
     * as emailing the dev team.
     *
     * Each handler is defined by the class name used for that handler, and it
     * MUST implement the `CodeIgniter\Log\Handlers\HandlerInterface` interface.
     *
     * The value of each key is an array of configuration items that are sent
     * to the constructor of each handler. The only required configuration item
     * is the 'handles' element, which must be an array of integer log levels.
     * This is most easily handled by using the constants defined in the
     * `Psr\Log\LogLevel` class.
     *
     * Handlers are executed in the order defined in this array, starting with
     * the handler on top and continuing down.
     *
     * @var array<class-string<HandlerInterface>, array<string, int|list<string>|string>>
     */
    public array $handlers = [
        /*
         * --------------------------------------------------------------------
         * JSON File Handler (Fase 7, plan.md: "Logs estruturados (JSON)")
         * --------------------------------------------------------------------
         * Substitui o FileHandler padrão (texto livre) — 1 objeto JSON por linha em
         * writable/logs/log-{Y-m-d}.json.log, parseável por agregador de log sem regex.
         */
        JsonFileHandler::class => [
            'handles' => [
                'critical',
                'alert',
                'emergency',
                'debug',
                'error',
                'info',
                'notice',
                'warning',
            ],

            /*
             * The file system permissions to be applied on newly created log files.
             *
             * IMPORTANT: This MUST be an integer (no quotes) and you MUST use octal
             * integer notation (i.e. 0700, 0644, etc.)
             */
            'filePermissions' => 0644,

            /*
             * Logging Directory Path
             *
             * By default, logs are written to WRITEPATH . 'logs/'
             * Specify a different destination here, if desired.
             */
            'path' => '',
        ],

        /*
         * The ChromeLoggerHandler requires the use of the Chrome web browser
         * and the ChromeLogger extension. Uncomment this block to use it.
         */
        // 'CodeIgniter\Log\Handlers\ChromeLoggerHandler' => [
        //     /*
        //      * The log levels that this handler will handle.
        //      */
        //     'handles' => ['critical', 'alert', 'emergency', 'debug',
        //                   'error', 'info', 'notice', 'warning'],
        // ],

        /*
         * ErrorlogHandler (Fase 7.5, achado do troubleshooting do 500 em /v1/feed/trending
         * no Render) — o free tier do Render não tem Shell (só nos planos pagos), então o
         * arquivo de log do JsonFileHandler acima (writable/logs/*.json.log) fica inacessível
         * em produção. TYPE_SAPI manda pro error log do php-fpm, que o supervisord.conf já
         * redireciona pro stderr do container — e esse sim aparece no stream de logs do
         * Render, sem precisar de Shell. Ver specs/deploy/fase-7-deploy-render.md,
         * "Troubleshooting — 500 em /v1/feed/trending".
         */
        ErrorlogHandler::class => [
            'handles' => ['critical', 'alert', 'emergency', 'error'],
            'messageType' => ErrorlogHandler::TYPE_SAPI,
        ],
    ];
}
