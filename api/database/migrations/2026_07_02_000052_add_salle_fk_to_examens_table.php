<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 6 — branche la FK examens.salle_id → salles (colonne créée sans FK au module #8,
// la table `salles` n'existant pas encore à ce moment).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->foreign('salle_id')->references('id')->on('salles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->dropForeign(['salle_id']);
        });
    }
};
