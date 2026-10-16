<?php

namespace Database\Seeders;

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Enums\TypeProduit;
use App\Models\Plan;
use App\Models\Produit;
use Illuminate\Database\Seeder;

/**
 * Grille initiale des plans et produits (CONTEXTE §14.4), réglée pour la phase
 * « Lancement » (§14.12). Les valeurs se modifient ensuite dans l'admin ;
 * un plan ou un produit existant n'est jamais écrasé.
 */
class PlansSeeder extends Seeder
{
    private const int ILLIMITE = CleDroit::ILLIMITE;

    public function run(): void
    {
        foreach ($this->plans() as $code => $plan) {
            $this->enregistrerPlan($code, $plan);
        }

        foreach ($this->produits() as $code => $produit) {
            Produit::query()->firstOrCreate(['code' => $code], $produit);
        }
    }

    /**
     * @param  array{attributs: array<string, mixed>, limites: array<string, int>}  $plan
     */
    private function enregistrerPlan(string $code, array $plan): void
    {
        $modele = Plan::query()->firstOrCreate(['code' => $code], $plan['attributs']);

        if (! $modele->wasRecentlyCreated) {
            return;
        }

        foreach ($plan['limites'] as $cle => $valeur) {
            $modele->limites()->create(['cle' => CleDroit::from($cle), 'valeur' => (string) $valeur]);
        }
    }

