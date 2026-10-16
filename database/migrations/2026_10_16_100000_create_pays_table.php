<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pays', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique()->comment('ISO 3166-1 alpha-2');
            $table->string('nom', 100);
            $table->string('indicatif', 6)->comment('ex. +225');
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['actif', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pays');
    }
};
