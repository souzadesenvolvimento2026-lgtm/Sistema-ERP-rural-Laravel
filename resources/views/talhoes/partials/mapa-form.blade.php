<div class="modal fade ff-talhao-edit-modal ff-talhao-polygon-modal" id="mapPolygonFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-talhao-edit-dialog">
        <form method="POST" action="{{ route('talhoes.mapa.store', [], false) }}" class="modal-content ff-talhao-edit-content" data-polygon-form>
            @csrf
            <input type="hidden" name="coordenadas_json" id="polygonCoordinates">
            <input type="hidden" name="talhao_id" id="polygonTalhaoId">

            <div class="modal-header modal-header-green">
                <h5 class="modal-title"><i class="bi bi-bounding-box me-2"></i>Novo talhão pelo mapa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-polygon-modal-cancel aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <label class="col-12">
                        Nome *
                        <input id="polygonName" name="nome" required maxlength="80" autocomplete="off" value="{{ old('nome') }}" placeholder="Ex: Talhão 01">
                    </label>

                    <label class="col-md-5">
                        Área (ha)
                        <input id="polygonAreaEstimate" type="text" readonly data-polygon-area-label value="Calculada ao salvar">
                    </label>

                    <label class="col-md-7">
                        Geometria
                        <input type="text" readonly value="Polígono - desenho no mapa" data-polygon-geometry-label>
                    </label>

                    <label class="col-12">
                        Descrição / Localização
                        <textarea id="polygonDescription" name="descricao" rows="3" placeholder="Informações adicionais...">{{ old('descricao') }}</textarea>
                    </label>
                </div>
                <small class="d-block mt-3 text-muted">A área será calculada automaticamente pela geometria desenhada.</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal" data-polygon-modal-cancel>Cancelar</button>
                <button class="btn primary" type="submit"><i class="bi bi-shield-check"></i>Salvar talhão</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-talhao-edit-modal" id="mapTalhaoEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-talhao-edit-dialog">
        <form method="POST" class="modal-content ff-talhao-edit-content" data-map-details-form data-map-action-template="/talhoes/__ID__/mapa/dados">
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
                        <button class="btn btn-outline-secondary" type="button" data-map-modal-clear-exclusions>
                            <i class="bi bi-eraser"></i>Limpar exclusões
                        </button>
                    </div>
                    <small>As áreas excluídas são descontadas da área plantável do talhão.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal" data-map-modal-cancel>Cancelar</button>
                <button type="submit" class="btn primary"><i class="bi bi-shield-check"></i>Salvar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-talhao-edit-modal ff-map-pivo-modal" id="mapPivoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-talhao-pivo-dialog">
        <div class="modal-content ff-talhao-edit-content">
            <div class="modal-header modal-header-green">
                <h5 class="modal-title"><i class="bi bi-record-circle me-2"></i>Pivô do mapa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-map-modal-section">
                    <div class="ff-map-modal-section-title">
                        <strong>Criar ou editar pivô em talhão existente</strong>
                        <span>Selecione o talhão e informe o centro e o raio do pivô.</span>
                    </div>

                    <form method="POST" class="ff-map-modal-grid" data-map-action-template="/talhoes/__ID__/mapa/pivo" data-map-pivo-form>
                        @csrf
                        <label class="col-12">
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
                        <div class="ff-map-modal-actions">
                            <button class="btn primary" type="submit">Salvar pivô</button>
                        </div>
                    </form>
                </div>

                <div class="ff-map-modal-section">
                    <div class="ff-map-modal-section-title">
                        <strong>Remover pivô</strong>
                        <span>Remove somente o vínculo do pivô com o talhão selecionado.</span>
                    </div>

                    <form method="POST" class="ff-map-modal-inline" data-map-action-template="/talhoes/__ID__/mapa/pivo" data-pivo-delete-form>
                        @csrf
                        @method('DELETE')
                        <label>
                            Talhão
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

                <div class="ff-map-modal-section">
                    <div class="ff-map-modal-section-title">
                        <strong>Criar pivô como novo talhão</strong>
                        <span>Use quando o pivô ainda não pertence a nenhum talhão cadastrado.</span>
                    </div>

                    <form method="POST" action="{{ route('talhoes.mapa.pivo.create', [], false) }}" class="ff-map-modal-grid" data-map-pivo-create-form>
                        @csrf
                        <label class="col-12">Nome do novo pivô <input name="nome" maxlength="80" required></label>
                        <label>Latitude <input name="pivo_lat" inputmode="decimal" required></label>
                        <label>Longitude <input name="pivo_lng" inputmode="decimal" required></label>
                        <label>Raio em metros <input name="pivo_raio_m" inputmode="decimal" required></label>
                        <div class="ff-map-modal-actions">
                            <button class="btn primary" type="submit">Criar pivô/talhão</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div class="ff-map-transport-forms" hidden aria-hidden="true">
    <form method="POST" data-exclusion-form data-map-action-template="/talhoes/__ID__/mapa/exclusoes">
        @csrf
        <select data-map-talhao-select required>
            <option value="">Selecione...</option>
            @foreach ($talhoes as $talhao)
                <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
            @endforeach
        </select>
        <textarea name="exclusao_json" data-exclusion-json required></textarea>
    </form>

    <form method="POST" data-exclusion-clear-form data-map-action-template="/talhoes/__ID__/mapa/exclusoes">
        @csrf
        @method('DELETE')
        <select data-map-talhao-select required>
            <option value="">Selecione...</option>
            @foreach ($talhoes as $talhao)
                <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
            @endforeach
        </select>
    </form>
</div>
