<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OperationImpossibleException;
use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Base des contrôleurs du back-office : l'auteur des actions est toujours le membre connecté.
 */
abstract class ControleurAdmin extends Controller
{
    protected function admin(Request $requete): Utilisateur
    {
        $utilisateur = $requete->user();
        abort_unless($utilisateur instanceof Utilisateur, 403);

        return $utilisateur;
    }

    /**
     * Exécute une action et revient à la page précédente avec son message, ou avec le refus métier.
     */
    protected function appliquer(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return back()->with('statut', $message);
    }
}
