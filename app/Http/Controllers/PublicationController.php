<?php

namespace App\Http\Controllers;

use App\Actions\Publication\CompterInteraction;
use App\Enums\StatutCommentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\DonneesStructurees;
use App\Services\LienContact;
use App\Services\Referentiels;
use App\Services\RenduCarte;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Page détail d'une publication, lien court et partage WhatsApp.
 */
class PublicationController extends Controller
{
    public function show(Request $request, string $slug, DonneesStructurees $donnees, Referentiels $referentiels, CompterInteraction $compter): View
    {
        $publication = Publication::query()
            ->where('slug', $slug)
            ->with([
                'detailEmploi',
                'detailService',
                'detailFormation',
                'entreprise:id,nom,site_web,verifiee',
                'commentaires' => fn (HasMany $requete) => $requete
                    ->whereNull('parent_id')
                    ->where('statut', StatutCommentaire::Visible->value)
                    ->with([
                        'utilisateur:id,nom,prenoms',
                        'sousCommentaires' => fn (HasMany $sous) => $sous
                            ->where('statut', StatutCommentaire::Visible->value)
                            ->with('utilisateur:id,nom,prenoms')
                            ->oldest('cree_le'),
                    ])
                    ->latest('cree_le')
                    ->limit(50),
            ])
            ->firstOrFail();

        // Une publication non publiée n'existe pas pour le public (404, pas 403).
        abort_unless(Gate::allows('view', $publication), 404);

        defer(fn () => $compter->executer($publication->id, CompterInteraction::VUES));

        $visiteur = $request->user();

        return view('publications.show', [
            'publication' => $publication,
            'commune' => $referentiels->nomCommune($publication->commune_id),
            'categorie' => $referentiels->nomCategorie($publication->categorie_id),
            'jsonLd' => $donnees->pour($publication),
            'etat' => app(RenduCarte::class)->chargerEtat($visiteur instanceof Utilisateur ? $visiteur : null, collect([$publication])),
            'dejaRepondu' => $visiteur !== null && $publication->reponses()->where('utilisateur_id', $visiteur->getAuthIdentifier())->exists(),
        ]);
    }

    /**
     * Lien court des SMS : /p/{code}.
     */
    public function lienCourt(string $code): RedirectResponse
    {
        $slug = Publication::query()->where('code_court', $code)->value('slug');
        abort_if($slug === null, 404);

        return redirect()->route('publications.show', $slug, 301);
    }

    /**
     * « Postuler » ou « S'inscrire » hors Rezohelp (lien, e-mail, téléphone) : le clic est compté (§11.8).
     */
    public function contactExterne(string $slug, CompterInteraction $compter): RedirectResponse
    {
        $publication = Publication::query()->publiees()->where('slug', $slug)
            ->with(['detailEmploi:publication_id,mode_candidature,contact_candidature', 'detailFormation:publication_id,mode_inscription,contact_inscription'])
            ->firstOrFail(['id', 'type', 'titre']);

        $lien = app(LienContact::class)->pour($publication);
        abort_if($lien === null, 404);

        defer(fn () => $compter->executer($publication->id, CompterInteraction::REPONSES));

        return redirect()->away($lien);
    }

    /**
     * Compte le partage puis ouvre WhatsApp avec le titre et le lien court.
     */
    public function partager(string $slug, CompterInteraction $compter): RedirectResponse
    {
        $publication = Publication::query()->publiees()->where('slug', $slug)->firstOrFail(['id', 'titre', 'code_court']);

        defer(fn () => $compter->executer($publication->id, CompterInteraction::PARTAGES));

        $texte = $publication->titre.' — '.route('publications.court', $publication->code_court);

        return redirect()->away('https://wa.me/?text='.rawurlencode($texte));
    }
}
