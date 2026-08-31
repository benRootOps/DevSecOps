<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrat d'API §12 : la FeeCategory porte directement montant/devise/nb tranches,
 * et un Payment est rattaché à une fee_category + numéro de tranche (pas à une
 * tranche interne). Additif : les colonnes internes existantes restent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories_frais', function (Blueprint $table) {
            $table->decimal('montant', 12, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->integer('nombre_tranches')->default(1);
        });

        Schema::table('versements', function (Blueprint $table) {
            $table->foreignId('categorie_frais_id')->nullable()->constrained('categories_frais')->nullOnDelete();
            $table->integer('numero_tranche')->nullable();
        });

        DB::statement('ALTER TABLE versements MODIFY tranche_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('versements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_frais_id');
            $table->dropColumn('numero_tranche');
        });

        Schema::table('categories_frais', function (Blueprint $table) {
            $table->dropColumn(['montant', 'devise', 'nombre_tranches']);
        });

        DB::statement('ALTER TABLE versements MODIFY tranche_id BIGINT UNSIGNED NOT NULL');
    }
};
