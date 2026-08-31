<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->boolean('est_systeme')->default(false);
            $table->timestamps();
            $table->unique(['etablissement_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('roles'); }
};
