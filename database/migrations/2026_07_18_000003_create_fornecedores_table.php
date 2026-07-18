<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('fornecedores')) {
            return;
        }

        Schema::create('fornecedores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('propriedade_id')->index();
            $table->string('nome', 160);
            $table->string('documento', 20)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();

            $table->index(['propriedade_id', 'ativo']);
            $table->index(['propriedade_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
