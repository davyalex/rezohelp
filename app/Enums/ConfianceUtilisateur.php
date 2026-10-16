<?php

namespace App\Enums;

/**
 * Niveau de confiance d'un compte (CONTEXTE §11.6).
 */
enum ConfianceUtilisateur: string
{
    case Nouveau = 'nouveau';
    case Fiable = 'fiable';
    case Restreint = 'restreint';
}
