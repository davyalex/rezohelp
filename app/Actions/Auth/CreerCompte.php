<?php

namespace App\Actions\Auth;

use App\Models\Utilisateur;
use App\Rules\TelephoneDisponible;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Inscription par téléphone + mot de passe (CONTEXTE §9), appelée par Fortify.
 */
class CreerCompte implements CreatesNewUsers
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): Utilisateur
    {
        $donnees = Validator::make($input, [
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'pays_code' => ['required', 'string', 'size:2', Rule::exists('pays', 'code')->where('actif', true)],
            'telephone' => ['required', 'string', 'max:30', 'phone:pays_code,mobile', new TelephoneDisponible],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->uncompromised()],
        ])->validate();

        return DB::transaction(function () use ($donnees): Utilisateur {
            $utilisateur = new Utilisateur([
                'nom' => $donnees['nom'],
                'prenoms' => $donnees['prenoms'],
                'pays_code' => strtoupper($donnees['pays_code']),
                'mot_de_passe' => $donnees['password'],
            ]);
            // Normalisé en E.164 par le cast TelephoneE164 (pays_code déjà renseigné).
            $utilisateur->telephone = $donnees['telephone'];
            $utilisateur->save();

            $utilisateur->assignRole('membre');

            return $utilisateur;
        });
    }
}
