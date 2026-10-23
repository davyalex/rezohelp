<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Exceptions\OperationImpossibleException;
use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Comptes de l'équipe (D-066). Chaque compte a un seul rôle. Règles :
 *  - le mot de passe est généré (8 caractères), affiché une seule fois, et doit être changé à la première connexion ;
 *  - seul un super administrateur crée, modifie, suspend ou supprime un super administrateur ;
 *  - personne ne se suspend, ne se supprime ni ne change son propre rôle ;
 *  - il reste toujours au moins un super administrateur actif.
 */
class GererAdministrateur
{
    use JournaliseActivite;

    public const int LONGUEUR_MOT_DE_PASSE = 8;

    /** Sans caractères ambigus (0/O, 1/l/I). */
    private const string MINUSCULES = 'abcdefghijkmnopqrstuvwxyz';

    private const string MAJUSCULES = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const string CHIFFRES = '23456789';

    /**
     * Mot de passe de 8 caractères avec au moins une minuscule, une majuscule et un chiffre.
     */
    public static function genererMotDePasse(): string
    {
        $tous = self::MINUSCULES.self::MAJUSCULES.self::CHIFFRES;
        $caracteres = [
            self::MINUSCULES[random_int(0, strlen(self::MINUSCULES) - 1)],
            self::MAJUSCULES[random_int(0, strlen(self::MAJUSCULES) - 1)],
            self::CHIFFRES[random_int(0, strlen(self::CHIFFRES) - 1)],
        ];

        while (count($caracteres) < self::LONGUEUR_MOT_DE_PASSE) {
            $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
        }

        // Mélange cryptographique (Fisher-Yates).
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }

    /**
     * @param  array{nom: string, prenoms: ?string, nom_utilisateur: string, email: ?string, role: string}  $donnees
     * @return array{0: Administrateur, 1: string} le compte et son mot de passe provisoire
     */
    public function creer(array $donnees, Administrateur $auteur): array
    {
        $this->verifierRoleAttribuable($donnees['role'], $auteur);
        $motDePasse = self::genererMotDePasse();

        $admin = DB::transaction(function () use ($donnees, $auteur, $motDePasse): Administrateur {
            $admin = new Administrateur([
                'nom' => $donnees['nom'],
                'prenoms' => $donnees['prenoms'],
                'nom_utilisateur' => mb_strtolower($donnees['nom_utilisateur']),
                'email' => $donnees['email'],
                'mot_de_passe' => $motDePasse,
            ]);
            $admin->doit_changer_mot_de_passe = true;
            $admin->save();
            $admin->syncRoles([$donnees['role']]);

            $this->journaliser($auteur, 'administrateur.cree', $admin, ['role' => $donnees['role']]);

            return $admin;
        });

        return [$admin, $motDePasse];
    }

    /**
     * @param  array{nom: string, prenoms: ?string, nom_utilisateur: string, email: ?string, role: string}  $donnees
     */
    public function modifier(Administrateur $admin, array $donnees, Administrateur $auteur): void
    {
        $this->verifierCible($admin, $auteur);
        $roleActuel = $admin->getRoleNames()->first();
        $nouveauRole = $donnees['role'];

        if ($nouveauRole !== $roleActuel) {
            if ($admin->is($auteur)) {
                throw new OperationImpossibleException('Vous ne pouvez pas changer votre propre rôle.');
            }
            $this->verifierRoleAttribuable($nouveauRole, $auteur);
            if ($roleActuel === PermissionsAdmin::ROLE_SUPER) {
                $this->verifierAutreSuperAdministrateur($admin);
            }
        }

        $admin->fill([
            'nom' => $donnees['nom'],
            'prenoms' => $donnees['prenoms'],
            'nom_utilisateur' => mb_strtolower($donnees['nom_utilisateur']),
            'email' => $donnees['email'],
        ]);

        DB::transaction(function () use ($admin, $auteur, $roleActuel, $nouveauRole): void {
            $differences = $this->differences($admin);
            $admin->save();

            if ($nouveauRole !== $roleActuel) {
                $admin->syncRoles([$nouveauRole]);
                $differences['avant']['role'] = $roleActuel;
                $differences['apres']['role'] = $nouveauRole;
            }

            if ($differences['apres'] !== []) {
                $this->journaliser($auteur, 'administrateur.modifie', $admin, $differences);
            }
        });
    }

