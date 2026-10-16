<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('slug', 170)->unique();
            $table->string('secteur', 100)->nullable();
            $table->string('site_web')->nullable();
            $table->string('telephone', 20)->nullable()->comment('E.164');
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->boolean('verifiee')->default(false);
            $table->foreignId('proprietaire_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->softDeletes('supprime_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
