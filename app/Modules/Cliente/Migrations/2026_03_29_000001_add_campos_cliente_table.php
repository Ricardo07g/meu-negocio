<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->date('data_nascimento')->nullable()->after('nome');
            $table->string('cpf', 14)->nullable()->after('data_nascimento');
            $table->string('sexo', 10)->nullable()->after('cpf');
            $table->boolean('telefone_whatsapp')->default(false)->after('telefone');
            $table->string('cep', 9)->nullable()->after('email');
            $table->string('estado', 2)->nullable()->after('cep');
            $table->string('cidade', 100)->nullable()->after('estado');
            $table->string('bairro', 100)->nullable()->after('cidade');
            $table->string('logradouro', 200)->nullable()->after('bairro');
            $table->string('numero', 20)->nullable()->after('logradouro');
            $table->string('complemento', 100)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'data_nascimento', 'cpf', 'sexo', 'telefone_whatsapp',
                'cep', 'estado', 'cidade', 'bairro', 'logradouro', 'numero', 'complemento',
            ]);
        });
    }
};
