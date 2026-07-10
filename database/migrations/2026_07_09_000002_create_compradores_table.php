<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compradores')) {
            return;
        }

        Schema::create('compradores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('propriedade_id')->index();
            $table->string('nome', 150);
            $table->string('documento', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->useCurrent();
            $table->unique(['propriedade_id', 'nome'], 'uk_comprador_prop_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compradores');
    }
};
