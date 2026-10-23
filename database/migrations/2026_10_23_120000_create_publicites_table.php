<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publicités (bannières) gérées dans l'admin, par emplacement (D-074).
 * Images dans la médiathèque (collections « principale » et « secondaire »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicites', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 120)->comment('nom interne, pour l’équipe');
            $table->string('annonceur', 120);
            $table->string('lien', 500);
            $table->string('texte_alternatif', 200);
            $table->string('emplacement', 30);
            $table->date('debut_le')->nullable();
            $table->date('fin_le')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('poids')->default(1)->comment('part dans la rotation (1 à 10)');
            $table->unsignedInteger('nb_impressions')->default(0);
            $table->unsignedInteger('nb_clics')->default(0);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');

            $table->index(['emplacement', 'active', 'debut_le', 'fin_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicites');
    }
};
