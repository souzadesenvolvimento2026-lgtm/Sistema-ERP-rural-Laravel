<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produto_estoque_movimentos')) {
            return;
        }

        $this->adicionarColuna('destino_tipo', function (Blueprint $table) {
            $table->string('destino_tipo', 40)->nullable()->after('tipo');
        });

        $this->adicionarColuna('safra_id', function (Blueprint $table) {
            $table->unsignedBigInteger('safra_id')->nullable()->after('destino_tipo');
        });

        $this->adicionarColuna('talhao_id', function (Blueprint $table) {
            $table->unsignedBigInteger('talhao_id')->nullable()->after('safra_id');
        });

        $this->adicionarColuna('maquina_id', function (Blueprint $table) {
            $table->unsignedBigInteger('maquina_id')->nullable()->after('talhao_id');
        });

        $this->adicionarColuna('maquina_lancamento_id', function (Blueprint $table) {
            $table->unsignedBigInteger('maquina_lancamento_id')->nullable()->after('maquina_id');
        });

        $this->adicionarColuna('motivo', function (Blueprint $table) {
            $table->string('motivo', 120)->nullable()->after('maquina_lancamento_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('produto_estoque_movimentos')) {
            return;
        }

        Schema::table('produto_estoque_movimentos', function (Blueprint $table) {
            foreach ([
                'motivo',
                'maquina_lancamento_id',
                'maquina_id',
                'talhao_id',
                'safra_id',
                'destino_tipo',
            ] as $column) {
                if (Schema::hasColumn('produto_estoque_movimentos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function adicionarColuna(string $coluna, callable $callback): void
    {
        if (Schema::hasColumn('produto_estoque_movimentos', $coluna)) {
            return;
        }

        Schema::table('produto_estoque_movimentos', $callback);
    }
};
