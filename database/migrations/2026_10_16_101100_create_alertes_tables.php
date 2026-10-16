<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('type', 20)->nullable()->comment('null = toutes catégories');
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->string('type_contrat', 20)->nullable();
            $table->string('mots_cles')->nullable();
            $table->string('frequence', 20)->default('instantanee');
            $table->boolean('active')->default(true);
            $table->timestamp('derniere_notification_le')->nullable();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['active', 'type']);
        });

        // Journal des correspondances : anti-doublon de notification
        Schema::create('alertes_publications', function (Blueprint $table) {
            $table->foreignId('alerte_id')->constrained('alertes')->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->timestamp('notifie_le')->nullable();

            $table->primary(['alerte_id', 'publication_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_publications');
        Schema::dropIfExists('alertes');
    }
};
