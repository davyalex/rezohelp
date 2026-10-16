<?php

namespace App\Services\IA;

use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use Illuminate\Support\Str;

/**
 * Client IA déterministe (local et tests) : le score dépend uniquement
 * des signaux d'arnaque trouvés dans le texte.
 */
class FakeClientIA implements ClientIA
{
    private const int SCORE_DE_BASE = 85;

    private const int PENALITE_PAR_SIGNAL = 30;

    /**
     * Signal => expressions (minuscules, sans accents) qui le déclenchent.
     *
     * @var array<string, list<string>>
     */
    private const array SIGNAUX = [
        'frais_demandes' => ['frais de dossier', "frais d'inscription", 'payer', 'paiement prealable'],
        'contact_whatsapp_uniquement' => ['whatsapp uniquement', 'uniquement par whatsapp', 'wa.me'],
        'transfert_argent' => ['western union', 'moneygram', 'transfert', 'mandat'],
    ];

    public function enrichir(DemandeEnrichissement $demande): ResultatEnrichissement
    {
        $texte = Str::lower(Str::ascii($demande->titre.' '.$demande->contenu));

        $signaux = [];
        foreach (self::SIGNAUX as $signal => $expressions) {
            if (Str::contains($texte, $expressions)) {
                $signaux[] = $signal;
            }
        }

        return new ResultatEnrichissement(
            texteCarte: Str::limit(Str::squish($demande->contenu), 220),
            categorie: $demande->categoriesPossibles[0] ?? null,
            scoreFiabilite: max(0, self::SCORE_DE_BASE - self::PENALITE_PAR_SIGNAL * count($signaux)),
            signauxRisque: $signaux,
            modele: 'fake',
        );
    }
}
