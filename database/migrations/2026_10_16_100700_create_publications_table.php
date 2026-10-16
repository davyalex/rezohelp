<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table centrale du fil, dénormalisée pour la lecture (CONTEXTE §7, §8, §13.4, §14.9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20);
            $table->string('sous_type', 20)->nullable();
            $table->string('statut', 20)->default('brouillon');
            $table->string('titre', 150);
            $table->string('slug', 190)->unique();
            $table->text('contenu');
            $table->string('extrait', 300)->nullable();
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('entreprise_id')->nullable()->constrained('entreprises')->nullOnDelete();

            // Dénormalisés pour les cartes du fil
            $table->string('auteur_nom_affiche', 150)->nullable();
            $table->string('auteur_avatar_url')->nullable();
            $table->boolean('auteur_verifie')->default(false)->comment('entreprise vérifiée');
            $table->json('faits_cles')->nullable()->comment('puces de la carte : contrat, salaire, tarif, durée, prix');
            $table->string('type_contrat', 20)->nullable()->comment('copie de details_emploi pour le filtre du fil');
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('image_largeur')->nullable();
            $table->unsignedSmallInteger('image_hauteur')->nullable();

            // Origine
            $table->string('source', 20)->default('utilisateur');
            $table->string('source_nom', 100)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->char('source_empreinte', 64)->nullable()->unique()->comment('sha256 de l’URL normalisée');
            $table->foreignId('source_veille_id')->nullable()->constrained('sources_veille')->nullOnDelete();
            $table->foreignId('import_veille_id')->nullable()->constrained('imports_veille')->nullOnDelete();

            // Modération
            $table->unsignedTinyInteger('score_fiabilite')->nullable();
            $table->json('signaux_risque')->nullable();
            $table->string('motif_rejet', 30)->nullable();
            $table->text('motif_rejet_detail')->nullable();

            // Compteurs dénormalisés (increment / decrement atomiques)
            $table->unsignedInteger('nb_jaime')->default(0);
            $table->unsignedInteger('nb_commentaires')->default(0);
            $table->unsignedInteger('nb_partages')->default(0);
            $table->unsignedInteger('nb_vues')->default(0);
            $table->unsignedInteger('nb_reponses')->default(0);

            $table->unsignedTinyInteger('nb_prolongations')->default(0);
            $table->string('motif_cloture', 150)->nullable();
            $table->timestamp('publiee_le')->nullable();
            $table->timestamp('expire_le')->nullable();
            $table->timestamp('tri_le')->nullable()->comment('publiee_le, ou dernière remontée');
            $table->char('code_court', 6)->unique()->comment('lien SMS /p/{code}');
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');

            $table->index(['statut', 'tri_le', 'id']);
            $table->index(['type', 'statut', 'tri_le', 'id']);
            $table->index(['commune_id', 'statut', 'tri_le', 'id']);
            $table->index(['type_contrat', 'statut', 'tri_le', 'id']);
            $table->index(['auteur_id', 'type', 'statut']);
            $table->index(['entreprise_id', 'type', 'statut']);
            $table->index(['statut', 'expire_le']);
            $table->fullText(['titre', 'contenu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
