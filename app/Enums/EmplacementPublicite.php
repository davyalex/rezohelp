<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Emplacements publicitaires du site public (D-074). Le format de chaque emplacement est fixé ici,
 * car la mise en page en dépend ; les annonces se gèrent dans l'admin (table publicites).
 *
 *  - format principal : image obligatoire, affichée par défaut ;
 *  - format secondaire : image facultative, servie par <picture> quand la requête média correspond
 *    (grand écran pour les marges, mobile pour les bannières horizontales).
 */
enum EmplacementPublicite: string
{
    use AvecLibelles;

    case MargeGauche = 'marge_gauche';
    case MargeDroite = 'marge_droite';
    case ColonneFil = 'colonne_fil';
    case FilEntreCartes = 'fil_entre_cartes';
    case DetailBas = 'detail_bas';

    public function libelle(): string
    {
        return match ($this) {
            self::MargeGauche => 'Marge gauche (grand écran)',
            self::MargeDroite => 'Marge droite (grand écran)',
            self::ColonneFil => 'Colonne de droite du fil',
            self::FilEntreCartes => 'Bannière dans le fil',
            self::DetailBas => 'Bannière de la page d’une offre',
        };
    }

    /**
     * Où et quand l'emplacement apparaît (aide de l'écran d'administration).
     */
    public function description(): string
    {
        return match ($this) {
            self::MargeGauche, self::MargeDroite => 'Bande verticale dans la marge, collée sous l’en-tête au défilement. Fil, recherche, page d’une offre, tarifs. Écrans de 1536 px et plus.',
            self::ColonneFil => 'Pavé sous « Créer une alerte » dans la colonne de droite du fil. Écrans de 1024 px et plus.',
            self::FilEntreCartes => 'Bannière entre la 8ᵉ et la 9ᵉ carte de chaque salve du fil. Tous les écrans.',
            self::DetailBas => 'Bannière sous l’annonce, avant les commentaires. Tous les écrans.',
        };
    }

    public function estVerticale(): bool
    {
        return in_array($this, [self::MargeGauche, self::MargeDroite, self::ColonneFil], true);
    }

    /**
     * Sans annonce en cours : « Annoncez ici » (emplacements verticaux), ou rien (bannières dans le contenu).
     */
    public function afficheAnnoncezIci(): bool
    {
        return $this->estVerticale();
    }

    /**
     * @return array{largeur: int, hauteur: int}
     */
    public function formatPrincipal(): array
    {
        return match ($this) {
            self::MargeGauche, self::MargeDroite => ['largeur' => 160, 'hauteur' => 600],
            self::ColonneFil => ['largeur' => 250, 'hauteur' => 250],
            self::FilEntreCartes, self::DetailBas => ['largeur' => 640, 'hauteur' => 160],
        };
    }

    /**
     * @return array{largeur: int, hauteur: int, media: string, libelle: string}|null
     */
    public function formatSecondaire(): ?array
    {
        return match ($this) {
            self::MargeGauche, self::MargeDroite => ['largeur' => 300, 'hauteur' => 600, 'media' => '(min-width: 1800px)', 'libelle' => 'très grand écran (1800 px et plus)'],
            self::FilEntreCartes, self::DetailBas => ['largeur' => 320, 'hauteur' => 100, 'media' => '(max-width: 639px)', 'libelle' => 'mobile'],
            self::ColonneFil => null,
        };
    }

    public function libelleFormats(): string
    {
        $principal = $this->formatPrincipal();
        $secondaire = $this->formatSecondaire();

        return $principal['largeur'].' × '.$principal['hauteur']
            .($secondaire ? ' (et '.$secondaire['largeur'].' × '.$secondaire['hauteur'].' pour '.$secondaire['libelle'].', facultatif)' : '');
    }
}
