<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Entreprise;
use App\Models\Parametre;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reçu d'une commande payée (CONTEXTE §14.7) : page imprimable et PDF, avec les mentions
 * légales des paramètres (raison sociale, RCCM, NCC).
 */
class RecuController extends Controller
{
    /** Balises admises dans les mentions légales saisies dans l'admin. */
    private const string BALISES_MENTIONS = '<p><br><strong><em><b><i><ul><li>';

    public function show(Commande $commande): View
    {
        $this->authorize('voirRecu', $commande);

        return view('espace.recus.show', $this->donnees($commande));
    }

    public function pdf(Commande $commande): Response
    {
        $this->authorize('voirRecu', $commande);

        return Pdf::loadView('espace.recus.pdf', $this->donnees($commande))
            ->setPaper('a5')
            ->download('recu-'.$commande->numero_recu.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(Commande $commande): array
    {
        $commande->loadMissing(['utilisateur:id,nom,prenoms,telephone,pays_code', 'plan:id,nom,duree_jours', 'produit:id,nom,duree_jours', 'publication:id,titre', 'titulaire']);
        $titulaire = $commande->titulaire;

        return [
            'commande' => $commande,
            'article' => $commande->article(),
            'nomTitulaire' => $titulaire instanceof Entreprise ? $titulaire->nom : $commande->utilisateur?->nomAffiche(),
            // Balises simples uniquement, sans attribut (aucun onclick, style ou lien injecté).
            'mentions' => (string) preg_replace('/<(\/?)(\w+)[^>]*>/', '<$1$2>', strip_tags(Parametre::texte('mentions_legales_recu'), self::BALISES_MENTIONS)),
        ];
    }
}
