<section id="polygonFormPanel" class="panel" style="display: none;">
    <div class="panel-head">
        <h2>Novo talhão pelo mapa</h2>
    </div>
    <form method="POST" action="{{ route('talhoes.mapa.store') }}" class="form-grid" data-polygon-form>
        @csrf
        <input type="hidden" name="coordenadas_json" id="polygonCoordinates">

        <label class="span-2">
            Destino do desenho
            <select id="polygonTalhaoId" name="talhao_id">
                <option value="">Criar novo talhão</option>
                @foreach ($talhoes as $talhao)
                    <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Nome do talhão *
            <input id="polygonName" name="nome" required maxlength="80" value="{{ old('nome') }}">
        </label>

        <label class="span-2">
            Descrição
            <textarea id="polygonDescription" name="descricao" rows="3">{{ old('descricao') }}</textarea>
        </label>

        <div class="form-actions span-2">
            <button class="btn primary" type="submit">Salvar talhão</button>
        </div>
    </form>
</section>

<div class="modal fade ff-talhao-edit-modal" id="mapTalhaoEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-talhao-edit-dialog">
        <form method="POST" class="modal-content ff-talhao-edit-content" data-map-details-form data-map-action-template="{{ url('/talhoes/__ID__/mapa/dados') }}">
            @csrf
            <div class="modal-header modal-header-green">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar talhão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" data-map-edit-talhao-id>

                <div class="row g-3">
                    <label class="col-12">
                        Nome *
                        <input id="mapEditTalhaoName" name="nome" required maxlength="80" autocomplete="off">
                    </label>

                    <label class="col-md-5">
                        Área (ha)
                        <input id="mapEditTalhaoArea" name="area" inputmode="decimal">
                    </label>

                    <label class="col-md-7">
                        Geometria
                        <input id="mapEditTalhaoGeometry" type="text" readonly data-map-edit-geometry>
                    </label>

                    <label class="col-12">
                        Descrição / Localização
                        <textarea id="mapEditTalhaoDescription" name="descricao" rows="3"></textarea>
                    </label>
                </div>

                <div class="ff-talhao-edit-tools">
                    <strong>Ferramentas do mapa</strong>
                    <div>
                        <button class="btn warning" type="button" data-map-modal-exclusion>
                            <i class="bi bi-scissors"></i>Adicionar área excluída
                        </button>
                        <button class="btn btn-info-outline" type="button" data-map-modal-pivo>
                            <i class="bi bi-record-circle"></i>Criar/editar pivô
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-map-modal-remove-pivo>
                            <i class="bi bi-x-circle"></i>Remover pivô
                        </button>
                    </div>
                    <small>As áreas excluídas são descontadas da área plantável do talhão.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary"><i class="bi bi-shield-check"></i>Salvar</button>
            </div>
        </form>
    </div>
</div>

<section class="panel" data-pivo-panel>
    <div class="panel-head">
        <h2>Ajustes do mapa</h2>
    </div>

    <div class="grid two">
        <div class="stack">
            <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/pivo') }}">
                @csrf
                <label>
                    Talhão com pivô
                    <select data-map-talhao-select required>
                        <option value="">Selecione...</option>
                        @foreach ($talhoes as $talhao)
                            <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Latitude <input name="pivo_lat" inputmode="decimal" required></label>
                <label>Longitude <input name="pivo_lng" inputmode="decimal" required></label>
                <label>Raio em metros <input name="pivo_raio_m" inputmode="decimal" required></label>
                <button class="btn primary" type="submit">Salvar pivô</button>
            </form>

            <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/pivo') }}" data-pivo-delete-form>
                @csrf
                @method('DELETE')
                <label>
                    Remover pivô
                    <select data-map-talhao-select required>
                        <option value="">Selecione...</option>
                        @foreach ($talhoes as $talhao)
                            <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn danger" type="submit">Remover pivô</button>
            </form>
        </div>

        <div class="stack">
            <form method="POST" action="{{ route('talhoes.mapa.pivo.create') }}">
                @csrf
                <label>Nome do novo pivô <input name="nome" maxlength="80" required></label>
                <label>Latitude <input name="pivo_lat" inputmode="decimal" required></label>
                <label>Longitude <input name="pivo_lng" inputmode="decimal" required></label>
                <label>Raio em metros <input name="pivo_raio_m" inputmode="decimal" required></label>
                <button class="btn primary" type="submit">Criar pivô/talhão</button>
            </form>
        </div>
    </div>

    <div class="grid two">
        <form method="POST" data-exclusion-form data-map-action-template="{{ url('/talhoes/__ID__/mapa/exclusoes') }}">
            @csrf
            <label>
                Talhão para exclusão
                <select data-map-talhao-select required>
                    <option value="">Selecione...</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-2">
                Coordenadas da área excluída em JSON
                <textarea name="exclusao_json" data-exclusion-json rows="5" required placeholder='[{"lat":-15.1,"lng":-47.1},{"lat":-15.1,"lng":-47.2},{"lat":-15.2,"lng":-47.2}]'></textarea>
            </label>
            <button class="btn primary" type="submit">Salvar área excluída</button>
        </form>

        <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/exclusoes') }}">
            @csrf
            @method('DELETE')
            <label>
                Limpar exclusões
                <select data-map-talhao-select required>
                    <option value="">Selecione...</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn danger" type="submit">Limpar exclusões</button>
        </form>
    </div>
</section>
