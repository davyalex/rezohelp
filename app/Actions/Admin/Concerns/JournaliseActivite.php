<?php

namespace App\Actions\Admin\Concerns;

use App\Models\Administrateur;
use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit des actions du back-office (CONTEXTE §13.1) : qui, quoi, sur quel objet,
 * avec quelles données (avant / après, motif), depuis quelle IP.
 */
trait JournaliseActivite
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    protected function journaliser(Administrateur $auteur, string $action, ?Model $sujet = null, array $donnees = []): JournalActivite
    {
        return JournalActivite::query()->create([
            'administrateur_id' => $auteur->id,
            'action' => $action,
            'sujet_type' => $sujet?->getMorphClass(),
            'sujet_id' => $sujet?->getKey(),
            'donnees' => $donnees === [] ? null : $donnees,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Valeurs avant / après des attributs modifiés d'un modèle (à appeler avant save()).
     *
     * @return array{avant: array<string, mixed>, apres: array<string, mixed>}
     */
    protected function differences(Model $modele): array
    {
        $apres = $modele->getDirty();
        $avant = array_intersect_key($modele->getOriginal(), $apres);

        // Le mot de passe et les jetons ne sont jamais recopiés dans le journal.
        $masquer = ['mot_de_passe', 'remember_token'];

        return [
            'avant' => array_diff_key($this->scalaires($avant), array_flip($masquer)),
            'apres' => array_diff_key($this->scalaires($apres), array_flip($masquer)),
        ];
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    private function scalaires(array $valeurs): array
    {
        return array_map(fn (mixed $valeur): mixed => match (true) {
            $valeur instanceof \BackedEnum => $valeur->value,
            $valeur instanceof \DateTimeInterface => $valeur->format('Y-m-d H:i:s'),
            is_scalar($valeur), $valeur === null => $valeur,
            default => json_encode($valeur),
        }, $valeurs);
    }
}
