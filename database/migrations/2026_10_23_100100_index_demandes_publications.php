<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onglet « Demandes » du fil (D-069) : services filtrés par sous-type, triés comme le fil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->index(['type', 'sous_type', 'statut', 'tri_le', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropIndex(['type', 'sous_type', 'statut', 'tri_le', 'id']);
        });
    }
};
