<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 9 — Résultats individuels de délibération (cf. §19).
// Les valeurs "originales" viennent du service de calcul (#8) ; le directeur peut
// override moyenne_finale / credits_valides / decision (traces audit dans `ajuste_par`).
return new class extends Migration {
    public function up(): void {
        Schema::create('deliberation_resultats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliberation_id')->constrained('deliberations')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->decimal('moyenne_finale', 5, 2)->nullable();
            $table->string('mention', 50)->nullable();
            $table->smallInteger('credits_valides')->default(0);
            $table->string('decision', 50); // Admis / Ajourné / Rattrapage / Exclu / Abandonné
            $table->text('observations')->nullable();
            $table->foreignId('ajuste_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('ajuste_le')->nullable();
            $table->timestamps();
            $table->unique(['deliberation_id', 'etudiant_id'], 'delib_resultats_unicite');
        });
    }
    public function down(): void { Schema::dropIfExists('deliberation_resultats'); }
};