    /**
     * @return array<string, array{attributs: array<string, mixed>, limites: array<string, int>}>
     */
    private function plans(): array
    {
        return [
            'membre_gratuit' => [
                'attributs' => $this->attributs('Gratuit', CibleOffre::Membre, null, 0, 0, 0, true, true, 1,
                    'Alertes dans votre espace, candidatures et publication de vos besoins.'),
                'limites' => [
                    'alertes.max' => 10,
                    'alertes.canal_sms' => 0,
                    'alertes.sms_par_jour' => 0,
                    'emploi.offres_mois' => 1,
                    'publication.photos_max' => 2,
                    'publication.duree_max_jours' => 60,
                    'reponses.demandes_mois' => 3,
                ],
            ],
            'alertes_sms_7j' => [
                'attributs' => $this->attributs('Alertes SMS 7 jours', CibleOffre::Membre, 'alertes_sms', 300, 7, 14, false, true, 2,
                    'Recevez vos alertes par SMS pendant 7 jours.'),
                'limites' => ['alertes.canal_sms' => 1, 'alertes.sms_par_jour' => 5],
            ],
            'alertes_sms_30j' => [
                'attributs' => $this->attributs('Alertes SMS 30 jours', CibleOffre::Membre, 'alertes_sms', 1000, 30, 14, false, true, 3,
                    'Recevez vos alertes par SMS pendant 30 jours.'),
                'limites' => ['alertes.canal_sms' => 1, 'alertes.sms_par_jour' => 5],
            ],
            'recruteur_gratuit' => [
                'attributs' => $this->attributs('Gratuit', CibleOffre::Recruteur, null, 0, 0, 0, true, true, 1,
                    'Publiez vos offres d’emploi au nom de votre entreprise.'),
                'limites' => [
                    'emploi.offres_mois' => 3,
                    'publication.photos_max' => 2,
                    'publication.duree_max_jours' => 60,
                ],
            ],
            'entreprise_30j' => [
                'attributs' => $this->attributs('Entreprise', CibleOffre::Recruteur, 'entreprise', 50000, 30, 14, false, false, 2,
                    'Offres illimitées, badge Entreprise, statistiques et remontées incluses.'),
                'limites' => [
                    'emploi.offres_mois' => self::ILLIMITE,
                    'publication.photos_max' => 5,
                    'publication.duree_max_jours' => 90,
                    'publication.remontees_mois' => 4,
                    'profil.badge' => 1,
                    'statistiques.avancees' => 1,
                ],
            ],
            'artisan_gratuit' => [
                'attributs' => $this->attributs('Gratuit', CibleOffre::Artisan, null, 0, 0, 0, true, true, 1,
                    'Proposez vos services et répondez aux demandes des clients.'),
                'limites' => [
                    'service.offres_actives' => 3,
                    'publication.photos_max' => 4,
                    'publication.duree_max_jours' => 60,
                    'reponses.demandes_mois' => 5,
                ],
            ],
            'artisan_pro_30j' => [
                'attributs' => $this->attributs('Artisan Pro', CibleOffre::Artisan, 'artisan_pro', 3000, 30, 30, false, false, 2,
                    'Plus d’annonces et de photos, badge Pro, réponses illimitées. Premier mois offert.'),
                'limites' => [
                    'service.offres_actives' => 10,
                    'publication.photos_max' => 10,
                    'publication.duree_max_jours' => 90,
                    'publication.remontees_mois' => 4,
                    'profil.badge' => 1,
                    'statistiques.avancees' => 1,
                    'reponses.demandes_mois' => self::ILLIMITE,
                ],
            ],
            'organisme_gratuit' => [
                'attributs' => $this->attributs('Gratuit', CibleOffre::Organisme, null, 0, 0, 0, true, true, 1,
                    'Publiez une formation à la fois.'),
                'limites' => [
                    'formation.offres_actives' => 1,
                    'publication.photos_max' => 2,
                    'publication.duree_max_jours' => 60,
                ],
            ],
            'organisme_pro_30j' => [
                'attributs' => $this->attributs('Organisme Pro', CibleOffre::Organisme, 'organisme_pro', 15000, 30, 14, false, false, 2,
                    'Formations illimitées, badge, statistiques et remontées incluses.'),
                'limites' => [
                    'formation.offres_actives' => self::ILLIMITE,
                    'publication.photos_max' => 5,
                    'publication.duree_max_jours' => 90,
                    'publication.remontees_mois' => 4,
                    'profil.badge' => 1,
                    'statistiques.avancees' => 1,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributs(
        string $nom,
        CibleOffre $cible,
        ?string $famille,
        int $prix,
        int $duree,
        int $essai,
        bool $estDefaut,
        bool $actif,
        int $ordre,
        string $description,
    ): array {
        return [
            'nom' => $nom,
            'description' => $description,
            'cible' => $cible,
            'famille' => $famille,
            'prix_fcfa' => $prix,
            'duree_jours' => $duree,
            'duree_essai_jours' => $essai,
            'est_defaut' => $estDefaut,
            'actif' => $actif,
            'visible' => true,
            'ordre' => $ordre,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function produits(): array
    {
        return [
            'offre_emploi_unite' => [
                'nom' => 'Offre d’emploi supplémentaire',
                'description' => '+1 offre d’emploi au-delà du quota du mois.',
                'cible' => CibleOffre::Recruteur,
                'type' => TypeProduit::OffreSupplementaire,
                'prix_fcfa' => 3000,
                'duree_jours' => 30,
                'actif' => false,
                'ordre' => 1,
            ],
            'remontee' => [
                'nom' => 'Remontée',
                'description' => 'Remet votre publication en tête du fil.',
                'cible' => CibleOffre::Artisan,
                'type' => TypeProduit::Remontee,
                'prix_fcfa' => 500,
                'duree_jours' => null,
                'actif' => true,
                'ordre' => 2,
            ],
            'une_commune_7j' => [
                'nom' => 'À la une dans la commune (7 jours)',
                'description' => 'En tête des résultats de votre commune et de votre catégorie.',
                'cible' => CibleOffre::Artisan,
                'type' => TypeProduit::UneCommune,
                'prix_fcfa' => 2500,
                'duree_jours' => 7,
                'actif' => false,
                'ordre' => 3,
            ],
            'sponsorise_fil_7j' => [
                'nom' => 'Sponsorisé dans le fil (7 jours)',
                'description' => 'Emplacement « Sponsorisé » dans le fil général.',
                'cible' => CibleOffre::Recruteur,
                'type' => TypeProduit::SponsoriseFil,
                'prix_fcfa' => 7500,
                'duree_jours' => 7,
                'actif' => false,
                'ordre' => 4,
            ],
        ];
    }
}
