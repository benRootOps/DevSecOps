<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrat d'API §8 : la présence s'enregistre par (matière, classe, date) et non
 * seulement par séance. On rend seance_id nullable et on ajoute matiere_id/classe_id/date.
 * Additif : l'ancien flux « par séance » continue de fonctionner.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE presences MODIFY seance_id BIGINT UNSIGNED NULL');

        Schema::table('presences', function (Blueprint $table) {
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->date('date')->nullable();
            $table->index(['classe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropIndex(['classe_id', 'date']);
            $table->dropConstrainedForeignId('matiere_id');
            $table->dropConstrainedForeignId('classe_id');
            $table->dropColumn('date');
        });

        DB::statement('ALTER TABLE presences MODIFY seance_id BIGINT UNSIGNED NOT NULL');
    }
};
