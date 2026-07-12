@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('fiscal.notas.index') }}">Voltar para notas</a>
            @if ($nota->tem_xml)
                <a class="btn" href="{{ route('fiscal.notas.xml', $nota->id) }}">XML</a>
            @endif
            @if ($nota->can_approve)
                <form method="post" action="{{ route('fiscal.notas.approve', $nota->id) }}">
                    @csrf
                    <button class="btn primary" type="submit">Aprovar nota</button>
                </form>
            @endif
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head">
            <h2>Dados da nota fiscal</h2>
            <span class="pill {{ $nota->status_tone }}">{{ $nota->status }}</span>
        </div>
        <div class="grid two">
            <div>
                <span class="label">Chave de acesso</span>
                <strong>{{ $nota->access_key }}</strong>
            </div>
            <div>
                <span class="label">Numero / serie</span>
                <strong>{{ $nota->number }}</strong>
            </div>
            <div>
                <span class="label">Emissao</span>
                <strong>{{ $nota->issue_date }}</strong>
            </div>
            <div>
                <span class="label">Valor total</span>
                <strong>{{ $nota->total }}</strong>
            </div>
            <div>
                <span class="label">Fornecedor</span>
                <strong>{{ $nota->issuer_name }}</strong>
                <p class="muted">{{ $nota->issuer_cnpj }}</p>
            </div>
            <div>
                <span class="label">Destinatario</span>
                <strong>{{ $nota->recipient_name }}</strong>
                <p class="muted">{{ $nota->recipient_cnpj }}</p>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Itens da nota fiscal</h2>
            <span class="badge">{{ $nota->item_count }} item(ns)</span>
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
                    @forelse ($itens as $item)
                        <tr>
                            <td>{{ $item->product_code }}</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->unit }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->unit_value }}</td>
                            <td><strong>{{ $item->total_value }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Nenhum item encontrado para esta nota fiscal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
