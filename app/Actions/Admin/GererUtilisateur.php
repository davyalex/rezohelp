<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Actions\Compte\AnonymiserUtilisateur;
use App\Actions\Publication\MettreAJourConfiance;
use App\Enums\ConfianceUtilisateur;
use App\Enums\StatutPublication;
use App\Exceptions\OperationImpossibleException;
use App\Models\Administrateur;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * F — Gestion des comptes membres par l'équipe (CONTEXTE §13.3.F). Les comptes de l'équipe sont
 * séparés (Administrateur, D-066) : seuls les rôles de membres s'attribuent ici.
 */
class GererUtilisateur
{
    use JournaliseActivite;

    public const int LONGUEUR_MOT_DE_PASSE_TEMPORAIRE = 12;

    public function __construct(
        private readonly AnonymiserUtilisateur $anonymiser,
        private readonly MettreAJourConfiance $mettreAJourConfiance,
    ) {}

    /**
     * @param  array{nom: string, prenoms: ?string, email: ?string, commune_id: ?int}  $donnees
     */
    public function modifier(Utilisateur $utilisateur, Administrateur $admin, array $donnees): void
    {
        $utilisateur->fill($donnees);

        if (! $utilisateur->isDirty()) {
            return;
        }

        DB::transaction(function () use ($utilisateur, $admin): void {
            $differences = $this->differences($utilisateur);
            $utilisateur->save();
            $this->journaliser($admin, 'utilisateur.modifie', $utilisateur, $differences);
        });
    }

    /**
     * Suspension (définitive si $jusquau est nul) : plus de connexion ni de publication, et les
     * publications en ligne sortent du fil pour revue (D-056).
     */
    public function suspendre(Utilisateur $utilisateur, Administrateur $admin, string $motif, ?Carbon $jusquau = null): void
    {
        DB::transaction(function () use ($utilisateur, $admin, $motif, $jusquau): void {
            $utilisateur->forceFill([
                'suspendu_le' => now(),
                'suspendu_jusqu_au' => $jusquau,
                'motif_suspension' => $motif,
            ])->save();

            $retirees = 0;
            Publication::query()
                ->where('auteur_id', $utilisateur->id)
                ->where('statut', StatutPublication::Publiee->value)
                ->lazyById(200)
                ->each(function (Publication $publication) use (&$retirees): void {
                    $publication->statut = StatutPublication::EnAttente;
                    $publication->save();
                    $retirees++;
                });

            $this->journaliser($admin, 'utilisateur.suspendu', $utilisateur, [
                'motif' => $motif,
                'jusqu_au' => $jusquau?->toDateTimeString(),
                'publications_retirees' => $retirees,
            ]);
        });
    }

    public function reactiver(Utilisateur $utilisateur, Administrateur $admin): void
    {
        DB::transaction(function () use ($utilisateur, $admin): void {
            $utilisateur->forceFill(['suspendu_le' => null, 'suspendu_jusqu_au' => null, 'motif_suspension' => null])->save();
            $this->journaliser($admin, 'utilisateur.reactive', $utilisateur);
        });
    }

    /**
     * Mot de passe temporaire, à changer à la prochaine connexion. Renvoyé une seule fois, jamais journalisé.
     */
    public function reinitialiserMotDePasse(Utilisateur $utilisateur, Administrateur $admin): string
    {
        $temporaire = Str::password(self::LONGUEUR_MOT_DE_PASSE_TEMPORAIRE, symbols: false);

        DB::transaction(function () use ($utilisateur, $admin, $temporaire): void {
            $utilisateur->forceFill(['mot_de_passe' => $temporaire, 'doit_changer_mot_de_passe' => true, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
            $this->journaliser($admin, 'utilisateur.mot_de_passe_reinitialise', $utilisateur);
        });

        return $temporaire;
    }

    /**
     * @param  list<string>  $roles
     */
    public function roles(Utilisateur $utilisateur, Administrateur $admin, array $roles): void
    {
        // Seuls les rôles de membres (garde web) : l'équipe a ses propres comptes (D-066).
        $avant = $utilisateur->getRoleNames()->sort()->values()->all();
        $roles = Role::query()->where('guard_name', 'web')
            ->whereIn('name', array_intersect($roles, RolesEtPermissionsSeeder::ROLES_MEMBRES))
            ->pluck('name')->sort()->values()->all();

        if ($avant === $roles) {
            return;
        }

        DB::transaction(function () use ($utilisateur, $admin, $avant, $roles): void {
            $utilisateur->syncRoles($roles);
            $this->journaliser($admin, 'utilisateur.roles', $utilisateur, ['avant' => $avant, 'apres' => $roles]);
        });
    }

    /**
     * Seul l'admin lève la restriction (§11.6) : les faits antérieurs ne comptent plus.
     */
    public function leverRestriction(Utilisateur $utilisateur, Administrateur $admin): void
    {
        if ($utilisateur->confiance !== ConfianceUtilisateur::Restreint) {
            throw new OperationImpossibleException('Ce compte n’est pas restreint.');
        }

        DB::transaction(function () use ($utilisateur, $admin): void {
            $utilisateur->forceFill(['confiance' => ConfianceUtilisateur::Nouveau, 'restriction_levee_le' => now()])->save();
            $this->mettreAJourConfiance->executer($utilisateur);
            $this->journaliser($admin, 'utilisateur.restriction_levee', $utilisateur);
        });
    }

    /**
     * Suppression ARTCI : anonymisation (D-042), publications conservées sous « Utilisateur supprimé ».
     */
    public function anonymiser(Utilisateur $utilisateur, Administrateur $admin): void
    {
        DB::transaction(function () use ($utilisateur, $admin): void {
            $this->journaliser($admin, 'utilisateur.anonymise', $utilisateur);
            $this->anonymiser->executer($utilisateur);
        });
    }
}
