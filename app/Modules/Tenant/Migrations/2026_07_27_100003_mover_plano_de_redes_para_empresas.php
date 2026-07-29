<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * A licenca desce de `redes` para `empresas`: cada empresa e um contrato individual.
 *
 * A rede deixa de ser a unidade comercial e passa a ser so o agrupamento de licencas
 * do mesmo dono. Nao ficam as duas colunas: duas fontes para o mesmo fato divergem.
 *
 * Escrita para ser re-executavel: DDL no MySQL nao e transacional, entao uma falha no
 * meio deixaria a migration marcada como pendente com parte do schema ja alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'plano_id')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->foreignId('plano_id')->nullable()->after('rede_id')->constrained('planos');
            });
        }

        if (Schema::hasColumn('redes', 'plano_id')) {
            // Backfill: toda empresa herda a licenca que era da sua rede.
            DB::table('redes')->select('id', 'plano_id')->orderBy('id')->chunk(200, function ($redes) {
                foreach ($redes as $rede) {
                    DB::table('empresas')
                        ->where('rede_id', $rede->id)
                        ->whereNull('plano_id')
                        ->update(['plano_id' => $rede->plano_id]);
                }
            });
        }

        // Rede orfa de plano (nao deveria existir) nao pode deixar empresa sem licenca.
        $idGratis = DB::table('planos')->where('slug', 'gratis')->value('id');
        if ($idGratis !== null) {
            DB::table('empresas')->whereNull('plano_id')->update(['plano_id' => $idGratis]);
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plano_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('redes', 'plano_id')) {
            $this->removerColunaPlano('redes');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('redes', 'plano_id')) {
            Schema::table('redes', function (Blueprint $table) {
                $table->foreignId('plano_id')->nullable()->after('nome')->constrained('planos');
            });
        }

        // Devolve para a rede a licenca da sua primeira empresa.
        DB::table('empresas')->select('rede_id', 'plano_id')->orderByDesc('id')->chunk(200, function ($empresas) {
            foreach ($empresas as $empresa) {
                DB::table('redes')
                    ->where('id', $empresa->rede_id)
                    ->update(['plano_id' => $empresa->plano_id]);
            }
        });

        if (Schema::hasColumn('empresas', 'plano_id')) {
            $this->removerColunaPlano('empresas');
        }
    }

    /**
     * Remove `plano_id` junto com a FK, no mesmo Blueprint (o SQLite reconstroi a tabela e
     * precisa das duas operacoes juntas — separa-las deixa a FK orfa e o schema invalido).
     *
     * O nome da constraint nao segue a convencao em `redes`: a tabela nasceu como `contas`
     * (`rename_contas_to_redes`) e o MySQL preserva `contas_plano_id_foreign` ao renomear.
     * Por isso descobrimos o nome real em vez de assumi-lo. No SQLite as FKs nao tem nome —
     * a forma por coluna e a correta la.
     */
    private function removerColunaPlano(string $tabela): void
    {
        $ehSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        $nomeFk = null;
        foreach (Schema::getForeignKeys($tabela) as $fk) {
            if (in_array('plano_id', $fk['columns'], true) && ! empty($fk['name'])) {
                $nomeFk = $fk['name'];
                break;
            }
        }

        Schema::table($tabela, function (Blueprint $table) use ($ehSqlite, $nomeFk) {
            if ($ehSqlite) {
                $table->dropForeign(['plano_id']);
            } elseif ($nomeFk !== null) {
                $table->dropForeign($nomeFk);
            }

            $table->dropColumn('plano_id');
        });
    }
};
