<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perf : PostgreSQL n'indexe pas automatiquement les colonnes de clés étrangères.
// On ajoute des index sur les colonnes réellement filtrées / jointes (hot paths).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->index('etudiant_id'); // statistiques d'assiduité par étudiant
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->index(['classe_id', 'semestre_id']); // emploi du temps d'une classe
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->index('abonnement_id'); // chargement des factures d'un abonnement
        });

        Schema::table('transactions_paiement', function (Blueprint $table) {
            $table->index('abonnement_id');
            $table->index('facture_id');
            $table->index('type_transaction');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropIndex(['etudiant_id']);
        });
        Schema::table('seances', function (Blueprint $table) {
            $table->dropIndex(['classe_id', 'semestre_id']);
        });
        Schema::table('factures', function (Blueprint $table) {
            $table->dropIndex(['abonnement_id']);
        });
        Schema::table('transactions_paiement', function (Blueprint $table) {
            $table->dropIndex(['abonnement_id']);
            $table->dropIndex(['facture_id']);
            $table->dropIndex(['type_transaction']);
        });
    }
};
