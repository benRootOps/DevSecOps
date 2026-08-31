<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrat d'API §10 : une note (Grade) est rattachée à un examen (exam_id).
 * On ajoute examen_id et on rend session_examen_id nullable (additif ; le calcul
 * de moyennes reste basé sur matiere_id/valeur/bareme, renseignés depuis l'examen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('examen_id')->nullable()->constrained('examens')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE notes MODIFY session_examen_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('examen_id');
        });

        DB::statement('ALTER TABLE notes MODIFY session_examen_id BIGINT UNSIGNED NOT NULL');
    }
};
