<?php

namespace App\Observers;

use App\Services\CacheFil;

/**
 * Un plan ou une limite de plan change dans l'admin : tous les droits en cache
 * deviennent obsolètes (incrément de droits:version), l'effet est immédiat.
 */
class PlanObserver
{
    public function __construct(private readonly CacheFil $cache) {}

    public function saved(): void
    {
        $this->cache->invaliderDroits();
    }

    public function deleted(): void
    {
        $this->cache->invaliderDroits();
    }
}
