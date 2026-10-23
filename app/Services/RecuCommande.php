<?php

namespace App\Services;

use App\Enums\StatutCommande;
use App\Models\Commande;
use App\Models\Entreprise;
use App\Models\Parametre;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Reçu d'une commande payée (CONTEXTE §14.7), partagé par « Mon espace » et le back-office.
 */
class RecuCommande
{
    /** Balises admises dans les mentions légales saisies dans l'admin. */
    private const string BALISES_MENTIONS = '<p><br><strong><em><b><i><ul><li>';

    public function disponible(Commande $commande): bool
    {
        return $commande->numero_recu !== null
            && in_array($commande->statut, [StatutCommande::Payee, StatutCommande::Remboursee], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function donnees(Commande $commande): array
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

    public function pdf(Commande $commande): Response
    {
        return Pdf::loadView('espace.recus.pdf', $this->donnees($commande))
            ->setPaper('a5')
            ->download('recu-'.$commande->numero_recu.'.pdf');
    }
}
