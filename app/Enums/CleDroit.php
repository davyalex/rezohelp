<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Catalogue des clés de droits (CONTEXTE §14.3).
 *
 * Les clés sont définies ici (le comportement en dépend) ; leurs valeurs
 * viennent de la table plan_limites. Pour un entier, -1 signifie illimité.
 */
enum CleDroit: string
{
    use AvecLibelles;

    case AlertesMax = 'alertes.max';
    case AlertesCanalSms = 'alertes.canal_sms';
    case AlertesSmsParJour = 'alertes.sms_par_jour';
    case AlertesCanalWhatsapp = 'alertes.canal_whatsapp';
    case EmploiOffresMois = 'emploi.offres_mois';
    case ServiceOffresActives = 'service.offres_actives';
    case FormationOffresActives = 'formation.offres_actives';
    case PublicationPhotosMax = 'publication.photos_max';
    case PublicationRemonteesMois = 'publication.remontees_mois';
    case PublicationDureeMaxJours = 'publication.duree_max_jours';
    case ProfilBadge = 'profil.badge';
    case StatistiquesAvancees = 'statistiques.avancees';
    case ReponsesDemandesMois = 'reponses.demandes_mois';
    case CvthequeAcces = 'cvtheque.acces';

    public const int ILLIMITE = -1;

    public function libelle(): string
    {
        return match ($this) {
            self::AlertesMax => 'Nombre maximal d’alertes',
            self::AlertesCanalSms => 'Alertes reçues par SMS',
            self::AlertesSmsParJour => 'SMS d’alerte par jour',
            self::AlertesCanalWhatsapp => 'Alertes par WhatsApp (V2)',
            self::EmploiOffresMois => 'Offres d’emploi par mois',
            self::ServiceOffresActives => 'Annonces de service actives',
            self::FormationOffresActives => 'Formations actives',
            self::PublicationPhotosMax => 'Photos par publication',
            self::PublicationRemonteesMois => 'Remontées incluses par mois',
            self::PublicationDureeMaxJours => 'Durée maximale d’une publication (jours)',
            self::ProfilBadge => 'Badge « Pro » ou « Entreprise »',
            self::StatistiquesAvancees => 'Statistiques détaillées',
            self::ReponsesDemandesMois => 'Réponses aux demandes de service par mois',
            self::CvthequeAcces => 'Accès à la CVthèque (V2)',
        };
    }

    public function estBooleen(): bool
    {
        return match ($this) {
            self::AlertesCanalSms,
            self::AlertesCanalWhatsapp,
            self::ProfilBadge,
            self::StatistiquesAvancees,
            self::CvthequeAcces => true,
            default => false,
        };
    }

    /**
     * Clé de quota : devient illimitée quand le paramètre quotas_actifs vaut false (§14.5).
     * Les canaux, plafonds de SMS, avantages inclus et durées restent soumis aux plans.
     */
    public function estQuota(): bool
    {
        return match ($this) {
            self::AlertesMax,
            self::EmploiOffresMois,
            self::ServiceOffresActives,
            self::FormationOffresActives,
            self::PublicationPhotosMax,
            self::ReponsesDemandesMois => true,
            default => false,
        };
    }
}
