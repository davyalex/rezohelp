<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans, produits, abonnements, commandes, crédits et mises en avant (CONTEXTE §7, §14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 100);
            $table->text('description')->nullable();
            $table->string('cible', 20);
            $table->string('famille', 50)->nullable()->comment('les plans d’une même famille partagent un seul essai');
            $table->unsignedInteger('prix_fcfa')->default(0)->comment('TTC');
            $table->unsignedSmallInteger('duree_jours')->default(30);
            $table->unsignedSmallInteger('duree_essai_jours')->default(0);
            $table->boolean('est_defaut')->default(false);
            // Garantit au plus un plan par défaut par cible
            $table->string('cible_par_defaut', 20)->nullable()->storedAs('IF(est_defaut, cible, NULL)')->unique();
            $table->boolean('actif')->default(true);
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['cible', 'actif', 'visible', 'ordre']);
        });

        Schema::create('plan_limites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('cle', 50);
            $table->string('valeur', 20)->comment('entier, -1 illimité, 0/1 pour un booléen');

            $table->unique(['plan_id', 'cle']);
        });

        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 100);
            $table->text('description')->nullable();
            $table->string('cible', 20);
            $table->string('type', 30);
            $table->unsignedInteger('prix_fcfa')->comment('TTC');
            $table->unsignedSmallInteger('duree_jours')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->comment('CMD-2026-000123');
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->morphs('titulaire');
            $table->string('article_type', 20)->comment('plan | produit');
            $table->unsignedBigInteger('article_id');
            $table->foreignId('publication_id')->nullable()->constrained('publications')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->unsignedInteger('montant_ttc_fcfa');
            $table->decimal('taux_tva', 5, 2);
            $table->unsignedInteger('montant_ht_fcfa');
            $table->string('statut', 20)->default('en_attente');
            $table->string('passerelle', 20);
            $table->string('reference_passerelle', 100)->nullable()->unique();
            $table->string('moyen_paiement', 30)->nullable();
            $table->timestamp('payee_le')->nullable();
            $table->string('numero_recu', 20)->nullable()->unique()->comment('REC-2026-000123');
            $table->json('donnees_passerelle')->nullable();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['article_type', 'article_id']);
            $table->index(['statut', 'payee_le']);
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->morphs('titulaire');
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('statut', 20);
            $table->string('origine', 20);
            $table->timestamp('debut_le')->nullable();
            $table->timestamp('fin_le')->nullable();
            $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->string('motif')->nullable();
            $table->json('relances_envoyees')->nullable()->comment('j3, j1, j0 : relances de fin déjà envoyées');
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['titulaire_type', 'titulaire_id', 'statut', 'fin_le'], 'abonnements_titulaire_statut_fin_index');
            $table->index(['statut', 'fin_le']);
        });

        // Numéros sans trou (commandes, reçus), un compteur par préfixe et par année
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('cle', 40)->primary();
            $table->unsignedBigInteger('valeur')->default(0);
        });

        Schema::create('essais_utilises', function (Blueprint $table) {
            $table->char('empreinte_telephone', 64)->comment('SHA-256 du numéro E.164');
            $table->string('famille', 50);
            $table->timestamp('utilise_le')->nullable();

            $table->primary(['empreinte_telephone', 'famille']);
        });

        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('titulaire');
            $table->string('cle', 50);
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('consommee')->default(0);
            $table->timestamp('expire_le')->nullable();
            $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();

            $table->index(['titulaire_type', 'titulaire_id', 'cle', 'expire_le'], 'credits_titulaire_cle_index');
        });

        Schema::create('notifications_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('passerelle', 20);
            $table->string('reference', 100)->nullable();
            $table->json('charge_utile');
            $table->boolean('signature_valide')->default(false);
            $table->timestamp('traitee_le')->nullable();
            $table->text('erreur')->nullable();
            $table->timestamp('cree_le')->nullable();

            $table->index(['passerelle', 'reference']);
        });

        Schema::create('mises_en_avant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->string('type', 20);
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->timestamp('debut_le')->nullable();
            $table->timestamp('fin_le')->nullable();
            $table->timestamp('suspendue_le')->nullable();
            $table->timestamp('derniere_impression_le')->nullable()->comment('rotation des cartes sponsorisées');
            $table->unsignedInteger('nb_impressions')->default(0);
            $table->unsignedInteger('nb_clics')->default(0);
            $table->timestamp('cree_le')->nullable();

            $table->index(['type', 'suspendue_le', 'debut_le', 'fin_le']);
        });

        Schema::create('sms_envoyes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('telephone', 20);
            $table->string('message', 320);
            $table->string('motif', 20);
            $table->string('fournisseur', 20);
            $table->string('statut', 20)->default('en_file');
            $table->string('reference', 100)->nullable();
            $table->unsignedInteger('cout_fcfa')->default(0);
            $table->timestamp('envoye_le')->nullable();
            $table->timestamp('cree_le')->nullable();

            $table->index(['utilisateur_id', 'motif', 'cree_le']);
            $table->index('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_envoyes');
        Schema::dropIfExists('mises_en_avant');
        Schema::dropIfExists('notifications_paiement');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('essais_utilises');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('commandes');
        Schema::dropIfExists('produits');
        Schema::dropIfExists('plan_limites');
        Schema::dropIfExists('plans');
    }
};
