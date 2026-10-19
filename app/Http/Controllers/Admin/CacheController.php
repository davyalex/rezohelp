<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Services\CacheFil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * G — Boutons « Vider le cache du fil » et « Vider les référentiels » (CONTEXTE §13.3.G), via CacheFil.
 */
class CacheController extends ControleurAdmin
{
    use JournaliseActivite;

    /** Référentiels mis en cache sous ref:{nom}. */
    public const array REFERENTIELS = ['pays', 'communes', 'categories', 'parametres'];

    public function __construct(private readonly CacheFil $cache) {}

    public function viderFil(Request $requete): RedirectResponse
    {
        $this->cache->invaliderFil();
        $this->journaliser($this->admin($requete), 'cache.fil_vide');

        return back()->with('statut', 'Cache du fil vidé.');
    }

    public function viderReferentiels(Request $requete): RedirectResponse
    {
        $this->cache->oublierReferentiels(self::REFERENTIELS);
        $this->journaliser($this->admin($requete), 'cache.referentiels_vides');

        return back()->with('statut', 'Cache des référentiels vidé.');
    }
}
