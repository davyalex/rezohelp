<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('emploi | service | formation');
            $table->string('nom', 100);
            $table->string('slug', 120)->unique();
            $table->string('icone', 50)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['type', 'actif', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
