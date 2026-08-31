<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 12 — Reporting (cf. bd-Edusphere_v2_0, §24) : instantanés de rapports.
return new class extends Migration {
    public function up(): void {
        Schema::create('rapports_statistiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('type_rapport', 80); // dashboard / repartition / reussite...
            $table->string('titre', 200);
            $table->json('donnees');
            $table->foreignId('genere_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('genere_le')->nullable();
            $table->timestamps();
            $table->index(['etablissement_id', 'type_rapport']);
        });
    }
    public function down(): void { Schema::dropIfExists('rapports_statistiques'); }
};
