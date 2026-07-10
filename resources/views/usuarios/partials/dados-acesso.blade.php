@php
    $editando = isset($usuario) && $usuario;
@endphp

<section class="panel">
    <div class="panel-head"><h2>Dados de acesso</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Nome</label>
                <input name="nome" value="{{ old('nome', $usuario->nome ?? '') }}" required>
            </div>
            <div class="field">
                <label>Perfil</label>
                <select name="perfil" required>
                    @foreach ($perfis as $value => $label)
                        <option value="{{ $value }}" @selected((string)old('perfil', $usuario->perfil ?? '') === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field wide">
                <label>E-mail</label>
                <input type="email" name="email" value="{{ old('email', $usuario->email ?? '') }}" required>
            </div>
            <div class="field">
                <label>{{ $editando ? 'Nova senha' : 'Senha' }}</label>
                <input type="password" name="senha" @required(!$editando)>
            </div>
            <div class="field">
                <label>Confirmar senha</label>
                <input type="password" name="senha_confirmation" @required(!$editando)>
            </div>
        </div>
    </div>
</section>
