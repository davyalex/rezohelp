<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Signaux de risque d'une publication (§11.5), stockés dans publications.signaux_risque.
 * Les codes de l'IA (§12) peuvent s'y ajouter.
 */
enum SignalRisque: string
{
    use AvecLibelles;

    case ExpressionARisque = 'expression_a_risque';
    case TelephoneDansTexte = 'telephone_dans_texte';
    case WhatsappDansTexte = 'whatsapp_dans_texte';
    case SalaireHorsNorme = 'salaire_hors_norme';
    case Majuscules = 'majuscules';
    case LienRaccourci = 'lien_raccourci';

    public function libelle(): string
    {
        return match ($this) {
            self::ExpressionARisque => 'Expression à risque (frais, paiement, transfert…)',
            self::TelephoneDansTexte => 'Numéro de téléphone dans le texte',
            self::WhatsappDansTexte => 'Lien WhatsApp dans le texte',
            self::SalaireHorsNorme => 'Salaire hors norme',
            self::Majuscules => 'Texte majoritairement en majuscules',
            self::LienRaccourci => 'Lien raccourci',
        };
    }
}
