<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transferencias')) {
            return;
        }

        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('propriedade_id')->index();
            $table->unsignedBigInteger('conta_origem_id')->index();
            $table->unsignedBigInteger('conta_destino_id')->index();
            $table->decimal('valor', 12, 2);
            $table->date('data_transferencia');
            $table->string('descricao', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};
