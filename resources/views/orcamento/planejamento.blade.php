@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('financeiro.index') }}">Financeiro</a>
            <a class="btn primary" href="{{ route('orcamento.create') }}">+ Nova projeção</a>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head"><h2>Filtro de safra</h2></div>
        <form method="GET" action="{{ route('financeiro.planejamento.index') }}" class="form-grid panel-body">
            <label>
                Safra
                <select name="safra_planejamento">
                    <option value="global" @selected($filtroSafra === 'global')>Visão global</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}" @selected((string)$safra->id === $filtroSafra)>{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions">
                <a class="btn" href="{{ route('financeiro.planejamento.index') }}">Limpar</a>
                <button class="btn primary" type="submit">Aplicar</button>
            </div>
        </form>
    </section>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head">
            <h2>Resultado por mês</h2>
            <span class="badge">{{ $mensal->count() }} mês(es)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Resultado da Safra</th>
                        <th>Projetado</th>
                        <th>% atingido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mensal as $row)
                        <tr>
                            <td>{{ $row->mes }}</td>
                            <td><strong>{{ $row->realizado_formatado }}</strong></td>
                            <td>{{ $row->projetado_formatado }}</td>
                            <td>{{ $row->percentual_formatado }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sem resultado ou projeção no filtro selecionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Resultado por categoria</h2>
            <span class="badge">{{ $categorias->count() }} categoria(s)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Resultado da Safra</th>
                        <th>Projetado</th>
                        <th>% atingido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categorias as $row)
                        <tr>
                            <td>{{ $row->nome }}</td>
                            <td><strong>{{ $row->realizado_formatado }}</strong></td>
                            <td>{{ $row->projetado_formatado }}</td>
                            <td>{{ $row->percentual_formatado }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Nenhuma categoria com resultado ou projeção.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Projeções financeiras</h2>
            <span class="badge">{{ $projecoes->count() }} registro(s)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Tipo</th>
                        <th>Safra</th>
                        <th>Categoria</th>
                        <th>Valor projetado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projecoes as $row)
                        <tr>
                            <td>{{ $row->mes_referencia }}</td>
                            <td>{{ $row->tipo_lancamento }}</td>
                            <td>{{ $row->safra ?? '-' }}</td>
                            <td>{{ $row->categoria ?? '-' }}</td>
                            <td><strong>{{ $row->valor_projetado_formatado }}</strong></td>
                            <td><a class="btn" href="{{ route('orcamento.edit', $row->id) }}">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Nenhuma projeção encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
