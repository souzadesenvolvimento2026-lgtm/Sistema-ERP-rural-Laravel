<section id="polygonFormPanel" class="panel" style="display: none;">
    <div class="panel-head">
        <h2>Novo talhão pelo mapa</h2>
    </div>
    <form method="POST" action="{{ route('talhoes.mapa.store') }}" class="form-grid">
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
            <textarea name="descricao" rows="3">{{ old('descricao') }}</textarea>
        </label>

        <div class="form-actions span-2">
            <button class="btn primary" type="submit">Salvar talhão</button>
        </div>
    </form>
</section>

<section class="panel" data-pivo-panel>
    <div class="panel-head">
        <h2>Ajustes do mapa</h2>
    </div>

    <div class="grid cols-3">
        <form method="POST" class="stack" data-map-action-template="{{ url('/talhoes/__ID__/mapa/dados') }}">
            @csrf
            <label>
                Talhao
                <select data-map-talhao-select required>
                    <option value="">Selecione...</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao['id'] }}" data-nome="{{ $talhao['nome'] }}" data-area="{{ $talhao['area'] }}" data-descricao="{{ $talhao['descricao'] ?? '' }}">{{ $talhao['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nome <input name="nome" maxlength="80" required></label>
            <label>Area ha <input name="area" inputmode="decimal"></label>
            <label>Descricao <textarea name="descricao" rows="3"></textarea></label>
            <button class="btn primary" type="submit">Salvar dados</button>
        </form>

        <div class="stack">
            <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/pivo') }}">
                @csrf
                <label>
                    Talhao com pivo
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
                <button class="btn primary" type="submit">Salvar pivo</button>
            </form>

            <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/pivo') }}">
                @csrf
                @method('DELETE')
                <label>
                    Remover pivo
                    <select data-map-talhao-select required>
                        <option value="">Selecione...</option>
                        @foreach ($talhoes as $talhao)
                            <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn danger" type="submit">Remover pivo</button>
            </form>
        </div>

        <div class="stack">
            <form method="POST" action="{{ route('talhoes.mapa.pivo.create') }}">
                @csrf
                <label>Nome do novo pivo <input name="nome" maxlength="80" required></label>
                <label>Latitude <input name="pivo_lat" inputmode="decimal" required></label>
                <label>Longitude <input name="pivo_lng" inputmode="decimal" required></label>
                <label>Raio em metros <input name="pivo_raio_m" inputmode="decimal" required></label>
                <button class="btn primary" type="submit">Criar pivo/talhao</button>
            </form>
        </div>
    </div>

    <div class="grid two">
        <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/exclusoes') }}">
            @csrf
            <label>
                Talhao para exclusao
                <select data-map-talhao-select required>
                    <option value="">Selecione...</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-2">
                Coordenadas da area excluida em JSON
                <textarea name="exclusao_json" rows="5" required placeholder='[{"lat":-15.1,"lng":-47.1},{"lat":-15.1,"lng":-47.2},{"lat":-15.2,"lng":-47.2}]'></textarea>
            </label>
            <button class="btn primary" type="submit">Salvar area excluida</button>
        </form>

        <form method="POST" data-map-action-template="{{ url('/talhoes/__ID__/mapa/exclusoes') }}">
            @csrf
            @method('DELETE')
            <label>
                Limpar exclusoes
                <select data-map-talhao-select required>
                    <option value="">Selecione...</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao['id'] }}">{{ $talhao['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn danger" type="submit">Limpar exclusoes</button>
        </form>
    </div>
</section>
