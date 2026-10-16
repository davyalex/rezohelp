<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('details_service', function (Blueprint $table) {
            $table->foreignId('publication_id')->primary()->constrained('publications')->cascadeOnDelete();
            $table->string('metier', 100)->nullable();
            $table->unsignedInteger('tarif_indicatif')->nullable();
            $table->string('unite_tarif', 20)->nullable();
            $table->json('zones_intervention')->nullable()->comment('ids de communes');
            $table->string('disponibilite', 150)->nullable();
            $table->string('telephone_contact', 20)->nullable()->comment('E.164, seul numéro public');
            $table->boolean('afficher_telephone')->default(true);
            $table->string('whatsapp', 20)->nullable()->comment('E.164');
            // Demande de service
            $table->date('date_souhaitee')->nullable();
            $table->unsignedInteger('budget')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('details_service');
    }
};
