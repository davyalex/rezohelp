<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes de l'équipe séparés des membres du site (D-066) : table administrateurs,
 * garde d'authentification « admin », rôles Spatie sur cette garde.
 * Les traces d'audit pointent désormais vers administrateurs ; les anciennes valeurs
 * (identifiants de membres) n'ont pas d'équivalent et sont vidées.
 */
return new class extends Migration
{
    /** Rôles et permissions d'équipe qui existaient sur la garde des membres. */
    private const array ANCIENS_ROLES = ['administrateur', 'moderateur'];

    private const array ANCIENNES_PERMISSIONS = [
        'tableau_de_bord.voir', 'publications.moderer', 'publications.gerer', 'signalements.traiter',
        'commentaires.moderer', 'utilisateurs.voir', 'utilisateurs.suspendre', 'utilisateurs.gerer',
        'entreprises.verifier', 'veille.gerer', 'referentiels.gerer', 'parametres.gerer',
        'statistiques.voir', 'systeme.voir', 'abonnements.gerer', 'paiements.voir',
    ];

    public function up(): void
    {
        Schema::create('administrateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80);
            $table->string('prenoms', 120)->nullable();
            $table->string('nom_utilisateur', 50)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('mot_de_passe');
            $table->boolean('doit_changer_mot_de_passe')->default(true);
            $table->timestamp('suspendu_le')->nullable();
            $table->timestamp('derniere_connexion_le')->nullable();
            $table->rememberToken();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');
        });

        Schema::table('journal_activites', function (Blueprint $table) {
            $table->dropForeign(['utilisateur_id']);
            $table->dropIndex(['utilisateur_id', 'cree_le']);
        });
        DB::table('journal_activites')->update(['utilisateur_id' => null]);
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->renameColumn('utilisateur_id', 'administrateur_id');
        });
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->foreign('administrateur_id')->references('id')->on('administrateurs')->nullOnDelete();
            $table->index(['administrateur_id', 'cree_le']);
        });

        $this->repointer('signalements', 'traite_par', 'administrateurs');
        $this->repointer('parametres', 'modifie_par', 'administrateurs');

        // Les membres n'ont plus de rôle d'équipe : l'administration a ses propres comptes.
        $roles = DB::table('roles')->where('guard_name', 'web')->whereIn('name', self::ANCIENS_ROLES)->pluck('id');
        DB::table('model_has_roles')->whereIn('role_id', $roles)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $roles)->delete();
        DB::table('roles')->whereIn('id', $roles)->delete();
        $permissions = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::ANCIENNES_PERMISSIONS)->pluck('id');
        DB::table('model_has_permissions')->whereIn('permission_id', $permissions)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $permissions)->delete();
        DB::table('permissions')->whereIn('id', $permissions)->delete();
    }

    public function down(): void
    {
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->dropForeign(['administrateur_id']);
            $table->dropIndex(['administrateur_id', 'cree_le']);
        });
        DB::table('journal_activites')->update(['administrateur_id' => null]);
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->renameColumn('administrateur_id', 'utilisateur_id');
        });
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->nullOnDelete();
            $table->index(['utilisateur_id', 'cree_le']);
        });

        $this->repointer('signalements', 'traite_par', 'utilisateurs');
        $this->repointer('parametres', 'modifie_par', 'utilisateurs');

        Schema::dropIfExists('administrateurs');
    }

    private function repointer(string $table, string $colonne, string $cible): void
    {
        Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$colonne]));
        DB::table($table)->update([$colonne => null]);
        Schema::table($table, fn (Blueprint $t) => $t->foreign($colonne)->references('id')->on($cible)->nullOnDelete());
    }
};
