<?php

namespace App\Services;

use App\Enums\ModeCandidature;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Publication;

/**
 * Données structurées schema.org (JSON-LD) d'une offre d'emploi : JobPosting,
 * pour l'affichage dans Google pour l'emploi.
 */
class DonneesStructurees
{
    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * @return array<string, mixed>|null
     */
    public function pour(Publication $publication): ?array
    {
        if ($publication->type !== TypePublication::Emploi || $publication->publiee_le === null) {
            return null;
        }

        $detail = $publication->detailEmploi;
        $finValidite = $detail?->date_limite?->endOfDay() ?? $publication->expire_le;

        $offre = [
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $publication->titre,
            'description' => nl2br(e($publication->contenu)),
            'datePosted' => $publication->publiee_le->toIso8601String(),
            'validThrough' => $finValidite?->toIso8601String(),
            'employmentType' => $this->typeEmploi($detail?->type_contrat),
            'hiringOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => $publication->entreprise->nom ?? $publication->auteur_nom_affiche ?? 'Employeur particulier',
                'sameAs' => $publication->entreprise?->site_web,
            ]),
            'jobLocation' => [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'addressLocality' => $this->referentiels->nomCommune($publication->commune_id),
                    'addressRegion' => 'Abidjan',
                    'addressCountry' => 'CI',
                ]),
            ],
            'directApply' => $detail?->mode_candidature === ModeCandidature::Rezohelp,
            'url' => route('publications.show', $publication->slug),
        ];

        if ($detail !== null && $detail->salaire_min !== null && ! $detail->salaire_a_negocier) {
            $offre['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => $detail->devise,
                'value' => array_filter([
                    '@type' => 'QuantitativeValue',
                    'minValue' => $detail->salaire_min,
                    'maxValue' => $detail->salaire_max,
                    'unitText' => 'MONTH',
                ]),
            ];
        }

        return array_filter($offre, fn (mixed $valeur): bool => $valeur !== null);
    }

    private function typeEmploi(?TypeContrat $contrat): string
    {
        return match ($contrat) {
            TypeContrat::Cdi => 'FULL_TIME',
            TypeContrat::Cdd, TypeContrat::Interim => 'TEMPORARY',
            TypeContrat::Stage => 'INTERN',
            TypeContrat::Freelance => 'CONTRACTOR',
            default => 'OTHER',
        };
    }
}
