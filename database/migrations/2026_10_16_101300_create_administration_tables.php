<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres, journal d'audit, recherches et appels à l'IA (CONTEXTE §7, §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle', 80)->primary();
            $table->text('valeur')->nullable();
            $table->string('type', 10)->default('texte');
            $table->string('libelle');
            $table->timestamp('modifie_le')->nullable();
            $table->foreignId('modifie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
        });

        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('action', 80);
            $table->nullableMorphs('sujet');
            $table->json('donnees')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('cree_le')->nullable();

            $table->index(['utilisateur_id', 'cree_le']);
        });

        Schema::create('recherches', function (Blueprint $table) {
            $table->id();
            $table->string('terme', 150);
            $table->string('type', 20)->nullable();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->unsignedInteger('nb_resultats')->default(0);
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('cree_le')->nullable();

            $table->index('cree_le');
            $table->index(['terme', 'nb_resultats']);
        });

        Schema::create('appels_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->nullable()->constrained('publications')->nullOnDelete();
            $table->foreignId('import_veille_id')->nullable()->constrained('imports_veille')->nullOnDelete();
            $table->string('modele', 60);
            $table->unsignedInteger('tokens_entree')->default(0);
            $table->unsignedInteger('tokens_sortie')->default(0);
            $table->unsignedInteger('cout_estime_fcfa')->default(0);
            $table->unsignedInteger('duree_ms')->default(0);
            $table->string('statut', 20);
            $table->timestamp('cree_le')->nullable();

            $table->index('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appels_ia');
        Schema::dropIfExists('recherches');
        Schema::dropIfExists('journal_activites');
        Schema::dropIfExists('parametres');
    }
};
