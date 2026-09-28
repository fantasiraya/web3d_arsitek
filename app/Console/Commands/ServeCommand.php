<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

use function Illuminate\Support\php_binary;

#[AsCommand(name: 'serve')]
class ServeCommand extends BaseServeCommand
{
    public function __construct()
    {
        parent::__construct();

        if (! in_array('PHP_INI_SCAN_DIR', static::$passthroughVariables, true)) {
            static::$passthroughVariables[] = 'PHP_INI_SCAN_DIR';
        }
    }

    /**
     * Get the full server command with increased upload and post size limits.
     *
     * @return array
     */
    protected function serverCommand()
    {
        $server = file_exists(base_path('server.php'))
            ? base_path('server.php')
            : $this->laravel->basePath('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

        return [
            php_binary(),
            '-d',
            'upload_max_filesize=100M',
            '-d',
            'post_max_size=100M',
            '-d',
            'memory_limit=512M',
            '-S',
            $this->host().':'.$this->port(),
            $server,
        ];
    }
}
