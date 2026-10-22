<?php

namespace App\Contracts;

use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;

/**
 * Client d'IA pour la réécriture des cartes et le score de fiabilité
 * (drivers : regles — gratuit, sans IA externe —, claude, fake).
 */
interface ClientIA
{
    public function enrichir(DemandeEnrichissement $demande): ResultatEnrichissement;
}
