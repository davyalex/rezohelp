<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Les quatre parcours de publication (CONTEXTE §11.2), valeur = segment d'URL /publier/{type}.
 */
enum ParcoursPublication: string
{
    use AvecLibelles;

    case Emploi = 'emploi';
    case ServiceOffre = 'service-offre';
    case ServiceDemande = 'service-demande';
    case Formation = 'formation';

    public function libelle(): string
    {
        return match ($this) {
            self::Emploi => 'Je recrute',
            self::ServiceOffre => 'Je propose mes services',
            self::ServiceDemande => 'Je cherche un prestataire',
            self::Formation => 'Je propose une formation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Emploi => 'Publiez une offre d’emploi, de stage ou de mission.',
            self::ServiceOffre => 'Plombier, électricien, nounou… faites-vous connaître.',
            self::ServiceDemande => 'Décrivez votre besoin, les artisans vous répondent.',
            self::Formation => 'Formation, certification, bootcamp ou apprentissage.',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Emploi => '💼',
            self::ServiceOffre => '🔧',
            self::ServiceDemande => '🙋',
            self::Formation => '🎓',
        };
    }

    public function type(): TypePublication
    {
        return match ($this) {
            self::Emploi => TypePublication::Emploi,
            self::ServiceOffre, self::ServiceDemande => TypePublication::Service,
            self::Formation => TypePublication::Formation,
        };
    }

    public function sousType(): ?SousTypeService
    {
        return match ($this) {
            self::ServiceOffre => SousTypeService::Offre,
            self::ServiceDemande => SousTypeService::Demande,
            default => null,
        };
    }

    public static function depuisPublication(TypePublication $type, ?SousTypeService $sousType): self
    {
        return match (true) {
            $type === TypePublication::Emploi => self::Emploi,
            $type === TypePublication::Formation => self::Formation,
            $sousType === SousTypeService::Demande => self::ServiceDemande,
            default => self::ServiceOffre,
        };
    }
}
