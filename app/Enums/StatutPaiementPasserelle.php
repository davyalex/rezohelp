<?php

namespace App\Enums;

/**
 * Statut d'un paiement tel que renvoyé par la passerelle lors de la revérification.
 */
enum StatutPaiementPasserelle: string
{
    case EnAttente = 'en_attente';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
}
