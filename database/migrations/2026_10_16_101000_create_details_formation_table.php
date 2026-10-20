<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('details_formation', function (Blueprint $table) {
            $table->foreignId('publication_id')->primary()->constrained('publications')->cascadeOnDelete();
            $table->string('organisme', 150)->nullable()->comment('texte de secours sans entreprise liée');
            $table->string('modalite', 20)->default('presentiel');
            $table->string('duree', 100)->nullable();
            $table->date('date_debut')->nullable();
            $table->unsignedInteger('prix')->default(0)->comment('XOF, 0 = gratuit');
            $table->boolean('certifiante')->default(false);
            $table->unsignedSmallInteger('places')->nullable();
            $table->string('mode_inscription', 20)->default('rezohelp');
            $table->string('contact_inscription')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('details_formation');
    }
};