    /**
     * Nouveau mot de passe généré, affiché une seule fois ; les autres sessions du compte sont fermées
     * au prochain clic (AuthenticateSession compare l'empreinte du mot de passe).
     */
    public function reinitialiserMotDePasse(Administrateur $admin, Administrateur $auteur): string
    {
        $this->verifierCible($admin, $auteur);
        if ($admin->is($auteur)) {
            throw new OperationImpossibleException('Utilisez « Mon mot de passe » pour changer le vôtre.');
        }

        $motDePasse = self::genererMotDePasse();

        DB::transaction(function () use ($admin, $auteur, $motDePasse): void {
            $admin->forceFill(['mot_de_passe' => $motDePasse, 'doit_changer_mot_de_passe' => true, 'remember_token' => Str::random(60)])->save();
            $this->journaliser($auteur, 'administrateur.mot_de_passe_reinitialise', $admin);
        });

        return $motDePasse;
    }

    public function changerSonMotDePasse(Administrateur $admin, string $motDePasse): void
    {
        DB::transaction(function () use ($admin, $motDePasse): void {
            $admin->forceFill(['mot_de_passe' => $motDePasse, 'doit_changer_mot_de_passe' => false])->save();
            $this->journaliser($admin, 'administrateur.mot_de_passe_change', $admin);
        });
    }

    public function suspendre(Administrateur $admin, Administrateur $auteur): void
    {
        $this->verifierCible($admin, $auteur);
        $this->verifierPasSoiMeme($admin, $auteur, 'suspendre');
        if ($admin->estSuperAdministrateur()) {
            $this->verifierAutreSuperAdministrateur($admin);
        }

        DB::transaction(function () use ($admin, $auteur): void {
            $admin->forceFill(['suspendu_le' => now(), 'remember_token' => Str::random(60)])->save();
            $this->journaliser($auteur, 'administrateur.suspendu', $admin);
        });
    }

    public function reactiver(Administrateur $admin, Administrateur $auteur): void
    {
        $this->verifierCible($admin, $auteur);

        DB::transaction(function () use ($admin, $auteur): void {
            $admin->forceFill(['suspendu_le' => null])->save();
            $this->journaliser($auteur, 'administrateur.reactive', $admin);
        });
    }

    public function supprimer(Administrateur $admin, Administrateur $auteur): void
    {
        $this->verifierCible($admin, $auteur);
        $this->verifierPasSoiMeme($admin, $auteur, 'supprimer');
        if ($admin->estSuperAdministrateur()) {
            $this->verifierAutreSuperAdministrateur($admin);
        }

        DB::transaction(function () use ($admin, $auteur): void {
            $this->journaliser($auteur, 'administrateur.supprime', $admin, ['nom_utilisateur' => $admin->nom_utilisateur]);
            // Le nom d'utilisateur est libéré pour un nouveau compte ; le journal garde la trace.
            $admin->forceFill(['nom_utilisateur' => 'supprime-'.$admin->id.'-'.$admin->nom_utilisateur, 'email' => null])->save();
            $admin->syncRoles([]);
            $admin->delete();
        });
    }

    private function verifierCible(Administrateur $admin, Administrateur $auteur): void
    {
        if ($admin->estSuperAdministrateur() && ! $auteur->estSuperAdministrateur()) {
            throw new OperationImpossibleException('Seul un super administrateur peut gérer un super administrateur.');
        }
    }

    private function verifierRoleAttribuable(string $role, Administrateur $auteur): void
    {
        if (! Role::query()->where('guard_name', Administrateur::GARDE)->where('name', $role)->exists()) {
            throw new OperationImpossibleException('Ce rôle n’existe pas.');
        }

        if ($role === PermissionsAdmin::ROLE_SUPER && ! $auteur->estSuperAdministrateur()) {
            throw new OperationImpossibleException('Seul un super administrateur peut attribuer ce rôle.');
        }
    }

    private function verifierPasSoiMeme(Administrateur $admin, Administrateur $auteur, string $verbe): void
    {
        if ($admin->is($auteur)) {
            throw new OperationImpossibleException("Vous ne pouvez pas {$verbe} votre propre compte.");
        }
    }

    private function verifierAutreSuperAdministrateur(Administrateur $admin): void
    {
        $autres = Administrateur::role(PermissionsAdmin::ROLE_SUPER)
            ->whereKeyNot($admin->id)
            ->whereNull('suspendu_le')
            ->exists();

        if (! $autres) {
            throw new OperationImpossibleException('Il doit rester au moins un super administrateur actif.');
        }
    }
}
