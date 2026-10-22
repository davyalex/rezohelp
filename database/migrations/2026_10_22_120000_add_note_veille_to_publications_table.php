<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes de la veille sur une annonce (« Détail non ouvert ce passage, à vérifier », « contact Gmail ») :
 * retirées du texte public, gardées pour la modération (D-067).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->text('note_veille')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropColumn('note_veille');
        });
    }
};
