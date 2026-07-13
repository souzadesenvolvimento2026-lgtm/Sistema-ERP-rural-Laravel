@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <section class="ff-comparison-hero" aria-labelledby="comparativoSafrasTitle">
        <div class="ff-comparison-hero-head">
            <div class="ff-comparison-title-group">
                <span class="ff-comparison-kicker">
                    <i class="bi bi-columns-gap" aria-hidden="true"></i>
                    Financeiro
                </span>
                <h1 id="comparativoSafrasTitle">{{ $title }}</h1>
                <p>{{ $subtitle }}</p>
            </div>

            <div class="ff-comparison-export-actions" aria-label="Exportar comparativo">
                <a class="ff-comparison-export-btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'csv'])) }}">
                    <i class="bi bi-filetype-csv" aria-hidden="true"></i>
                    <span>CSV</span>
                </a>
                <a class="ff-comparison-export-btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'excel'])) }}">
                    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                    <span>Excel</span>
                </a>
                <a class="ff-comparison-export-btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'pdf'])) }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span>PDF</span>
                </a>
            </div>
        </div>

        <h2 class="visually-hidden">Filtros</h2>
        @include('relatorios.comparativo-safras.partials.filtros')
    </section>

    @include('relatorios.comparativo-safras.partials.avisos')
    @include('relatorios.comparativo-safras.partials.tabela')
@endsection
