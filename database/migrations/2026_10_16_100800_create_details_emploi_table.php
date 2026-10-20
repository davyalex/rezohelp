<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('details_emploi', function (Blueprint $table) {
            $table->foreignId('publication_id')->primary()->constrained('publications')->cascadeOnDelete();
            $table->boolean('employeur_particulier')->default(false);
            $table->string('type_contrat', 20)->nullable();
            $table->unsignedSmallInteger('nb_postes')->default(1);
            $table->unsignedInteger('salaire_min')->nullable();
            $table->unsignedInteger('salaire_max')->nullable();
            $table->boolean('salaire_a_negocier')->default(false);
            $table->char('devise', 3)->default('XOF');
            $table->string('experience_requise', 100)->nullable();
            $table->string('niveau_etude', 100)->nullable();
            $table->date('date_limite')->nullable();
            $table->string('mode_candidature', 20)->default('rezohelp');
            $table->string('contact_candidature')->nullable();

            $table->index('type_contrat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('details_emploi');
    }
};
