<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 200);
            $table->string('documento', 20)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('conta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
