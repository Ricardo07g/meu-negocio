<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('produto_id')->constrained('produtos');
            $table->string('tipo', 20);
            $table->integer('quantidade');
            $table->timestamps();

            $table->index(['conta_id', 'empresa_id']);
            $table->index('produto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_estoque');
    }
};
