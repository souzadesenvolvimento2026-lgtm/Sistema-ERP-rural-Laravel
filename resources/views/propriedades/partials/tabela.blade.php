<section class="panel">
    <div class="panel-head">
        <h2>Propriedades / Fazendas</h2>
        <span class="badge">{{ $rows->count() }} propriedade(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Município/UF</th>
                    <th>Área</th>
                    <th>Plano</th>
                    <th>Pecuária</th>
                    <th>Usuários</th>
                    <th>Responsável</th>
                    <th>Aprovador</th>
                    <th>Grupos</th>
                    <th>Cotação soja</th>
                    <th>Georreferência</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->nome }}</strong>
                            <br><span class="muted">{{ $row->cnpj_cpf }}</span>
                        </td>
                        <td>{{ $row->municipio_uf }}</td>
                        <td>{{ $row->area_total }}</td>
                        <td><span class="pill">{{ $row->plano }}</span></td>
                        <td><span class="pill {{ $row->pecuaria_ativa ? 'success' : '' }}">{{ $row->pecuaria }}</span></td>
                        <td>
                            <strong>{{ $row->usuarios_total }}/{{ $row->limite_usuarios }}</strong>
                            <br><span class="muted">limite do plano</span>
                        </td>
                        <td>{{ $row->responsavel }}</td>
                        <td>{{ $row->aprovador }}</td>
                        <td>{{ $row->grupos }}</td>
                        <td>
                            <strong>{{ $row->cotacao_soja }}/sc</strong>
                            <br><span class="muted">{{ $row->regiao_cotacao }}</span>
                            <br><span class="muted">Atualizada em {{ $row->cotacao_data }}</span>
                        </td>
                        <td>{{ $row->geo }}</td>
                        <td><span class="pill {{ $row->ativo ? 'success' : 'warning' }}">{{ $row->status }}</span></td>
                        <td>
                            <div class="actions">
                                <a class="btn small" href="{{ route('propriedades.edit', $row->id) }}">Editar</a>
                                <form method="post" action="{{ route('propriedades.toggle-status', $row->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $row->ativo ? 'Inativar' : 'Reativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="muted">Nenhuma propriedade encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
