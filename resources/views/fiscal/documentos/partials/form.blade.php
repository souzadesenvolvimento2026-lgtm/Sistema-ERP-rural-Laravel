<section class="panel">
    <div class="panel-head"><h2>Novo documento</h2></div>
    <form method="POST" action="{{ route('fiscal.documentos.store') }}" class="form-grid" enctype="multipart/form-data">
        @csrf

        <label>
            Tipo *
            <select name="tipo" required>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Data
            <input type="date" name="data_documento" value="{{ old('data_documento', now()->format('Y-m-d')) }}">
        </label>

        <label>
            Status *
            <select name="status" required>
                <option value="pendente" @selected(old('status', 'pendente') === 'pendente')>Pendente</option>
                <option value="conferido" @selected(old('status') === 'conferido')>Conferido</option>
                <option value="arquivado" @selected(old('status') === 'arquivado')>Arquivado</option>
            </select>
        </label>

        <label class="span-2">
            Título *
            <input name="titulo" value="{{ old('titulo') }}" required maxlength="180">
        </label>

        <label>
            Número
            <input name="numero" value="{{ old('numero') }}" maxlength="80">
        </label>

        <label>
            Pessoa/empresa
            <input name="pessoa" value="{{ old('pessoa') }}" maxlength="150">
        </label>

        <label>
            Safra
            <select name="safra_id">
                <option value="">Nenhuma</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected((string)old('safra_id') === (string)$safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Valor
            <input name="valor" value="{{ old('valor') }}" inputmode="decimal" placeholder="0,00">
        </label>

        <label>
            Arquivo
            <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </label>

        <label class="span-2">
            Observações
            <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar documento</button>
        </div>
    </form>
</section>
