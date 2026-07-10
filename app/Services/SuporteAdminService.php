<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Support\Facades\DB;

class SuporteAdminService
{
    public function pagina(): array
    {
        $conversas = DB::table('suporte_conversas as c')
            ->leftJoin('usuarios as cliente', 'cliente.id', '=', 'c.usuario_id')
            ->leftJoin('usuarios as atendente', 'atendente.id', '=', 'c.atendente_usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'c.propriedade_id')
            ->orderByDesc('c.atualizada_em')
            ->limit(80)
            ->get([
                'c.id',
                'c.assunto',
                'c.status',
                'c.nivel_atendimento',
                'c.atualizada_em',
                'cliente.nome as cliente_nome',
                'atendente.nome as atendente_nome',
                'p.nome as propriedade_nome',
            ]);

        $atendentes = DB::table('suporte_mensagens as sm')
            ->leftJoin('usuarios as u', 'u.id', '=', 'sm.autor_usuario_id')
            ->where('sm.autor_tipo', 'admin')
            ->where('sm.criada_em', '>=', now()->subDays(30))
            ->groupBy('u.id', 'u.nome', 'u.email', 'u.perfil')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(20)
            ->get([
                'u.id',
                DB::raw("COALESCE(u.nome, 'Atendente removido') as nome"),
                DB::raw("COALESCE(u.email, '-') as email"),
                DB::raw("COALESCE(u.perfil, '') as perfil"),
                DB::raw('COUNT(*) as respostas'),
                DB::raw('COUNT(DISTINCT sm.conversa_id) as conversas'),
                DB::raw('MAX(sm.criada_em) as ultimo_atendimento'),
            ]);

        $ultimas = DB::table('suporte_mensagens as sm')
            ->leftJoin('usuarios as u', 'u.id', '=', 'sm.autor_usuario_id')
            ->leftJoin('suporte_conversas as c', 'c.id', '=', 'sm.conversa_id')
            ->leftJoin('usuarios as cliente', 'cliente.id', '=', 'c.usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'c.propriedade_id')
            ->where('sm.autor_tipo', 'admin')
            ->orderByDesc('sm.criada_em')
            ->orderByDesc('sm.id')
            ->limit(50)
            ->get([
                'sm.id',
                'sm.criada_em',
                'sm.mensagem',
                'u.nome as atendente_nome',
                'u.email as atendente_email',
                'c.assunto',
                'cliente.nome as cliente_nome',
                'p.nome as propriedade_nome',
            ])
            ->map(function ($row) {
                $row->criada_em_fmt = FarmFormat::date($row->criada_em);
                $row->resumo = $this->resumo((string)$row->mensagem, 140);
                return $row;
            });

        return [
            'activeModule' => 'suporte',
            'title' => 'Chat de Duvidas',
            'subtitle' => 'Acompanhamento dos atendimentos enviados pelos clientes dentro das fazendas.',
            'cards' => [
                ['label' => 'Abertas', 'value' => (string)$conversas->where('status', 'aberta')->count(), 'tone' => 'warning'],
                ['label' => 'Respondidas', 'value' => (string)$conversas->where('status', 'respondida')->count(), 'tone' => 'success'],
                ['label' => 'Aguardando encerramento', 'value' => (string)$conversas->where('status', 'aguardando_encerramento')->count(), 'tone' => 'warning'],
                ['label' => 'Encerradas', 'value' => (string)$conversas->where('status', 'encerrada')->count(), 'tone' => 'success'],
            ],
            'conversas' => $conversas,
            'atendentes' => $atendentes,
            'ultimas' => $ultimas,
        ];
    }

    private function resumo(string $texto, int $limite): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?: '');
        if (strlen($texto) <= $limite) {
            return $texto;
        }

        return substr($texto, 0, max(0, $limite - 3)).'...';
    }
}
