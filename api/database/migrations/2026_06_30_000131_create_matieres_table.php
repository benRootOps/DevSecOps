<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 4 — Cours/UE : matières (cf. bd-Edusphere_v2_0, §12).
return new class extends Migration {
    public function up(): void {
        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('unite_id')->constrained('unites_enseignement')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('intitule', 200);
            $table->smallInteger('volume_horaire')->nullable();
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->string('type_evaluation', 50)->nullable(); // 'Contrôle continu', 'Examen', 'TP', 'Mixte'
            $table->timestamps();
            $table->unique(['unite_id', 'code']);
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('matieres'); }
};
