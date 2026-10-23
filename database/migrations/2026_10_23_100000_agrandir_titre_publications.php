<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titres de publication plus longs (D-069) : 150 → 255 caractères.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->string('titre', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->string('titre', 150)->change();
        });
    }
};
