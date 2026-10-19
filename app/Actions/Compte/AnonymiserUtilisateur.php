<?php

namespace App\Actions\Compte;

use App\Models\CodeOtp;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Suppression d'un compte conforme à la loi ivoirienne n° 2013-450 (ARTCI, CONTEXTE §13.F, §16) :
 * les données personnelles sont effacées, les alertes et notifications supprimées,
 * les publications conservées sous un nom anonyme. L'empreinte des essais gratuits reste (§14.6).
 */
class AnonymiserUtilisateur
{
    public const string NOM_ANONYME = 'Utilisateur supprimé';

    public function executer(Utilisateur $utilisateur): void
    {
        DB::transaction(function () use ($utilisateur): void {
            $telephone = $utilisateur->telephone;

            $utilisateur->alertes()->delete();
            $utilisateur->notifications()->delete();
            $utilisateur->publicationsEnregistrees()->detach();
            $utilisateur->clearMediaCollection('photo');
            $utilisateur->syncRoles([]);

            if ($telephone !== null) {
                CodeOtp::query()->where('telephone', $telephone)->delete();
            }

            Publication::query()
                ->where('auteur_id', $utilisateur->id)
                ->whereNull('entreprise_id')
                ->lazyById(200)
                ->each(function (Publication $publication): void {
                    $publication->auteur_nom_affiche = self::NOM_ANONYME;
                    $publication->auteur_avatar_url = null;
                    $publication->save();
                });

            $utilisateur->forceFill([
                'nom' => self::NOM_ANONYME,
                'prenoms' => null,
                'telephone' => null,
                'telephone_verifie_le' => null,
                'email' => null,
                'commune_id' => null,
                'mot_de_passe' => Hash::make(Str::random(40)),
                'remember_token' => null,
            ])->save();

            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();

            $utilisateur->delete();
        });
    }
}
