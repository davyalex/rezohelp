<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Exceptions\OperationImpossibleException;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationRemiseEnAttente;
use App\Support\IdentifiantsPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * C — Gestion d'une publication par l'équipe (CONTEXTE §13.3.C) : modification, dépublication,
 * prolongation, suppression. Chaque action est journalisée avec l'avant / après.
 */
class GererPublication
{
    use JournaliseActivite;

    /**
     * @param  array{titre: string, contenu: string, categorie_id: int, commune_id: ?int, expire_le: ?string}  $donnees
     */
    public function modifier(Publication $publication, Utilisateur $admin, array $donnees): void
    {
        $contenu = trim($donnees['contenu']);

        $publication->fill([
            'titre' => trim($donnees['titre']),
            'contenu' => $contenu,
            'extrait' => IdentifiantsPublication::extrait($contenu),
            'categorie_id' => $donnees['categorie_id'],
            'commune_id' => $donnees['commune_id'],
            'expire_le' => $donnees['expire_le'] !== null ? Carbon::parse($donnees['expire_le'])->endOfDay() : null,
        ]);

        if (! $publication->isDirty()) {
            return;
        }

        DB::transaction(function () use ($publication, $admin): void {
            $differences = $this->differences($publication);
            $publication->save();
            $this->journaliser($admin, 'publication.modifiee', $publication, $differences);
        });
    }

    /**
     * Retire une publication du fil : elle repasse en modération, l'auteur est prévenu (D-055).
     */
    public function depublier(Publication $publication, Utilisateur $admin): void
    {
        if ($publication->statut !== StatutPublication::Publiee) {
            throw new OperationImpossibleException('Seule une publication en ligne peut être dépubliée.');
        }

        DB::transaction(function () use ($publication, $admin): void {
            $publication->statut = StatutPublication::EnAttente;
            $publication->save();
            $this->journaliser($admin, 'publication.depubliee', $publication);
        });

        if ($publication->source === SourcePublication::Utilisateur && $publication->auteur_id !== null) {
            Utilisateur::query()->find($publication->auteur_id)?->notify(new PublicationRemiseEnAttente($publication));
        }
    }

    /**
     * +jours_prolongation jours ; une publication expirée revient en ligne (décision de l'équipe).
     */
    public function prolonger(Publication $publication, Utilisateur $admin): void
    {
        if (! in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::Expiree], true)) {
            throw new OperationImpossibleException('Seule une publication en ligne ou expirée peut être prolongée.');
        }

        DB::transaction(function () use ($publication, $admin): void {
            $depart = $publication->expire_le !== null && $publication->expire_le->isFuture() ? $publication->expire_le : now();
            $publication->expire_le = $depart->copy()->addDays(Parametre::entier('jours_prolongation'));
            $publication->statut = StatutPublication::Publiee;

            $differences = $this->differences($publication);
            $publication->save();
            $this->journaliser($admin, 'publication.prolongee', $publication, $differences);
        });
    }

    public function supprimer(Publication $publication, Utilisateur $admin): void
    {
        DB::transaction(function () use ($publication, $admin): void {
            $publication->delete();
            $this->journaliser($admin, 'publication.supprimee', $publication, ['titre' => $publication->titre]);
        });
    }
}
