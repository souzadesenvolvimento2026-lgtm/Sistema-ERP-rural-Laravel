<section class="panel">
    <div class="panel-head">
        <h2>Talhoes da safra</h2>
        <span class="badge">{{ $talhoesResumo->count() }} talhao(oes)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Talhao</th>
                    <th>Area</th>
                    <th>Cargas</th>
                    <th>Peso</th>
                    <th>Sacas</th>
                    <th>Produtividade</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($talhoesResumo as $talhao)
                    <tr>
                        <td><strong>{{ $talhao->nome }}</strong></td>
                        <td>{{ $talhao->area }}</td>
                        <td>{{ $talhao->cargas }}</td>
                        <td>{{ $talhao->peso }}</td>
                        <td>{{ $talhao->sacas }}</td>
                        <td>{{ $talhao->produtividade }}</td>
                        <td>
                            <span class="pill {{ $talhao->finalizado ? 'success' : 'warning' }}">
                                {{ $talhao->finalizado ? 'Finalizado' : 'Aberto' }}
                            </span>
                            @if ($talhao->finalizado)
                                <br><span class="muted">{{ $talhao->finalizado_em }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($talhao->finalizado)
                                <form method="POST" action="{{ route('colheita.talhoes.reabrir') }}">
                                    @csrf
                                    <input type="hidden" name="safra_id" value="{{ $filtros['safra_id'] }}">
                                    <input type="hidden" name="talhao_id" value="{{ $talhao->id }}">
                                    <button class="btn" type="submit">Reabrir</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('colheita.talhoes.finalizar') }}">
                                    @csrf
                                    <input type="hidden" name="safra_id" value="{{ $filtros['safra_id'] }}">
                                    <input type="hidden" name="talhao_id" value="{{ $talhao->id }}">
                                    <button class="btn primary" type="submit" @disabled($talhao->cargas <= 0)>Finalizar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Selecione uma safra para acompanhar os talhoes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
