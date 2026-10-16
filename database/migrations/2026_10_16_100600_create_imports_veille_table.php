<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_veille', function (Blueprint $table) {
            $table->id();
            $table->string('fichier_drive_id')->unique();
            $table->string('nom_fichier');
            $table->string('statut', 20)->default('en_cours');
            $table->unsignedInteger('nb_lignes')->default(0);
            $table->unsignedInteger('nb_importees')->default(0);
            $table->unsignedInteger('nb_doublons')->default(0);
            $table->unsignedInteger('nb_rejetees')->default(0);
            $table->text('erreur')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['statut', 'traite_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports_veille');
    }
};
