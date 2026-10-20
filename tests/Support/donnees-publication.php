<?php

/*
 * Données valides des quatre parcours de publication, partagées par les tests.
 */

use App\Enums\TypeContrat;
use App\Models\Categorie;
use App\Models\Commune;

function contenuValide(string $debut = 'Nous recherchons une personne sérieuse et ponctuelle.'): string
{
    return $debut.' Travail dans une ambiance agréable, avec une équipe accueillante, à Abidjan. Merci de répondre via Rezohelp.';
}

function donneesEmploi(array $surcharge = []): array
{
    return array_merge([
        'titre' => 'Comptable confirmé (CDI)',
        'categorie_id' => Categorie::query()->where('slug', 'emploi-finance-et-comptabilite')->value('id'),
        'commune_id' => Commune::query()->where('slug', 'plateau')->value('id'),
        'contenu' => contenuValide(),
        'employeur' => 'particulier',
        'type_contrat' => TypeContrat::Cdi->value,
        'nb_postes' => 2,
        'salaire_min' => 350000,
        'salaire_max' => 400000,
        'mode_candidature' => 'rezohelp',
    ], $surcharge);
}

function donneesServiceOffre(array $surcharge = []): array
{
    return array_merge([
        'titre' => 'Électricien disponible à Cocody',
        'categorie_id' => Categorie::query()->where('slug', 'service-electricite')->value('id'),
        'commune_id' => Commune::query()->where('slug', 'cocody')->value('id'),
        'contenu' => contenuValide('Électricien qualifié, dépannage et installation.'),
        'zones_intervention' => [Commune::query()->where('slug', 'cocody')->value('id'), Commune::query()->where('slug', 'marcory')->value('id')],
        'tarif_indicatif' => 5000,
        'unite_tarif' => 'prestation',
        'telephone_contact' => '05 06 07 08 09',
        'afficher_telephone' => '1',
        'whatsapp_meme_numero' => '1',
    ], $surcharge);
}

function donneesServiceDemande(array $surcharge = []): array
{
    return array_merge([
        'titre' => 'Cherche plombier pour une fuite à Yopougon',
        'categorie_id' => Categorie::query()->where('slug', 'service-plomberie')->value('id'),
        'commune_id' => Commune::query()->where('slug', 'yopougon')->value('id'),
        'contenu' => contenuValide('Fuite d’eau sous l’évier de la cuisine, intervention rapide souhaitée.'),
        'date_souhaitee_choix' => 'semaine',
        'budget' => 15000,
    ], $surcharge);
}

function donneesFormation(array $surcharge = []): array
{
    return array_merge([
        'titre' => 'Bootcamp développeur web',
        'categorie_id' => Categorie::query()->where('slug', 'formation-informatique-et-numerique')->value('id'),
        'commune_id' => Commune::query()->where('slug', 'marcory')->value('id'),
        'contenu' => contenuValide('Trois mois pour apprendre le développement web, du HTML à Laravel.'),
        'organisateur' => 'texte',
        'organisme' => 'Académie du Code',
        'modalite' => 'presentiel',
        'duree' => '3 mois',
        'prix' => 0,
        'certifiante' => '1',
        'mode_inscription' => 'rezohelp',
    ], $surcharge);
}
