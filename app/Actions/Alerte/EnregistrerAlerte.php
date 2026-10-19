<?php

namespace App\Actions\Alerte;

use App\Enums\FrequenceAlerte;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Alerte;
use App\Models\Utilisateur;
use App\Services\Referentiels;

/**
 * Crée ou modifie une alerte. Sans nom saisi, le nom est tiré des critères
 * (« Électricité · Cocody »).
 */
class EnregistrerAlerte
{
    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * @param  array<string, mixed>  $donnees  données validées par AlerteRequest
     */
    public function executer(Utilisateur $utilisateur, array $donnees, ?Alerte $alerte = null): Alerte
    {
        $alerte ??= new Alerte(['active' => true]);

        $alerte->fill([
            'type' => isset($donnees['type']) ? TypePublication::from((string) $donnees['type']) : null,
            'categorie_id' => isset($donnees['categorie_id']) ? (int) $donnees['categorie_id'] : null,
            'commune_id' => isset($donnees['commune_id']) ? (int) $donnees['commune_id'] : null,
            'type_contrat' => isset($donnees['type_contrat']) ? TypeContrat::from((string) $donnees['type_contrat']) : null,
            'mots_cles' => isset($donnees['mots_cles']) ? trim((string) $donnees['mots_cles']) : null,
            'frequence' => FrequenceAlerte::from((string) $donnees['frequence']),
        ]);

        $nom = trim((string) ($donnees['nom'] ?? ''));
        $alerte->nom = $nom !== '' ? $nom : $this->nomParDefaut($alerte);

        if ($alerte->exists) {
            $alerte->save();
        } else {
            $utilisateur->alertes()->save($alerte);
        }

        return $alerte;
    }

    private function nomParDefaut(Alerte $alerte): string
    {
        $morceaux = array_filter([
            $this->referentiels->nomCategorie($alerte->categorie_id) ?? match ($alerte->type) {
                TypePublication::Emploi => 'Emplois',
                TypePublication::Service => 'Home service',
                TypePublication::Formation => 'Formations',
                null => null,
            },
            $alerte->type_contrat?->libelle(),
            $alerte->mots_cles !== null && $alerte->mots_cles !== '' ? '« '.$alerte->mots_cles.' »' : null,
            $this->referentiels->nomCommune($alerte->commune_id),
        ]);

        return mb_substr(implode(' · ', $morceaux) ?: 'Mon alerte', 0, 100);
    }
}
