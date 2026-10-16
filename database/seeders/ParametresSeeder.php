<?php

namespace Database\Seeders;

use App\Enums\TypeParametre;
use App\Models\Parametre;
use Illuminate\Database\Seeder;

/**
 * Paramètres réglables dans l'admin (CONTEXTE §11, §13.G, §14).
 * Une valeur déjà modifiée dans l'admin n'est jamais écrasée.
 */
class ParametresSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->parametres() as $cle => [$type, $valeur, $libelle]) {
            $parametre = Parametre::query()->firstOrNew(['cle' => $cle]);
            $parametre->fill(['type' => $type, 'libelle' => $libelle]);

            if (! $parametre->exists) {
                $parametre->valeur = is_array($valeur) ? (string) json_encode($valeur, JSON_UNESCAPED_UNICODE) : (string) $valeur;
            }

            $parametre->save();
        }
    }

    /**
     * @return array<string, array{0: TypeParametre, 1: int|string|array<array-key, mixed>, 2: string}>
     */
    private function parametres(): array
    {
        return [
            // Modération et confiance (§11.5, §11.6, §12, §13)
            'seuil_publication_auto' => [TypeParametre::Entier, 70, 'Score de fiabilité minimal pour une publication automatique'],
            'seuil_remise_en_attente' => [TypeParametre::Entier, 40, 'Sous ce score, une publication en ligne repasse en attente'],
            'seuil_masquage_signalements' => [TypeParametre::Entier, 3, 'Signalements « arnaque » non traités avant masquage automatique'],
            'publications_pour_fiable' => [TypeParametre::Entier, 3, 'Publications validées pour qu’un auteur devienne fiable'],
            'rejets_arnaque_pour_restreint' => [TypeParametre::Entier, 2, 'Rejets pour arnaque avant restriction du compte'],
            'plafonds_salaire' => [TypeParametre::Json, [
                'cdi' => 3000000,
                'cdd' => 3000000,
                'stage' => 500000,
                'interim' => 1500000,
                'freelance' => 5000000,
                'autre' => 3000000,
            ], 'Salaire mensuel maximal plausible par type de contrat (FCFA)'],
            'mots_a_risque' => [TypeParametre::Json, [
                'frais de dossier',
                'frais d\'inscription',
                'payer',
                'paiement préalable',
                'western union',
                'moneygram',
                'transfert',
                'mandat',
                'caution',
            ], 'Expressions à risque qui forcent la modération'],
            'texte_bandeau_prevention' => [TypeParametre::Texte, 'Aucun recruteur sérieux ne demande d’argent pour un emploi.', 'Bandeau de prévention des offres d’emploi'],

            // Publication par les membres (§11)
            'jours_expiration_defaut' => [TypeParametre::Entier, 30, 'Durée de publication par défaut (jours)'],
            'max_publications_par_jour' => [TypeParametre::Entier, 5, 'Publications maximales par compte et par jour'],
            'max_envois_par_heure' => [TypeParametre::Entier, 10, 'Envois de formulaire de publication maximal par heure'],
            'jours_doublon' => [TypeParametre::Entier, 7, 'Fenêtre de détection des doublons (jours)'],
            'seuil_similarite_doublon' => [TypeParametre::Entier, 90, 'Similarité de titre considérée comme doublon (%)'],
            'max_prolongations' => [TypeParametre::Entier, 3, 'Nombre maximal de prolongations d’une publication'],
            'jours_prolongation' => [TypeParametre::Entier, 30, 'Durée d’une prolongation (jours)'],

            // Monétisation (§14)
            'quotas_actifs' => [TypeParametre::Booleen, 0, 'Quotas des plans appliqués (désactivés au lancement)'],
            'jours_grace' => [TypeParametre::Entier, 3, 'Délai de grâce après la fin d’un abonnement (jours)'],
            'taux_tva' => [TypeParametre::Entier, 18, 'Taux de TVA appliqué aux commandes (%)'],
            'frequence_sponsorise' => [TypeParametre::Entier, 8, 'Une carte sponsorisée toutes les N cartes du fil'],
            'max_cartes_une' => [TypeParametre::Entier, 2, 'Cartes « À la une » affichées au-dessus d’un fil filtré'],
            'heures_entre_remontees' => [TypeParametre::Entier, 24, 'Délai minimal entre deux remontées d’une publication (heures)'],
            'mentions_legales_recu' => [TypeParametre::Html, '<p>Resohelph — raison sociale, RCCM, NCC et adresse à compléter.</p>', 'Mentions légales imprimées sur les reçus'],

            // Contenus et maintenance
            'contenu_cgu' => [TypeParametre::Html, '<p>Conditions générales d’utilisation à compléter.</p>', 'Conditions générales d’utilisation'],
            'contenu_confidentialite' => [TypeParametre::Html, '<p>Politique de confidentialité à compléter (loi n° 2013-450, ARTCI).</p>', 'Politique de confidentialité'],
            'jours_purge_recherches' => [TypeParametre::Entier, 180, 'Conservation du journal des recherches (jours)'],
            'jours_purge_publications' => [TypeParametre::Entier, 90, 'Délai avant purge définitive des publications supprimées (jours)'],
        ];
    }
}
