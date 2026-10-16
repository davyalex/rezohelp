<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->char('pays_code', 2);
            $table->string('ville', 100);
            $table->string('nom', 100);
            $table->string('slug', 120)->unique();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->foreign('pays_code')->references('code')->on('pays')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['actif', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
