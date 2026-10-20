<?php

namespace App\Actions\Commande;

use App\Enums\PasserelleCommande;
use App\Enums\StatutCommande;
use App\Enums\StatutPublication;
use App\Enums\TypeArticle;
use App\Enums\TypeProduit;
use App\Exceptions\OperationImpossibleException;
use App\Models\Commande;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\NumeroSequentiel;

/**
 * Commande d'un plan ou d'un produit (CONTEXTE §14.7). Le montant est figé ici,
 * à partir du prix en base : rien de ce que le client envoie ne fixe le prix.
 */
class CreerCommande
{
    public function __construct(
        private readonly NumeroSequentiel $numeros,
        private readonly RemonterPublication $remonter,
    ) {}

    public function executer(
        Utilisateur $acheteur,
        Utilisateur|Entreprise $titulaire,
        Plan|Produit $article,
        ?Publication $publication = null,
        ?int $communeId = null,
    ): Commande {
        $this->verifier($acheteur, $titulaire, $article, $publication);

        $ttc = $article->prix_fcfa;
        $tva = Parametre::entier('taux_tva');

        $commande = new Commande([
            'numero' => $this->numeros->suivant(NumeroSequentiel::COMMANDE),
            'utilisateur_id' => $acheteur->id,
            'titulaire_type' => $titulaire->getMorphClass(),
            'titulaire_id' => $titulaire->getKey(),
            'article_type' => $article instanceof Plan ? TypeArticle::Plan : TypeArticle::Produit,
            'article_id' => $article->id,
            'publication_id' => $publication?->id,
            'commune_id' => $communeId ?? $publication?->commune_id,
            'montant_ttc_fcfa' => $ttc,
            'taux_tva' => $tva,
            'montant_ht_fcfa' => (int) round($ttc * 100 / (100 + $tva)),
            'passerelle' => PasserelleCommande::from((string) config('rezohelp.paiement.driver')),
        ]);
        $commande->statut = StatutCommande::EnAttente;
        $commande->save();

        return $commande;
    }

    private function verifier(Utilisateur $acheteur, Utilisateur|Entreprise $titulaire, Plan|Produit $article, ?Publication $publication): void
    {
        if ($titulaire instanceof Utilisateur ? $titulaire->id !== $acheteur->id : $titulaire->proprietaire_id !== $acheteur->id) {
            throw new OperationImpossibleException('Vous ne pouvez commander que pour vous-même ou vos entreprises.');
        }

        if (! $article->actif || $article->prix_fcfa <= 0) {
            throw new OperationImpossibleException('Cette offre n’est pas disponible à l’achat.');
        }

        if ($article instanceof Plan && ! in_array($article->cible, Droits::cibles($titulaire), true)) {
            throw new OperationImpossibleException('Ce plan ne correspond pas à ce compte.');
        }

        if (! $article instanceof Produit || $article->type === TypeProduit::OffreSupplementaire) {
            return;
        }

        // Remontée et mises en avant : une publication en ligne du titulaire (payer ne dispense pas de la modération).
        $estAuTitulaire = $publication !== null && ($titulaire instanceof Entreprise
            ? $publication->entreprise_id === $titulaire->id
            : $publication->auteur_id === $titulaire->id);

        if (! $estAuTitulaire) {
            throw new OperationImpossibleException('Choisissez une de vos publications.');
        }

        if ($publication->statut !== StatutPublication::Publiee) {
            throw new OperationImpossibleException('Seule une publication en ligne peut être mise en avant.');
        }

        if ($article->type === TypeProduit::Remontee) {
            $this->remonter->verifierPossible($publication);
        }
    }
}
