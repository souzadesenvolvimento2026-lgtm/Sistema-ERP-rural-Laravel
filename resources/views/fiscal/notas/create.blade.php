@extends('layouts.farmfort', ['title' => 'FarmFort - Importar NF-e'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Importar NF-e</h1>
            <p class="subtitle">Importacao de XML com conferencia antes do lancamento fiscal.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'fiscal']) }}">Voltar</a>
    </div>

    @if ($preview)
        @php
            $invoice = $preview['invoice'];
            $items = collect($preview['items']);
        @endphp
        <section class="panel">
            <div class="panel-head">
                <h2>Conferencia da nota fiscal</h2>
                <span class="pill warning">Aguardando confirmacao</span>
            </div>
            <div class="grid cols-3">
                <div><span class="muted">Chave de acesso</span><strong>{{ $invoice['access_key'] }}</strong></div>
                <div><span class="muted">Numero</span><strong>{{ $invoice['invoice_number'] }}</strong></div>
                <div><span class="muted">Serie</span><strong>{{ $invoice['series'] ?: '-' }}</strong></div>
                <div><span class="muted">Emissao</span><strong>{{ \App\Support\FarmFormat::date($invoice['issue_date']) }}</strong></div>
                <div><span class="muted">Valor total</span><strong>{{ \App\Support\FarmFormat::money($invoice['total_value']) }}</strong></div>
                <div><span class="muted">Itens</span><strong>{{ $items->count() }}</strong></div>
                <div><span class="muted">Fornecedor</span><strong>{{ $invoice['issuer_name'] }}</strong><p class="muted">{{ $invoice['issuer_cnpj'] }}</p></div>
                <div><span class="muted">Destinatario</span><strong>{{ $invoice['recipient_name'] ?: '-' }}</strong><p class="muted">{{ $invoice['recipient_cnpj'] ?: '-' }}</p></div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Descricao</th>
                            <th>Un.</th>
                            <th>Qtd.</th>
                            <th>Valor unit.</th>
                            <th>Valor total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['product_code'] ?: '-' }}</td>
                                <td>{{ $item['description'] }}</td>
                                <td>{{ $item['unit'] ?: '-' }}</td>
                                <td>{{ \App\Support\FarmFormat::decimal($item['quantity']) }}</td>
                                <td>{{ \App\Support\FarmFormat::money($item['unit_value']) }}</td>
                                <td><strong>{{ \App\Support\FarmFormat::money($item['total_value']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <form method="post" action="{{ route('fiscal.notas.preview.cancel') }}">
                    @csrf
                    <button class="btn" type="submit">Cancelar</button>
                </form>
                <form method="post" action="{{ route('fiscal.notas.confirm') }}">
                    @csrf
                    <button class="btn primary" type="submit">Confirmar lancamento</button>
                </form>
            </div>
        </section>
    @endif

    <form method="post" action="{{ route('fiscal.notas.store') }}" enctype="multipart/form-data">
        @csrf
        <section class="panel">
            <div class="panel-head"><h2>Arquivo XML</h2></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="field full">
                        <label>XML da NF-e</label>
                        <input type="file" name="xml" accept=".xml,text/xml,application/xml" required>
                    </div>
                </div>
            </div>
        </section>

        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'fiscal']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Processar XML</button>
        </div>
    </form>
@endsection
