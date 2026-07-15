@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="ff-patrimony-page">
        @include('partials.stats', ['cards' => $cards])

        <div class="ff-patrimony-grid">
            <section class="panel ff-patrimony-list-card">
                <div class="panel-head">
                    <h2><i class="bi bi-truck"></i> Patrimônios</h2>
                    <button class="btn primary small" type="button" data-bs-toggle="modal" data-bs-target="#patrimonyModalCreate">
                        Novo patrimônio
                    </button>
                </div>

                <div class="ff-patrimony-list">
                    @forelse ($rows as $row)
                        <a
                            href="{{ route('patrimonio.index', ['patrimonio' => $row->id]) }}"
                            class="ff-patrimony-list-item {{ (int) ($selectedPatrimonioId ?? 0) === (int) $row->id ? 'is-selected' : '' }}"
                        >
                            <strong>{{ $row->nome }}</strong>
                            <span>{{ $row->tipo }} | {{ $row->marca_modelo }} | Preço: {{ $row->valor_aquisicao }}</span>
                        </a>
                    @empty
                        <div class="ff-patrimony-empty-list">
                            Nenhum patrimônio encontrado.
                        </div>
                    @endforelse
                </div>
            </section>

            <div class="ff-patrimony-detail-column">
                @if (! $selectedPatrimonio)
                    <section class="ff-patrimony-empty-detail">
                        Selecione um patrimônio para ver custos, abastecimentos e manutenções.
                    </section>
                @else
                    <section class="panel ff-patrimony-detail-card">
                        <div class="panel-head">
                            <h2><i class="bi bi-gear"></i> {{ $selectedPatrimonio->nome }}</h2>
                            <div class="inline-actions">
                                <button class="btn small" type="button" data-bs-toggle="modal" data-bs-target="#patrimonyModalEdit">
                                    Editar
                                </button>
                                <form method="post" action="{{ route('patrimonio.toggle-status', $selectedPatrimonio->id) }}">
                                    @csrf
                                    <button class="btn small danger" type="submit" onclick="return confirm('Apagar este patrimônio do cadastro ativo?')">Apagar</button>
                                </form>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="ff-patrimony-info-grid">
                                <div><span>Tipo</span><strong>{{ $selectedPatrimonio->tipo }}</strong></div>
                                <div><span>Preço do patrimônio</span><strong>{{ $selectedPatrimonio->valor_aquisicao }}</strong></div>
                                <div><span>Custo</span><strong class="danger">{{ $selectedPatrimonio->custo_total }}</strong></div>
                                <div><span>Combustível</span><strong>{{ $selectedPatrimonio->combustivel }}</strong></div>
                                @if ($selectedPatrimonio->horimetro !== '-')
                                    <div><span>Horímetro</span><strong>{{ $selectedPatrimonio->horimetro }}</strong></div>
                                @endif
                                @if ($selectedPatrimonio->odometro !== '-')
                                    <div><span>Odômetro</span><strong>{{ $selectedPatrimonio->odometro }}</strong></div>
                                @endif
                                <div><span>NF patrimônio</span><strong>{{ $selectedPatrimonio->nota_fiscal_numero }}</strong></div>
                                <div>
                                    <span>Arquivo NF</span>
                                    @if ($selectedPatrimonio->nota_fiscal_arquivo !== '')
                                        <a class="btn small outline-info" href="{{ asset('uploads/comprovantes/'.$selectedPatrimonio->nota_fiscal_arquivo) }}" target="_blank">Abrir arquivo</a>
                                    @else
                                        <strong>-</strong>
                                    @endif
                                </div>
                                @if ($selectedPatrimonio->nf_entrada_id)
                                    <div>
                                        <span>Fiscal</span>
                                        <a class="btn small outline-success" href="{{ route('fiscal.entrada-nf.index', ['search' => $selectedPatrimonio->nota_fiscal_numero]) }}">Abrir no fiscal</a>
                                    </div>
                                @endif
                                @if ($selectedPatrimonio->descricao !== '-')
                                    <div class="ff-patrimony-info-wide">
                                        <span>Descrição</span>
                                        <strong>{{ $selectedPatrimonio->descricao }}</strong>
                                    </div>
                                @endif
                            </div>

                            <form method="post" action="{{ route('patrimonio.update-value', $selectedPatrimonio->id) }}" class="ff-patrimony-price-form">
                                @csrf
                                <label>
                                    <span>Preço do patrimônio</span>
                                    <input class="form-control" name="valor_aquisicao" inputmode="decimal" value="{{ old('valor_aquisicao', number_format($selectedPatrimonio->valor_aquisicao_raw, 2, ',', '.')) }}">
                                </label>
                                <button class="btn primary" type="submit">Salvar preço</button>
                            </form>
                        </div>
                    </section>

                    <section class="panel ff-patrimony-launch-card">
                        <div class="panel-head">
                            <h2><i class="bi bi-clock-history"></i> Lançamentos</h2>
                            <button class="btn primary small" type="button" data-bs-toggle="modal" data-bs-target="#patrimonyLaunchModal">
                                Novo lançamento
                            </button>
                        </div>

                        <div class="table-wrap ff-patrimony-launch-table-wrap">
                            <table class="ff-patrimony-launch-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Talhão</th>
                                        <th>Qtd</th>
                                        <th>Total</th>
                                        <th>Marcador</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($lancamentos as $row)
                                        <tr>
                                            <td>{{ $row->data }}</td>
                                            <td>{{ $row->tipo }}</td>
                                            <td>
                                                <strong>{{ $row->descricao }}</strong>
                                                <br><span class="muted">{{ $row->fornecedor }} @if ($row->safra !== '-') | {{ $row->safra }} @endif</span>
                                            </td>
                                            <td>{{ $row->talhao }}</td>
                                            <td>{{ $row->quantidade }}</td>
                                            <td><strong>{{ $row->valor }}</strong></td>
                                            <td>
                                                {{ $row->horimetro }}
                                                @if ($row->odometro !== '-')
                                                    <br><span class="muted">{{ $row->odometro }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="muted">Nenhum lançamento encontrado para este patrimônio.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>

    @include('patrimonio.partials.modal-patrimonio', [
        'modalId' => 'patrimonyModalCreate',
        'modalTitle' => 'Patrimônio',
        'action' => route('patrimonio.store'),
        'method' => 'post',
        'patrimonio' => null,
        'submitLabel' => 'Salvar',
    ])

    @if ($selectedPatrimonio)
        @include('patrimonio.partials.modal-patrimonio', [
            'modalId' => 'patrimonyModalEdit',
            'modalTitle' => 'Patrimônio',
            'action' => route('patrimonio.update', $selectedPatrimonio->id),
            'method' => 'put',
            'patrimonio' => $selectedPatrimonioForm,
            'submitLabel' => 'Salvar',
        ])

        @include('patrimonio.partials.modal-lancamento', [
            'patrimonio' => $selectedPatrimonio,
            'safras' => $safras,
            'talhoes' => $talhoes,
            'tiposLancamento' => $tiposLancamento,
        ])
    @endif
@endsection
