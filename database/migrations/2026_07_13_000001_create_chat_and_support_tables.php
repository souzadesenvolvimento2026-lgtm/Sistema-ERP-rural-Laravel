<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureChatUsuariosOnline();
        $this->ensureChatMensagens();
        $this->ensureChatAnexos();
        $this->ensureSuporteConversas();
        $this->ensureSuporteMensagens();
        $this->ensureSuporteAnexos();
    }

    public function down(): void
    {
        // Migration intencionalmente não destrutiva.
        // Estas tabelas podem conter conversas reais do suporte/chat interno.
    }

    private function ensureChatUsuariosOnline(): void
    {
        if (! Schema::hasTable('chat_usuarios_online')) {
            Schema::create('chat_usuarios_online', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_id')->primary();
                $table->string('sessao_id', 120)->nullable();
                $table->string('sessao_token', 160)->nullable();
                $table->timestamp('atualizado_em')->nullable()->index();
            });

            return;
        }

        $this->addColumnIfMissing('chat_usuarios_online', 'sessao_id', fn (Blueprint $table) => $table->string('sessao_id', 120)->nullable());
        $this->addColumnIfMissing('chat_usuarios_online', 'sessao_token', fn (Blueprint $table) => $table->string('sessao_token', 160)->nullable());
        $this->addColumnIfMissing('chat_usuarios_online', 'atualizado_em', fn (Blueprint $table) => $table->timestamp('atualizado_em')->nullable());
    }

    private function ensureChatMensagens(): void
    {
        if (! Schema::hasTable('chat_mensagens')) {
            Schema::create('chat_mensagens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('remetente_usuario_id')->index();
                $table->unsignedBigInteger('destinatario_usuario_id')->index();
                $table->text('mensagem');
                $table->timestamp('lida_em')->nullable()->index();
                $table->timestamp('criada_em')->useCurrent();
                $table->index(['remetente_usuario_id', 'destinatario_usuario_id'], 'idx_chat_mensagens_conversa');
            });

            return;
        }

        $this->addColumnIfMissing('chat_mensagens', 'remetente_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('remetente_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('chat_mensagens', 'destinatario_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('destinatario_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('chat_mensagens', 'mensagem', fn (Blueprint $table) => $table->text('mensagem')->nullable());
        $this->addColumnIfMissing('chat_mensagens', 'lida_em', fn (Blueprint $table) => $table->timestamp('lida_em')->nullable());
        $this->addColumnIfMissing('chat_mensagens', 'criada_em', fn (Blueprint $table) => $table->timestamp('criada_em')->useCurrent());
    }

    private function ensureChatAnexos(): void
    {
        if (! Schema::hasTable('chat_anexos')) {
            Schema::create('chat_anexos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mensagem_id')->index();
                $table->unsignedBigInteger('remetente_usuario_id')->index();
                $table->unsignedBigInteger('destinatario_usuario_id')->index();
                $table->string('nome_original', 255);
                $table->string('nome_arquivo', 255)->nullable();
                $table->string('caminho_relativo', 500)->nullable();
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamanho_bytes')->default(0);
                $table->unsignedBigInteger('baixado_por')->nullable()->index();
                $table->timestamp('baixado_em')->nullable();
                $table->timestamp('expira_em')->nullable()->index();
                $table->timestamp('criado_em')->useCurrent();
            });

            return;
        }

        $this->addColumnIfMissing('chat_anexos', 'mensagem_id', fn (Blueprint $table) => $table->unsignedBigInteger('mensagem_id')->nullable()->index());
        $this->addColumnIfMissing('chat_anexos', 'remetente_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('remetente_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('chat_anexos', 'destinatario_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('destinatario_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('chat_anexos', 'nome_original', fn (Blueprint $table) => $table->string('nome_original', 255)->nullable());
        $this->addColumnIfMissing('chat_anexos', 'nome_arquivo', fn (Blueprint $table) => $table->string('nome_arquivo', 255)->nullable());
        $this->addColumnIfMissing('chat_anexos', 'caminho_relativo', fn (Blueprint $table) => $table->string('caminho_relativo', 500)->nullable());
        $this->addColumnIfMissing('chat_anexos', 'mime', fn (Blueprint $table) => $table->string('mime', 120)->nullable());
        $this->addColumnIfMissing('chat_anexos', 'tamanho_bytes', fn (Blueprint $table) => $table->unsignedBigInteger('tamanho_bytes')->default(0));
        $this->addColumnIfMissing('chat_anexos', 'baixado_por', fn (Blueprint $table) => $table->unsignedBigInteger('baixado_por')->nullable());
        $this->addColumnIfMissing('chat_anexos', 'baixado_em', fn (Blueprint $table) => $table->timestamp('baixado_em')->nullable());
        $this->addColumnIfMissing('chat_anexos', 'expira_em', fn (Blueprint $table) => $table->timestamp('expira_em')->nullable());
        $this->addColumnIfMissing('chat_anexos', 'criado_em', fn (Blueprint $table) => $table->timestamp('criado_em')->useCurrent());
    }

    private function ensureSuporteConversas(): void
    {
        if (! Schema::hasTable('suporte_conversas')) {
            Schema::create('suporte_conversas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('propriedade_id')->nullable()->index();
                $table->unsignedBigInteger('usuario_id')->index();
                $table->unsignedBigInteger('atendente_usuario_id')->nullable()->index();
                $table->string('assunto', 180);
                $table->string('status', 40)->default('aberta')->index();
                $table->string('origem', 40)->default('manual');
                $table->string('ia_status', 40)->default('nao_aplicado');
                $table->string('nivel_atendimento', 40)->default('colaborador');
                $table->timestamp('atendimento_assumido_em')->nullable();
                $table->timestamp('encerramento_solicitado_em')->nullable();
                $table->string('encerramento_solicitado_por', 40)->nullable();
                $table->timestamp('encerrada_em')->nullable();
                $table->timestamp('criada_em')->useCurrent();
                $table->timestamp('atualizada_em')->nullable()->useCurrent()->index();
            });

            return;
        }

        $this->addColumnIfMissing('suporte_conversas', 'propriedade_id', fn (Blueprint $table) => $table->unsignedBigInteger('propriedade_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_conversas', 'usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('usuario_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_conversas', 'atendente_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('atendente_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_conversas', 'assunto', fn (Blueprint $table) => $table->string('assunto', 180)->default('Duvida do cliente'));
        $this->addColumnIfMissing('suporte_conversas', 'status', fn (Blueprint $table) => $table->string('status', 40)->default('aberta'));
        $this->addColumnIfMissing('suporte_conversas', 'origem', fn (Blueprint $table) => $table->string('origem', 40)->default('manual'));
        $this->addColumnIfMissing('suporte_conversas', 'ia_status', fn (Blueprint $table) => $table->string('ia_status', 40)->default('nao_aplicado'));
        $this->addColumnIfMissing('suporte_conversas', 'nivel_atendimento', fn (Blueprint $table) => $table->string('nivel_atendimento', 40)->default('colaborador'));
        $this->addColumnIfMissing('suporte_conversas', 'atendimento_assumido_em', fn (Blueprint $table) => $table->timestamp('atendimento_assumido_em')->nullable());
        $this->addColumnIfMissing('suporte_conversas', 'encerramento_solicitado_em', fn (Blueprint $table) => $table->timestamp('encerramento_solicitado_em')->nullable());
        $this->addColumnIfMissing('suporte_conversas', 'encerramento_solicitado_por', fn (Blueprint $table) => $table->string('encerramento_solicitado_por', 40)->nullable());
        $this->addColumnIfMissing('suporte_conversas', 'encerrada_em', fn (Blueprint $table) => $table->timestamp('encerrada_em')->nullable());
        $this->addColumnIfMissing('suporte_conversas', 'criada_em', fn (Blueprint $table) => $table->timestamp('criada_em')->useCurrent());
        $this->addColumnIfMissing('suporte_conversas', 'atualizada_em', fn (Blueprint $table) => $table->timestamp('atualizada_em')->nullable()->useCurrent());
    }

    private function ensureSuporteMensagens(): void
    {
        if (! Schema::hasTable('suporte_mensagens')) {
            Schema::create('suporte_mensagens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversa_id')->index();
                $table->unsignedBigInteger('autor_usuario_id')->nullable()->index();
                $table->string('autor_tipo', 30)->index();
                $table->text('mensagem');
                $table->boolean('lida_admin')->default(false)->index();
                $table->boolean('lida_cliente')->default(false)->index();
                $table->timestamp('criada_em')->useCurrent();
            });

            return;
        }

        $this->addColumnIfMissing('suporte_mensagens', 'conversa_id', fn (Blueprint $table) => $table->unsignedBigInteger('conversa_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_mensagens', 'autor_usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('autor_usuario_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_mensagens', 'autor_tipo', fn (Blueprint $table) => $table->string('autor_tipo', 30)->default('cliente'));
        $this->addColumnIfMissing('suporte_mensagens', 'mensagem', fn (Blueprint $table) => $table->text('mensagem')->nullable());
        $this->addColumnIfMissing('suporte_mensagens', 'lida_admin', fn (Blueprint $table) => $table->boolean('lida_admin')->default(false));
        $this->addColumnIfMissing('suporte_mensagens', 'lida_cliente', fn (Blueprint $table) => $table->boolean('lida_cliente')->default(false));
        $this->addColumnIfMissing('suporte_mensagens', 'criada_em', fn (Blueprint $table) => $table->timestamp('criada_em')->useCurrent());
    }

    private function ensureSuporteAnexos(): void
    {
        if (! Schema::hasTable('suporte_anexos')) {
            Schema::create('suporte_anexos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mensagem_id')->index();
                $table->unsignedBigInteger('conversa_id')->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('nome_original', 255);
                $table->string('nome_arquivo', 255)->nullable();
                $table->string('caminho_relativo', 500)->nullable();
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamanho_bytes')->default(0);
                $table->unsignedBigInteger('baixado_por')->nullable()->index();
                $table->timestamp('baixado_em')->nullable();
                $table->timestamp('expira_em')->nullable()->index();
                $table->timestamp('criado_em')->useCurrent();
            });

            return;
        }

        $this->addColumnIfMissing('suporte_anexos', 'mensagem_id', fn (Blueprint $table) => $table->unsignedBigInteger('mensagem_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_anexos', 'conversa_id', fn (Blueprint $table) => $table->unsignedBigInteger('conversa_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_anexos', 'usuario_id', fn (Blueprint $table) => $table->unsignedBigInteger('usuario_id')->nullable()->index());
        $this->addColumnIfMissing('suporte_anexos', 'nome_original', fn (Blueprint $table) => $table->string('nome_original', 255)->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'nome_arquivo', fn (Blueprint $table) => $table->string('nome_arquivo', 255)->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'caminho_relativo', fn (Blueprint $table) => $table->string('caminho_relativo', 500)->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'mime', fn (Blueprint $table) => $table->string('mime', 120)->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'tamanho_bytes', fn (Blueprint $table) => $table->unsignedBigInteger('tamanho_bytes')->default(0));
        $this->addColumnIfMissing('suporte_anexos', 'baixado_por', fn (Blueprint $table) => $table->unsignedBigInteger('baixado_por')->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'baixado_em', fn (Blueprint $table) => $table->timestamp('baixado_em')->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'expira_em', fn (Blueprint $table) => $table->timestamp('expira_em')->nullable());
        $this->addColumnIfMissing('suporte_anexos', 'criado_em', fn (Blueprint $table) => $table->timestamp('criado_em')->useCurrent());
    }

    /**
     * @param callable(Blueprint): void $definition
     */
    private function addColumnIfMissing(string $tableName, string $columnName, callable $definition): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }
};
