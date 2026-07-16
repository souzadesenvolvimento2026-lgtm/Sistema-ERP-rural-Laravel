<section class="panel ff-safra-table-panel">
    <div class="panel-head ff-safra-table-head">
        <h2><i class="bi bi-calendar3"></i> Safras cadastradas</h2>
        <span class="badge">{{ $rows->count() }} safra(s)</span>
    </div>
    <div class="table-wrap ff-safra-table-wrap">
        <table class="ff-safra-table">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Cultura</th>
                    <th>Referência</th>
                    <th>Período</th>
                    <th>Área</th>
                    <th>Prod. estimada</th>
                    <th>Prod. realizada</th>
                    <th>Preço/sc</th>
                    <th>Talhões</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            @if ($row->observacoes !== '-')
                                <br><span class="muted">{{ $row->observacoes }}</span>
                            @endif
                        </td>
                        <td>{{ $row->cultura }}</td>
                        <td>{{ $row->referencia }}</td>
                        <td>{{ $row->inicio }} a {{ $row->fim }}</td>
                        <td>{{ $row->area }}</td>
                        <td>{{ $row->producao_estimada }}</td>
                        <td>{{ $row->producao_realizada }}</td>
                        <td>{{ $row->preco_estimado }}</td>
                        <td>{{ $row->talhoes_colhidos }}/{{ $row->talhoes_count }} colhidos</td>
                        <td><span class="pill {{ $row->status_tone }}">{{ $row->status }}</span></td>
                        <td class="ff-safra-actions-cell">
                            <div class="inline-actions ff-safra-row-actions">
                                <a class="btn small" href="{{ route('safras.edit', $row->id) }}" data-safra-edit>Editar</a>
                                @foreach ($row->actions as $action)
                                    <form method="POST" action="{{ route('safras.status', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $action['target_status'] }}">
                                        <button class="btn small" type="submit">{{ $action['label'] }}</button>
                                    </form>
                                @endforeach
                                @if ($row->can_delete)
                                    <form method="POST" action="{{ route('safras.destroy', $row->id) }}" onsubmit="return confirm('Excluir definitivamente esta safra? Esta ação não pode ser desfeita.');">
                                        @csrf
                                        @method('DELETE')
                                        <input class="compact-input" type="password" name="senha_exclusao" placeholder="Senha" required>
                                        <button class="btn small danger" type="submit">Excluir</button>
                                    </form>
                                @else
                                    <button class="btn small" type="button" disabled title="{{ $row->delete_block_reason }}">Excluir</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Nenhuma safra encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
