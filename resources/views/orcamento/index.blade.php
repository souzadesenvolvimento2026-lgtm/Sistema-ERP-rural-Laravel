@extends('layouts.farmfort', ['title' => 'FarmFort - Orçamento'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Orçamento</h1>
            <p class="subtitle">Planejamento financeiro da safra com projeções de receitas e despesas.</p>
        </div>
        <a class="btn primary" href="{{ route('orcamento.create') }}">+ Nova projeção</a>
    </div>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head"><h2>Base da safra</h2></div>
        <form method="post" action="{{ route('orcamento.base-safra') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra</label>
                    <select name="safra_id" required>
                        <option value="">Selecione</option>
                        @foreach ($safras as $safra)
                            <option
                                value="{{ $safra->id }}"
                                data-area="{{ $safra->area_plantada !== null ? number_format((float)$safra->area_plantada, 2, ',', '.') : '' }}"
                                data-producao="{{ $safra->producao_estimada !== null ? number_format((float)$safra->producao_estimada, 2, ',', '.') : '' }}"
                                data-preco="{{ $safra->preco_estimado !== null ? number_format((float)$safra->preco_estimado, 2, ',', '.') : '' }}"
                            >{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Area plantada ha</label>
                    <input name="area_plantada" inputmode="decimal" data-base-area>
                </div>
                <div class="field">
                    <label>Producao estimada sc/ha</label>
                    <input name="producao_estimada" inputmode="decimal" data-base-producao>
                </div>
                <div class="field">
                    <label>Preco estimado sc</label>
                    <input name="preco_estimado" inputmode="decimal" data-base-preco>
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Salvar base da safra</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Atalhos do planejamento</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field">
                    <form method="post" action="{{ route('orcamento.culturas.store') }}">
                        @csrf
                        <label>Nova cultura</label>
                        <input name="nome" maxlength="120" required>
                        <div class="actions" style="justify-content:flex-start;margin-top:12px">
                            <button class="btn primary" type="submit">Cadastrar cultura</button>
                        </div>
                    </form>
                </div>
                <div class="field">
                    <form method="post" action="{{ route('orcamento.categorias.store') }}">
                        @csrf
                        <label>Nova categoria</label>
                        <input name="nome" maxlength="120" required>
                        <div class="actions" style="justify-content:flex-start;margin-top:12px">
                            <button class="btn primary" type="submit">Cadastrar categoria</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Ano agricola</h2></div>
        <form method="post" action="{{ route('orcamento.anos-agricolas.store') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Ano inicial</label>
                    <input type="number" name="ano_inicio" min="2000" max="2100" value="{{ date('Y') }}" required>
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Salvar ano agricola</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Safra retroativa</h2></div>
        <form method="post" action="{{ route('orcamento.safras-retroativas.store') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Descricao</label>
                    <input name="descricao" maxlength="120" required>
                </div>
                <div class="field">
                    <label>Cultura</label>
                    <select name="cultura_id">
                        <option value="">Sem cultura</option>
                        @foreach ($culturas as $cultura)
                            <option value="{{ $cultura->id }}">{{ $cultura->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Data inicial</label>
                    <input type="date" name="data_inicio" required>
                </div>
                <div class="field">
                    <label>Data final</label>
                    <input type="date" name="data_fim">
                </div>
                <div class="field">
                    <label>Area plantada ha</label>
                    <input name="area_plantada" inputmode="decimal">
                </div>
                <div class="field">
                    <label>Producao estimada sc/ha</label>
                    <input name="producao_estimada" inputmode="decimal">
                </div>
                <div class="field">
                    <label>Producao realizada sc</label>
                    <input name="producao_realizada" inputmode="decimal">
                </div>
                <div class="field">
                    <label>Preco estimado sc</label>
                    <input name="preco_estimado" inputmode="decimal">
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Cadastrar safra retroativa</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Despesa planejada</h2></div>
        <form method="post" action="{{ route('orcamento.despesas-planejadas.store') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra</label>
                    <select name="safra_id" required>
                        <option value="">Selecione</option>
                        @foreach ($safras as $safra)
                            <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cultura</label>
                    <select name="cultura_id">
                        <option value="">Nao informada</option>
                        @foreach ($culturas as $cultura)
                            <option value="{{ $cultura->id }}">{{ $cultura->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Mes</label>
                    <input type="month" name="mes_referencia" value="{{ date('Y-m') }}" required>
                </div>
                <div class="field">
                    <label>Categoria</label>
                    <select name="categoria_id" required>
                        <option value="">Selecione</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Valor</label>
                    <input name="valor_projetado" inputmode="decimal" required>
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Adicionar despesa planejada</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Insumo planejado</h2></div>
        <form method="post" action="{{ route('orcamento.insumos-planejados.store') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra</label>
                    <select name="safra_id" required>
                        <option value="">Selecione</option>
                        @foreach ($safras as $safra)
                            <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cultura</label>
                    <select name="cultura_id">
                        <option value="">Usar cultura da safra</option>
                        @foreach ($culturas as $cultura)
                            <option value="{{ $cultura->id }}">{{ $cultura->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Data de uso</label>
                    <input type="date" name="data_utilizacao" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label>Categoria</label>
                    <select name="categoria_id" required>
                        <option value="">Selecione</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Quantidade</label>
                    <input name="quantidade" inputmode="decimal" required>
                </div>
                <div class="field">
                    <label>Unidade</label>
                    <input name="unidade" maxlength="20" placeholder="Litro, kg, sc">
                </div>
                <div class="field">
                    <label>Valor unitario</label>
                    <input name="valor_unitario" inputmode="decimal" required>
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Adicionar insumo planejado</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Atividade planejada</h2></div>
        <form method="post" action="{{ route('orcamento.atividades-planejadas.store') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra</label>
                    <select name="safra_id" required>
                        <option value="">Selecione</option>
                        @foreach ($safras as $safra)
                            <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Talhao</label>
                    <select name="talhao_id">
                        <option value="">Fazenda geral</option>
                        @foreach ($talhoes as $talhao)
                            <option value="{{ $talhao->id }}">{{ $talhao->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tipo</label>
                    <select name="tipo" required>
                        @foreach ($tiposAtividade as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Data inicial</label>
                    <input type="date" name="data_inicio" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label>Data final</label>
                    <input type="date" name="data_fim">
                </div>
                <div class="field">
                    <label>Area executada ha</label>
                    <input name="area_executada" inputmode="decimal">
                </div>
                <div class="field wide">
                    <label>Descricao</label>
                    <input name="descricao" maxlength="180">
                </div>
                <div class="field">
                    <label>Responsavel</label>
                    <input name="responsavel" maxlength="120">
                </div>
                <div class="field">
                    <label>Servico</label>
                    <input name="servico" maxlength="180">
                </div>
                <div class="field">
                    <label>Produto</label>
                    <input name="produto" maxlength="120">
                </div>
                <div class="field">
                    <label>Custo estimado</label>
                    <input name="custo_estimado" inputmode="decimal">
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Salvar atividade planejada</button>
            </div>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Safra</th>
                        <th>Descricao</th>
                        <th>Tipo</th>
                        <th>Talhao</th>
                        <th>Area</th>
                        <th>Custo</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($atividadesPlanejadas as $atividade)
                        <tr>
                            <td>{{ \App\Support\FarmFormat::date($atividade->data_inicio) }}</td>
                            <td>{{ $atividade->safra ?? '-' }}</td>
                            <td>{{ $atividade->descricao ?? '-' }}</td>
                            <td>{{ $tiposAtividade[$atividade->tipo] ?? $atividade->tipo }}</td>
                            <td>{{ $atividade->talhao ?? 'Fazenda geral' }}</td>
                            <td>{{ $atividade->area_executada !== null ? \App\Support\FarmFormat::decimal($atividade->area_executada) : '-' }}</td>
                            <td>{{ \App\Support\FarmFormat::money($atividade->custo_estimado ?? 0) }}</td>
                            <td>
                                <form method="post" action="{{ route('orcamento.atividades-planejadas.destroy', $atividade->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">Nenhuma atividade planejada encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Despesa recorrente</h2></div>
        <form method="post" action="{{ route('orcamento.recorrente') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra</label>
                    <select name="safra_id">
                        <option value="">Sem safra</option>
                        @foreach ($safras as $safra)
                            <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tipo safra</label>
                    <select name="tipo_safra">
                        <option value="principal">Principal</option>
                        <option value="safrinha">Safrinha</option>
                    </select>
                </div>
                <div class="field">
                    <label>Ano safra</label>
                    <input name="ano_safra" value="{{ date('Y') }}/{{ date('Y') + 1 }}" required>
                </div>
                <div class="field">
                    <label>Categoria</label>
                    <select name="categoria_id" required>
                        <option value="">Selecione</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Mes inicial</label>
                    <input type="month" name="mes_inicial" value="{{ date('Y-m') }}" required>
                </div>
                <div class="field">
                    <label>Mes final</label>
                    <input type="month" name="mes_final" value="{{ date('Y-m') }}" required>
                </div>
                <div class="field">
                    <label>Valor mensal</label>
                    <input name="valor_projetado" inputmode="decimal" required>
                </div>
                <div class="field wide">
                    <label>Observacoes</label>
                    <input name="observacoes">
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Gerar recorrencia</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Copiar safra anterior</h2></div>
        <form method="post" action="{{ route('orcamento.copiar-safra-anterior') }}" class="panel-body">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Safra de destino</label>
                    <select name="safra_id" required>
                        <option value="">Selecione</option>
                        @foreach ($safras as $safra)
                            <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field wide">
                    <label>Como funciona</label>
                    <input value="Copia as despesas da safra anterior e substitui o planejamento da safra escolhida." readonly>
                </div>
            </div>
            <div class="actions">
                <button class="btn primary" type="submit">Copiar planejamento</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Projeções financeiras</h2></div>
        @if ($rows->isNotEmpty())
            <form method="post" action="{{ route('orcamento.projecoes.lote') }}">
                @csrf
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Categoria</th>
                                <th>Qtd.</th>
                                <th>Un.</th>
                                <th>Valor unit.</th>
                                <th>Valor total</th>
                                <th>Observacoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <input type="hidden" name="projecao_id[]" value="{{ $row->id }}">
                                        <input type="month" name="mes_referencia[]" value="{{ substr((string)$row->mes_referencia, 0, 7) }}" required>
                                    </td>
                                    <td>
                                        <select name="categoria_id[]" required>
                                            @foreach ($categorias as $categoria)
                                                <option value="{{ $categoria->id }}" @selected((int)$row->categoria_id === (int)$categoria->id)>{{ $categoria->nome }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input name="quantidade[]" value="{{ \App\Support\FarmFormat::decimal($row->quantidade) }}" inputmode="decimal"></td>
                                    <td><input name="unidade[]" value="{{ $row->unidade }}" maxlength="20"></td>
                                    <td><input name="valor_unitario[]" value="{{ number_format((float)$row->valor_unitario, 2, ',', '.') }}" inputmode="decimal"></td>
                                    <td><input name="valor_projetado[]" value="{{ number_format((float)$row->valor_projetado_raw, 2, ',', '.') }}" inputmode="decimal" required></td>
                                    <td><input name="observacoes[]" value="{{ $row->observacoes }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="actions">
                    <button class="btn primary" type="submit">Salvar ajustes rapidos</button>
                </div>
            </form>
        @endif
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Tipo</th>
                        <th>Safra</th>
                        <th>Categoria</th>
                        <th>Qtd.</th>
                        <th>Un.</th>
                        <th>Valor unit.</th>
                        <th>Valor projetado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->mes_referencia }}</td>
                            <td>{{ $row->tipo_lancamento }}</td>
                            <td>{{ $row->safra ?? '-' }}</td>
                            <td>{{ $row->categoria ?? '-' }}</td>
                            <td>{{ \App\Support\FarmFormat::decimal($row->quantidade) }}</td>
                            <td>{{ $row->unidade ?? '-' }}</td>
                            <td>{{ $row->valor_unitario_formatado }}</td>
                            <td>{{ $row->valor_projetado_formatado }}</td>
                            <td>
                                <div class="actions" style="justify-content:flex-start">
                                    <a class="btn" href="{{ route('orcamento.edit', $row->id) }}">Editar</a>
                                    <form method="post" action="{{ route('orcamento.destroy', $row->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">Nenhuma projeção encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const safraSelect = document.querySelector('form[action="{{ route('orcamento.base-safra') }}"] select[name="safra_id"]');
            if (!safraSelect) return;

            const fields = {
                area: document.querySelector('[data-base-area]'),
                producao: document.querySelector('[data-base-producao]'),
                preco: document.querySelector('[data-base-preco]'),
            };

            safraSelect.addEventListener('change', () => {
                const option = safraSelect.selectedOptions[0];
                if (!option) return;

                fields.area.value = option.dataset.area || '';
                fields.producao.value = option.dataset.producao || '';
                fields.preco.value = option.dataset.preco || '';
            });
        });
    </script>
@endpush
