<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réactions, commentaires, enregistrements, réponses et signalements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->string('type', 20)->default('jaime');
            $table->timestamp('cree_le')->nullable();

            $table->unique(['publication_id', 'utilisateur_id']);
            $table->index('utilisateur_id');
        });

        Schema::create('commentaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('commentaires')->cascadeOnDelete();
            $table->text('contenu');
            $table->string('statut', 20)->default('visible');
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');

            $table->index(['publication_id', 'statut', 'cree_le']);
        });

        Schema::create('enregistrements', function (Blueprint $table) {
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->timestamp('cree_le')->nullable();

            $table->primary(['utilisateur_id', 'publication_id']);
        });

        Schema::create('reponses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('message')->nullable();
            $table->boolean('telephone_partage')->default(false);
            $table->string('statut', 20)->default('envoyee');
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->unique(['publication_id', 'utilisateur_id']);
            $table->index(['utilisateur_id', 'type', 'cree_le']);
        });

        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('motif', 20);
            $table->text('commentaire')->nullable();
            $table->boolean('retenu')->nullable()->comment('décision de modération : signalement fondé ou non');
            $table->timestamp('traite_le')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->unique(['publication_id', 'utilisateur_id']);
            $table->index(['traite_le', 'motif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
        Schema::dropIfExists('reponses');
        Schema::dropIfExists('enregistrements');
        Schema::dropIfExists('commentaires');
        Schema::dropIfExists('reactions');
    }
};
