<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrat d'API §9 : un Exam porte un `type` (continuous_assessment…) et un `name`,
 * et n'exige pas de session d'examen. On ajoute type/intitule et on rend
 * session_examen_id nullable (additif ; l'ancien flux « par session » reste possible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->string('type', 50)->nullable();
            $table->string('intitule', 255)->nullable();
        });

        DB::statement('ALTER TABLE examens MODIFY session_examen_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->dropColumn(['type', 'intitule']);
        });

        DB::statement('ALTER TABLE examens MODIFY session_examen_id BIGINT UNSIGNED NOT NULL');
    }
};
