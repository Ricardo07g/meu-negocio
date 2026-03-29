<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->integer('max_empresas')->default(1);
            $table->integer('max_usuarios')->default(2);
            $table->boolean('tem_estoque')->default(false);
            $table->boolean('tem_financeiro')->default(false);
            $table->boolean('tem_relatorios')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
