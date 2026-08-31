<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — CONFIG (extension) : barème des grades lettres (A+, A, B+…) par établissement.
return new class extends Migration {
    public function up(): void {
        Schema::create('bareme_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->decimal('borne_min', 5, 2);   // borne basse (sur le barème /20)
            $table->decimal('borne_max', 5, 2);   // borne haute
            $table->string('lettre', 5);          // 'A+', 'A', 'B+'...
            $table->decimal('points_sur_5', 3, 2)->default(0); // équivalent GPA (sur 5)
            $table->smallInteger('ordre')->default(0);
            $table->timestamps();
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('bareme_grades'); }
};
