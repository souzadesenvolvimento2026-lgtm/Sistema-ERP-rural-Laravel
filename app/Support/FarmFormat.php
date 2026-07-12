<?php

namespace App\Support;

use Carbon\Carbon;

class FarmFormat
{
    public static function money($value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public static function decimal($value, int $places = 2): string
    {
        $formatted = number_format((float) $value, $places, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public static function percentage($value, int $places = 2): string
    {
        return number_format((float) $value, $places, ',', '.').'%';
    }

    public static function date($value): string
    {
        if (! $value) {
            return '-';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    public static function bool($value): string
    {
        return (int) $value === 1 ? 'Sim' : 'Não';
    }

    public static function value($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    public static function statusLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $labels = [
            'administrador' => 'Administrador',
            'administrador_sistema' => 'Administrador do Sistema',
            'gerencia_sistema' => 'Gerência do Sistema',
            'colaborador_sistema' => 'Colaborador FarmFort',
            'gestor_propriedade' => 'Gestor da Propriedade',
            'gestor_financeiro' => 'Gestor Financeiro',
            'gestao' => 'Gestão',
            'produtor' => 'Produtor',
            'colaborador' => 'Colaborador',
            'financeiro' => 'Financeiro',
            'visualizador' => 'Visualizador',
            'em_aberto' => 'Em Aberto',
            'aguardando_aprovacao' => 'Aguardando Aprovação',
            'aprovado' => 'Aprovado',
            'aprovada' => 'Aprovada',
            'aprovado_baixado' => 'Aprovado/Baixado',
            'baixado' => 'Baixado',
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'recebido' => 'Recebido',
            'transferido' => 'Transferido',
            'transferida' => 'Transferida',
            'vencido' => 'Vencido',
            'cancelado' => 'Cancelado',
            'cancelada' => 'Cancelada',
            'reprovada' => 'Reprovada',
            'rascunho' => 'Rascunho',
            'validada' => 'Validada',
            'concluida' => 'Concluída',
            'conferida' => 'Conferida',
            'rejeitada' => 'Rejeitada',
            'planejamento' => 'Planejamento',
            'em_andamento' => 'Em Andamento',
            'colhida' => 'Colhida',
            'encerrada' => 'Encerrada',
            'aberta' => 'Aberta',
            'respondida' => 'Respondida',
            'aguardando_encerramento' => 'Aguardando Encerramento',
            'encerrada' => 'Encerrada',
            'ativo' => 'Ativo',
            'ativa' => 'Ativa',
            'inativo' => 'Inativo',
            'inativa' => 'Inativa',
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            'receita' => 'Receita',
            'despesa' => 'Despesa',
            'conta_corrente' => 'Conta Corrente',
            'conta_poupanca' => 'Conta Poupança',
            'caixa' => 'Caixa',
            'uso_total_patrimonio' => 'Uso Total no Patrimônio',
            'uso_parcial_patrimonio' => 'Uso Parcial no Patrimônio',
        ];

        if (isset($labels[$value])) {
            return $labels[$value];
        }

        $text = str_replace('_', ' ', $value);

        return implode(' ', array_map(
            static fn (string $part): string => $part === '' ? '' : strtoupper($part[0]).substr($part, 1),
            explode(' ', strtolower($text))
        ));
    }
}
