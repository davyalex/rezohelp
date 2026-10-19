<?php

namespace App\Actions\Commande;

use App\Enums\CleDroit;
use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Exceptions\OperationImpossibleException;
use App\Models\Commande;
use App\Models\Entreprise;
use App\Models\MiseEnAvant;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;

/**
 * Remontée (CONTEXTE §14.9) : tri_le = now(), la pagination par curseur reste intacte.
 * Au plus une remontée par publication toutes les heures_entre_remontees heures ;
 * gratuite dans la limite publication.remontees_mois, sinon achetée (produit remontee).
 */
class RemonterPublication
{
    public function verifierPossible(Publication $publication): void
    {
        if ($publication->statut !== StatutPublication::Publiee) {
            throw new OperationImpossibleException('Seule une publication en ligne peut être remontée.');
        }

        $derniere = MiseEnAvant::query()
            ->where('publication_id', $publication->id)
            ->where('type', TypeMiseEnAvant::Remontee->value)
            ->max('cree_le');
        $delai = Parametre::entier('heures_entre_remontees');

        if ($derniere !== null && now()->subHours($delai)->lt($derniere)) {
            throw new OperationImpossibleException("Cette publication a déjà été remontée il y a moins de {$delai} heures.");
        }
    }

    /**
     * Remontée incluse dans la formule du titulaire (sans paiement).
     */
    public function incluse(Publication $publication, Utilisateur|Entreprise $titulaire): MiseEnAvant
    {
        Droits::pour($titulaire)->verifierOuEchouer(CleDroit::PublicationRemonteesMois);

        return $this->executer($publication, null);
    }

    public function executer(Publication $publication, ?Commande $commande): MiseEnAvant
    {
        $this->verifierPossible($publication);

        return $this->appliquer($publication, $commande);
    }

    /**
     * Remontée payée, à l'activation de la commande : le délai a été contrôlé à la commande.
     * Si la publication est sortie du fil entre-temps, la remontée attend (suspendue) son retour.
     */
    public function payee(Publication $publication, Commande $commande): MiseEnAvant
    {
        if ($publication->statut === StatutPublication::Publiee) {
            return $this->appliquer($publication, $commande);
        }

        $miseEnAvant = new MiseEnAvant([
            'publication_id' => $publication->id,
            'commande_id' => $commande->id,
            'type' => TypeMiseEnAvant::Remontee,
        ]);
        $miseEnAvant->suspendue_le = now();
        $miseEnAvant->save();

        return $miseEnAvant;
    }

    private function appliquer(Publication $publication, ?Commande $commande): MiseEnAvant
    {
        $publication->tri_le = now();
        $publication->save();

        return MiseEnAvant::query()->create([
            'publication_id' => $publication->id,
            'commande_id' => $commande?->id,
            'type' => TypeMiseEnAvant::Remontee,
            'debut_le' => now(),
            'fin_le' => now(),
        ]);
    }
}
