<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->after('nome');
            $table->string('codigo_barras', 50)->nullable()->after('codigo');
            $table->text('descricao')->nullable()->after('codigo_barras');
            $table->foreignId('categoria_produto_id')->nullable()->after('descricao')
                ->constrained('categorias_produto')->nullOnDelete();
            $table->decimal('valor_custo', 10, 2)->nullable()->after('categoria_produto_id');
            $table->renameColumn('valor', 'valor_venda');
            $table->integer('estoque_minimo')->nullable()->after('quantidade');
            $table->string('unidade', 20)->nullable()->after('estoque_minimo');
            $table->boolean('ativo')->default(true)->after('unidade');
            $table->text('observacoes')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['categoria_produto_id']);
            $table->dropColumn([
                'codigo', 'codigo_barras', 'descricao', 'categoria_produto_id',
                'valor_custo', 'estoque_minimo', 'unidade', 'ativo', 'observacoes',
            ]);
            $table->renameColumn('valor_venda', 'valor');
        });
    }
};
