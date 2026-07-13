@php
    $isEdit = isset($propriedade) && $propriedade;
    $linkedUsers = collect($linkedUsers ?? []);
    $useOld = old('modal_id') === $modalId;
    $field = function (string $name, $fallback = '') use ($useOld) {
        return $useOld ? old($name, $fallback) : $fallback;
    };
    $planoAtual = $field('plano', $isEdit ? $propriedade->plano_key : 'basico');
    $pecuariaAtiva = (string) $field('pecuaria_ativa', $isEdit && $propriedade->pecuaria_ativa ? '1' : '0') === '1';
    $aprovadorAtual = (int) $field('aprovador_usuario_id', $isEdit ? ($propriedade->aprovador_usuario_id ?? 0) : 0);
@endphp

<div class="modal fade ff-property-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-property-dialog">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="modal-content ff-property-modal-content">
            @csrf
            @if (($method ?? 'POST') !== 'POST')
                @method($method)
            @endif
            <input type="hidden" name="modal_id" value="{{ $modalId }}">

            <div class="modal-header modal-header-green">
                <h5 class="modal-title">
                    <i class="bi {{ $isEdit ? 'bi-pencil-square' : 'bi-plus-square' }} me-2"></i>{{ $title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-property-form-grid">
                    <label class="ff-property-field span-6">
                        <span>Nome *</span>
                        <input name="nome" value="{{ $field('nome', $isEdit ? $propriedade->nome_raw : '') }}" required maxlength="150">
                    </label>

                    <label class="ff-property-field span-3">
                        <span>Município</span>
                        <input name="municipio" value="{{ $field('municipio', $isEdit ? $propriedade->municipio : '') }}" maxlength="100">
                    </label>

                    <label class="ff-property-field span-3">
                        <span>Estado</span>
                        <input name="estado" value="{{ $field('estado', $isEdit ? $propriedade->estado : 'GO') }}" maxlength="2">
                    </label>

                    <label class="ff-property-field span-2">
                        <span>Área total (ha) opcional</span>
                        <input name="area_total" inputmode="decimal" value="{{ $field('area_total', $isEdit ? $propriedade->area_total_input : '') }}" placeholder="Pode ficar em branco">
                    </label>

                    <label class="ff-property-field span-2">
                        <span>CNPJ / CPF</span>
                        <input name="cnpj_cpf" value="{{ $field('cnpj_cpf', $isEdit ? $propriedade->cnpj_cpf_raw : '') }}" maxlength="20">
                    </label>

                    <label class="ff-property-field span-2">
                        <span>Responsável</span>
                        <input name="responsavel" value="{{ $field('responsavel', $isEdit ? $propriedade->responsavel_raw : '') }}" maxlength="100">
                    </label>

                    <label class="ff-property-field span-6">
                        <span>Plano da propriedade</span>
                        <select name="plano" required>
                            @foreach ($planOptions as $value => $label)
                                <option value="{{ $value }}" @selected($planoAtual === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="ff-property-switch span-6">
                        <input type="hidden" name="pecuaria_ativa" value="0">
                        <label>
                            <input class="form-check-input" type="checkbox" name="pecuaria_ativa" value="1" @checked($pecuariaAtiva)>
                            <span>Ativar menu Pecuária nesta propriedade</span>
                        </label>
                        <small>Por padrão, o módulo Pecuária fica desativado para a propriedade.</small>
                    </div>

                    <label class="ff-property-field span-2">
                        <span>Latitude</span>
                        <input name="latitude" inputmode="decimal" value="{{ $field('latitude', $isEdit ? $propriedade->latitude : '') }}">
                    </label>

                    <label class="ff-property-field span-2">
                        <span>Longitude</span>
                        <input name="longitude" inputmode="decimal" value="{{ $field('longitude', $isEdit ? $propriedade->longitude : '') }}">
                    </label>

                    <label class="ff-property-field span-2">
                        <span>Região / praça da soja</span>
                        <input name="regiao_cotacao" value="{{ $field('regiao_cotacao', $isEdit ? $propriedade->regiao_cotacao_raw : '') }}" maxlength="160">
                    </label>

                    <div class="ff-property-note span-6">
                        A cotação da soja é automática. O FarmFort usa latitude/longitude ou município/UF para buscar a praça regional mais próxima a partir das 05:00, de hora em hora.
                    </div>

                    <label class="ff-property-field span-6">
                        <span>Aprovador de despesas</span>
                        <select name="aprovador_usuario_id">
                            <option value="">Administradores e gestores financeiros</option>
                            @foreach (($aprovadores ?? collect()) as $aprovador)
                                <option value="{{ $aprovador->id }}" @selected($aprovadorAtual === (int) $aprovador->id)>
                                    {{ $aprovador->nome }} — {{ \App\Support\FarmFormat::statusLabel($aprovador->perfil) }}
                                </option>
                            @endforeach
                        </select>
                        <small>Usuários com perfil de gestão, aprovação ou financeiro podem aprovar despesas desta fazenda.</small>
                    </label>

                    <label class="ff-property-field span-6">
                        <span>Importar KML, KMZ ou SHP da área / talhões (opcional)</span>
                        <input type="file" name="kml_area" accept=".kml,.kmz,.shp,.zip">
                        <small>Pode salvar a propriedade sem arquivo. Se houver arquivo geoespacial, o FarmFort cria/atualiza talhões e georreferência.</small>
                    </label>
                </div>

                <div class="ff-property-users-block">
                    <h6><i class="bi bi-people me-1"></i>Usuários da propriedade</h6>

                    @if ($isEdit && $linkedUsers->isNotEmpty())
                        <div class="ff-property-linked-users">
                            @foreach ($linkedUsers as $index => $usuario)
                                <div class="ff-property-user-row">
                                    <input type="hidden" name="usuarios_vinculados[{{ $index }}][id]" value="{{ $usuario->id }}">
                                    <label>
                                        <span>Nome</span>
                                        <input name="usuarios_vinculados[{{ $index }}][nome]" value="{{ $usuario->nome }}">
                                    </label>
                                    <label>
                                        <span>E-mail</span>
                                        <input type="email" name="usuarios_vinculados[{{ $index }}][email]" value="{{ $usuario->email }}">
                                    </label>
                                    <label>
                                        <span>Perfil</span>
                                        <select name="usuarios_vinculados[{{ $index }}][perfil]">
                                            @foreach ($perfisUsuario as $perfil => $label)
                                                <option value="{{ $perfil }}" @selected($usuario->perfil === $perfil)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>Senha opcional</span>
                                        <input type="password" name="usuarios_vinculados[{{ $index }}][senha]" autocomplete="new-password" placeholder="Alterar senha">
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="ff-property-empty-users">Nenhum usuário vinculado diretamente ainda.</p>
                    @endif

                    <div class="ff-property-user-columns">
                        <label class="ff-property-field">
                            <span>Vincular usuários existentes</span>
                            <select name="usuarios_existentes[]" multiple size="5">
                                @foreach (($usuariosDisponiveis ?? collect()) as $usuario)
                                    @php $jaVinculado = $linkedUsers->contains('id', (int) $usuario->id); @endphp
                                    <option value="{{ $usuario->id }}" @disabled($jaVinculado)>
                                        {{ $usuario->nome }} — {{ $usuario->email }} — {{ \App\Support\FarmFormat::statusLabel($usuario->perfil) }}{{ $jaVinculado ? ' (já vinculado)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small>Use Ctrl para selecionar mais de um usuário.</small>
                        </label>

                        <div class="ff-property-new-users">
                            <span class="ff-property-section-label">Adicionar até 3 novos usuários</span>
                            @for ($i = 0; $i < 3; $i++)
                                <div class="ff-property-new-user-row">
                                    <input name="novos_usuarios[{{ $i }}][nome]" placeholder="Nome">
                                    <input type="email" name="novos_usuarios[{{ $i }}][email]" placeholder="E-mail">
                                    <input type="password" name="novos_usuarios[{{ $i }}][senha]" autocomplete="new-password" placeholder="Senha">
                                    <select name="novos_usuarios[{{ $i }}][perfil]">
                                        @foreach ($perfisUsuario as $perfil => $label)
                                            <option value="{{ $perfil }}" @selected($perfil === 'visualizador')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endfor
                            <small>Se o e-mail já existir, o usuário será apenas vinculado à propriedade. Se for novo, a senha é obrigatória.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-check2-square"></i> {{ $isEdit ? 'Salvar alterações' : 'Salvar Fazenda' }}
                </button>
            </div>
        </form>
    </div>
</div>
