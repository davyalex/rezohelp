<?php

namespace App\Services;

use App\Enums\Modalite;
use App\Enums\SousTypeService;
use App\Enums\TypePublication;
use App\Enums\UniteTarif;
use App\Models\DetailEmploi;
use App\Models\DetailFormation;
use App\Models\DetailService;
use App\Models\Publication;
use App\Support\Montant;

/**
 * Informations clés affichées en puces sur la carte (contrat, salaire, tarif,
 * durée, prix). Calculées une fois, à l'enregistrement, puis stockées dans
 * publications.faits_cles : le fil ne lit jamais les tables details_*.
 */
class FaitsCles
{
    public const int MAXIMUM = 4;

    /**
     * @return list<string>
     */
    public function calculer(Publication $publication, DetailEmploi|DetailService|DetailFormation|null $detail): array
    {
        $faits = match (true) {
            $detail instanceof DetailEmploi => $this->emploi($detail),
            $detail instanceof DetailService && $publication->sous_type === SousTypeService::Demande => $this->demande($detail),
            $detail instanceof DetailService => $this->offreService($detail),
            $detail instanceof DetailFormation => $this->formation($detail),
            default => [],
        };

        return array_slice(array_values(array_filter($faits)), 0, self::MAXIMUM);
    }

    /**
     * Calcule et enregistre les champs dénormalisés tirés des détails.
     */
    public function appliquer(Publication $publication): void
    {
        $detail = match ($publication->type) {
            TypePublication::Emploi => $publication->detailEmploi,
            TypePublication::Service => $publication->detailService,
            TypePublication::Formation => $publication->detailFormation,
        };

        $publication->faits_cles = $this->calculer($publication, $detail);
        $publication->type_contrat = $detail instanceof DetailEmploi ? $detail->type_contrat : null;
    }

    /**
     * @return list<string|null>
     */
    private function emploi(DetailEmploi $detail): array
    {
        return [
            $detail->type_contrat?->libelle(),
            $detail->salaire_a_negocier ? 'Salaire à négocier' : Montant::fourchette($detail->salaire_min, $detail->salaire_max),
            $detail->nb_postes > 1 ? $detail->nb_postes.' postes' : null,
            $detail->date_limite !== null ? 'Jusqu’au '.$detail->date_limite->translatedFormat('j M') : null,
        ];
    }

    /**
     * @return list<string|null>
     */
    private function offreService(DetailService $detail): array
    {
        $tarif = match (true) {
            $detail->unite_tarif === UniteTarif::Devis || $detail->tarif_indicatif === null => 'Sur devis',
            $detail->unite_tarif !== null => Montant::fcfa($detail->tarif_indicatif).' '.$detail->unite_tarif->libelle(),
            default => Montant::fcfa($detail->tarif_indicatif),
        };

        return [$detail->metier, $tarif, $detail->disponibilite];
    }

    /**
     * @return list<string|null>
     */
    private function demande(DetailService $detail): array
    {
        return [
            $detail->metier,
            $detail->budget !== null ? 'Budget : '.Montant::fcfa($detail->budget) : null,
            $detail->date_souhaitee !== null ? 'Pour le '.$detail->date_souhaitee->translatedFormat('j M') : 'Dès que possible',
        ];
    }

    /**
     * @return list<string|null>
     */
    private function formation(DetailFormation $detail): array
    {
        return [
            $detail->prix === 0 ? 'Gratuit' : Montant::fcfa($detail->prix),
            $detail->duree,
            $detail->modalite === Modalite::Presentiel ? null : $detail->modalite->libelle(),
            $detail->certifiante ? 'Certifiante' : null,
        ];
    }
}
