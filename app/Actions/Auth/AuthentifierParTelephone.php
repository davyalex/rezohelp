<?php

namespace App\Actions\Auth;

use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Retrouve le compte à partir du numéro saisi dans n'importe quel format
 * (« 0708091011 », « 07 08 09 10 11 », « +225 07 08 09 10 11 ») et du pays choisi.
 * Un compte suspendu est refusé avec un message explicite.
 */
class AuthentifierParTelephone
{
    public function __construct(private readonly TelephoneService $telephones) {}

    public function executer(Request $request): ?Utilisateur
    {
        $telephone = $this->telephones->normaliser(
            $request->string('telephone')->toString(),
            $request->string('pays_code')->toString() ?: null,
        );

        if ($telephone === null) {
            return null;
        }

        $utilisateur = Utilisateur::query()->where('telephone', $telephone)->first();

        if ($utilisateur === null || ! Hash::check($request->string('password')->toString(), $utilisateur->mot_de_passe)) {
            return null;
        }

        if ($utilisateur->estSuspendu()) {
            throw ValidationException::withMessages(['telephone' => $utilisateur->messageSuspension()]);
        }

        return $utilisateur;
    }
}
