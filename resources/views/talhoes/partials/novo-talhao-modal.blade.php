<div class="modal fade ff-talhao-create-modal" id="talhaoCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-green">
                <h5 class="modal-title">Novo Talhão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <label class="ff-talhao-create-field">
                    <span>Propriedade *</span>
                    <select disabled>
                        <option>{{ $propertyName ?? 'Propriedade selecionada' }}</option>
                    </select>
                </label>

                <form id="talhaoImportModalForm" method="POST" action="{{ route('talhoes.importar-geo') }}" enctype="multipart/form-data" class="ff-talhao-create-section">
                    @csrf

                    <label class="ff-talhao-create-field">
                        <span>Arquivo do talhão</span>
                        <input type="file" name="geo" accept=".kml,.kmz,.shp,.zip" required>
                        <small>Com KML, KMZ ou SHP, o FarmFort calcula a área pela geometria e cria os talhões automaticamente.</small>
                    </label>

                    <label class="ff-talhao-create-field">
                        <span>Nome para o talhão importado</span>
                        <input name="nome_importacao" maxlength="80" value="{{ old('nome_importacao') }}" placeholder="Ex: Talhão 01">
                        <small>Use quando o arquivo não trouxer nome. Se houver vários talhões, o FarmFort numera automaticamente.</small>
                    </label>
                </form>

                <hr class="ff-talhao-create-divider">

                <form id="talhaoManualModalForm" method="POST" action="{{ route('talhoes.store') }}" class="ff-talhao-create-section">
                    @csrf
                    <input type="hidden" name="geometria_tipo" value="">
                    <input type="hidden" name="pivo_ativo" value="0">

                    <div class="ff-talhao-create-section-title">
                        <strong>Cadastro manual</strong>
                        <span>Use somente quando não houver arquivo do talhão.</span>
                    </div>

                    <div class="ff-talhao-create-grid">
                        <label class="ff-talhao-create-field ff-talhao-create-wide">
                            <span>Nome manual *</span>
                            <input name="nome" required maxlength="80" value="{{ old('nome') }}" placeholder="Ex: Talhão 01">
                        </label>

                        <label class="ff-talhao-create-field">
                            <span>Área manual (ha) *</span>
                            <input name="area" required inputmode="decimal" value="{{ old('area') }}" placeholder="0.00">
                        </label>

                        <label class="ff-talhao-create-field">
                            <span>Latitude</span>
                            <input name="latitude" inputmode="decimal" value="{{ old('latitude') }}">
                        </label>

                        <label class="ff-talhao-create-field">
                            <span>Longitude</span>
                            <input name="longitude" inputmode="decimal" value="{{ old('longitude') }}">
                        </label>

                        <label class="ff-talhao-create-field ff-talhao-create-full">
                            <span>Descrição / Localização</span>
                            <textarea name="descricao" rows="3" placeholder="Informações adicionais...">{{ old('descricao') }}</textarea>
                        </label>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="talhaoImportModalForm" class="btn btn-success-outline">
                    <i class="bi bi-upload"></i>Ler arquivo
                </button>
                <button type="submit" form="talhaoManualModalForm" class="btn primary">
                    <i class="bi bi-shield-check"></i>Salvar manual
                </button>
            </div>
        </div>
    </div>
</div>
