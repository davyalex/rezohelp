<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenoms', 150)->nullable();
            $table->string('telephone', 20)->nullable()->unique()->comment('E.164 ; null après anonymisation');
            $table->char('pays_code', 2)->default('CI');
            $table->timestamp('telephone_verifie_le')->nullable();
            $table->string('email')->nullable();
            $table->string('mot_de_passe');
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->string('confiance', 20)->default('nouveau');
            $table->timestamp('derniere_connexion_le')->nullable();
            $table->timestamp('suspendu_le')->nullable();
            $table->timestamp('suspendu_jusqu_au')->nullable();
            $table->string('motif_suspension')->nullable();
            $table->boolean('doit_changer_mot_de_passe')->default(false);
            $table->rememberToken();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');

            $table->foreign('pays_code')->references('code')->on('pays')->cascadeOnUpdate()->restrictOnDelete();
            $table->index('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
