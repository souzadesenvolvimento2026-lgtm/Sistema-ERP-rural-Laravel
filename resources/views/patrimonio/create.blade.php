@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Patrimônio'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo patrimônio</h1>
            <p class="subtitle">Cadastro de máquinas, implementos e outros bens na base Laravel.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'patrimonio']) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('patrimonio.store') }}" enctype="multipart/form-data">
        @csrf

        @include('patrimonio.partials.dados-patrimonio')

        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'patrimonio']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar patrimônio</button>
        </div>
    </form>
@endsection
