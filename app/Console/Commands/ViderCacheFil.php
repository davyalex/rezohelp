<?php

namespace App\Console\Commands;

use App\Services\CacheFil;
use Illuminate\Console\Command;

/**
 * Incrémente fil:version : toutes les salves en cache deviennent caduques (CLAUDE.md).
 */
class ViderCacheFil extends Command
{
    protected $signature = 'fil:vider-cache';

    protected $description = 'Invalide le cache du fil (incrémente fil:version)';

    public function handle(CacheFil $cache): int
    {
        $cache->invaliderFil();
        $this->info('Cache du fil invalidé (version '.$cache->versionFil().').');

        return self::SUCCESS;
    }
}
