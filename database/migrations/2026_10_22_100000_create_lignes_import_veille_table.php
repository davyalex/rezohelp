<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sort de chaque ligne d'un import de la veille (CONTEXTE §13.3.E) : importée → publication,
 * doublon → publication existante, rejetée → raison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_import_veille', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_veille_id')->constrained('imports_veille')->cascadeOnDelete();
            $table->unsignedInteger('numero')->comment('numéro de l’enregistrement dans le fichier (en-tête = 1)');
            $table->string('statut', 20);
            $table->string('intitule', 255)->nullable();
            $table->string('lien', 2048)->nullable();
            $table->foreignId('publication_id')->nullable()
                ->comment('publication créée, ou publication existante pour un doublon')
                ->constrained('publications')->nullOnDelete();
            $table->string('raison', 255)->nullable();
            $table->timestamp('cree_le')->nullable();

            $table->index(['import_veille_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_import_veille');
    }
};
