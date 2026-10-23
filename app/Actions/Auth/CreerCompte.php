<?php

namespace App\Actions\Auth;

use App\Models\Parametre;
use App\Models\Utilisateur;
use App\Rules\TelephoneDisponible;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Inscription par téléphone + mot de passe (CONTEXTE §9), appelée par Fortify.
 * Au plus max_inscriptions_par_heure comptes par heure depuis une même adresse IP (lot 9) ;
 * la limite reste large car beaucoup d'abonnés mobiles partagent une même IP publique.
 */
class CreerCompte implements CreatesNewUsers
{
    public const int FENETRE_SECONDES = 3600;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): Utilisateur
    {
        $cle = 'inscription:'.request()->ip();
        $maximum = Parametre::entier('max_inscriptions_par_heure');

        if ($maximum > 0 && RateLimiter::tooManyAttempts($cle, $maximum)) {
            throw ValidationException::withMessages([
                'telephone' => 'Trop de comptes créés depuis cette connexion. Réessayez dans '.max(1, (int) ceil(RateLimiter::availableIn($cle) / 60)).' minute(s).',
            ]);
        }

        $donnees = Validator::make($input, [
            'nom' => ['required', 'string', 'max:100'],
            'prenoms' => ['required', 'string', 'max:150'],
            'pays_code' => ['required', 'string', 'size:2', Rule::exists('pays', 'code')->where('actif', true)],
            'telephone' => ['required', 'string', 'max:30', 'phone:pays_code,mobile', new TelephoneDisponible],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->uncompromised()],
        ])->validate();

        // Seules les inscriptions réussies comptent : une erreur de saisie ne pénalise personne.
        RateLimiter::hit($cle, self::FENETRE_SECONDES);

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
