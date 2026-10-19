<?php

namespace App\Actions\Compte;

use App\Jobs\RafraichirAuteurPublications;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;

/**
 * Mise à jour du profil. Le nom et la photo étant dénormalisés sur les cartes,
 * les publications publiées en son nom propre sont rafraîchies en file.
 */
class ModifierProfil
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Utilisateur $utilisateur, array $donnees, ?UploadedFile $photo = null): void
    {
        $utilisateur->fill([
            'nom' => trim((string) $donnees['nom']),
            'prenoms' => trim((string) $donnees['prenoms']),
            'commune_id' => isset($donnees['commune_id']) ? (int) $donnees['commune_id'] : null,
            'email' => isset($donnees['email']) && $donnees['email'] !== '' ? mb_strtolower(trim((string) $donnees['email'])) : null,
        ]);
        $nomChange = $utilisateur->isDirty(['nom', 'prenoms']);
        $utilisateur->save();

        if ($photo !== null) {
            $utilisateur->addMedia($photo)->toMediaCollection('photo');
        }

        if ($nomChange || $photo !== null) {
            RafraichirAuteurPublications::dispatch($utilisateur->id);
        }
    }
}
