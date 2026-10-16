<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources_veille', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('domaine', 150)->nullable()->unique()->comment('null pour la source Inconnue');
            $table->string('niveau_confiance', 10)->default('bas');
            $table->boolean('publication_auto')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources_veille');
    }
};
