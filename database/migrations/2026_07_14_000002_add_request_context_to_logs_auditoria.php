<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logs_auditoria')) {
            return;
        }

        $this->addColumnIfMissing('ip_cliente', fn (Blueprint $table) => $table->string('ip_cliente', 45)->nullable()->after('ip'));
        $this->addColumnIfMissing('ip_proxy', fn (Blueprint $table) => $table->string('ip_proxy', 45)->nullable()->after('ip_cliente'));
        $this->addColumnIfMissing('user_agent', fn (Blueprint $table) => $table->text('user_agent')->nullable()->after('ip_proxy'));
        $this->addColumnIfMissing('cf_ray', fn (Blueprint $table) => $table->string('cf_ray', 80)->nullable()->after('user_agent'));
        $this->addColumnIfMissing('host', fn (Blueprint $table) => $table->string('host', 190)->nullable()->after('cf_ray'));
        $this->addColumnIfMissing('rota', fn (Blueprint $table) => $table->string('rota', 255)->nullable()->after('host'));
        $this->addColumnIfMissing('metodo', fn (Blueprint $table) => $table->string('metodo', 10)->nullable()->after('rota'));
    }

    public function down(): void
    {
        if (! Schema::hasTable('logs_auditoria')) {
            return;
        }

        Schema::table('logs_auditoria', function (Blueprint $table): void {
            foreach (['metodo', 'rota', 'host', 'cf_ray', 'user_agent', 'ip_proxy', 'ip_cliente'] as $column) {
                if (Schema::hasColumn('logs_auditoria', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addColumnIfMissing(string $column, callable $callback): void
    {
        if (Schema::hasColumn('logs_auditoria', $column)) {
            return;
        }

        Schema::table('logs_auditoria', $callback);
    }
};
