<label>
    Nome do grupo *
    <input name="nome" value="{{ old('nome', $grupo->nome ?? '') }}" required maxlength="120">
</label>

<label>
    Aprovador
    <select name="aprovador_usuario_id">
        <option value="">Ainda não definido</option>
        @foreach ($aprovadores as $aprovador)
            <option value="{{ $aprovador->id }}" @selected(($grupo->aprovador_usuario_id ?? null) == $aprovador->id)>{{ $aprovador->nome }} - {{ $aprovador->perfil }}</option>
        @endforeach
    </select>
</label>

<label>
    Ativo
    <select name="ativo">
        <option value="1" @selected(($grupo->ativo ?? 1) == 1)>Sim</option>
        <option value="0" @selected(($grupo->ativo ?? 1) == 0)>Não</option>
    </select>
</label>

<label class="span-2">
    Descrição
    <textarea name="descricao" rows="3">{{ old('descricao', $grupo->descricao ?? '') }}</textarea>
</label>

<label class="span-2">
    Fazendas Premium *
    <select name="propriedades[]" multiple required size="6">
        @foreach ($propriedades as $propriedade)
            <option value="{{ $propriedade->id }}" @disabled($propriedade->plano !== 'premium') @selected(in_array($propriedade->id, $grupo->propriedades_ids ?? []))>
                {{ $propriedade->nome }} - {{ ucfirst($propriedade->plano ?? 'basico') }}
            </option>
        @endforeach
    </select>
</label>
