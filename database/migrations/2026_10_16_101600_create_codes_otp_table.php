<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes OTP envoyés par SMS (CONTEXTE §9) : seule une empreinte du code est stockée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_otp', function (Blueprint $table) {
            $table->id();
            $table->string('telephone', 20)->comment('E.164');
            $table->string('objet', 30);
            $table->char('empreinte_code', 64)->comment('HMAC-SHA256 du code');
            $table->unsignedTinyInteger('essais')->default(0);
            $table->timestamp('expire_le');
            $table->timestamp('utilise_le')->nullable();
            $table->timestamp('cree_le')->nullable();

            $table->index(['telephone', 'objet', 'utilise_le']);
            $table->index('expire_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_otp');
    }
};
