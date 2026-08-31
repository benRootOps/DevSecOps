<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('libelle', 200);
            $table->string('module', 80);
            $table->text('description')->nullable();
            $table->timestamp('cree_le')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('permissions'); }
};
