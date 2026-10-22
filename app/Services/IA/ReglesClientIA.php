<?php

namespace App\Services\IA;

use App\Actions\Publication\AnalyserRisquePublication;
use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use App\Enums\TypePublication;

/**
 * Score de fiabilité sans IA externe, gratuit (D-064) : les règles anti-arnaque des publications
 * des membres (AnalyserRisquePublication : expressions de `mots_a_risque`, numéro ou lien WhatsApp
 * dans une offre d'emploi, texte en majuscules, lien raccourci).
 *
 * - Aucun signal : SCORE_DE_BASE, au-dessus de seuil_publication_auto (70 par défaut).
 * - Chaque signal retire PENALITE_PAR_SIGNAL : un signal laisse l'annonce en modération, deux
 *   signaux la font passer sous seuil_remise_en_attente (40).
 * - Le texte de la carte n'est pas réécrit et aucune catégorie n'est devinée.
 */
class ReglesClientIA implements ClientIA
{
    public const string MODELE = 'regles';

    public const int SCORE_DE_BASE = 85;

    public const int PENALITE_PAR_SIGNAL = 30;

    public function __construct(private readonly AnalyserRisquePublication $analyser) {}

    public function enrichir(DemandeEnrichissement $demande): ResultatEnrichissement
    {
        $signaux = $this->analyser->executer(TypePublication::from($demande->type), $demande->titre, $demande->contenu);

        return new ResultatEnrichissement(
            texteCarte: '',
            categorie: null,
            scoreFiabilite: max(0, self::SCORE_DE_BASE - self::PENALITE_PAR_SIGNAL * count($signaux)),
            signauxRisque: $signaux,
            modele: self::MODELE,
            appelExterne: false,
        );
    }
}
