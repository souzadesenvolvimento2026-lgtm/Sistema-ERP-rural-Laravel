<section class="panel">
    @php
        $valorCarga = fn (string $campo, $padrao = '') => old($campo, isset($carga) ? ($carga->{$campo} ?? $padrao) : $padrao);
    @endphp
    <div class="panel-head"><h2>Dados da carga</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field">
                <label>Safra</label>
                <select name="safra_id">
                    <option value="">Sem safra</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}" @selected((string)$valorCarga('safra_id') === (string)$safra->id)>{{ $safra->descricao }} ({{ $safra->status }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Talhão</label>
                <select name="talhao_id" required>
                    <option value="">Selecione</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao->id }}" @selected((string)$valorCarga('talhao_id') === (string)$talhao->id)>{{ $talhao->nome }} - {{ number_format((float)$talhao->area, 2, ',', '.') }} ha</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Data</label>
                <input type="date" name="data_colheita" value="{{ $valorCarga('data_colheita', date('Y-m-d')) }}" required>
            </div>
            <div class="field">
                <label>Ticket</label>
                <input name="ticket_numero" value="{{ $valorCarga('ticket_numero') }}">
            </div>
            <div class="field">
                <label>Motorista</label>
                <input name="motorista" value="{{ $valorCarga('motorista') }}">
            </div>
            <div class="field">
                <label>Veículo</label>
                <input name="veiculo_placa" value="{{ $valorCarga('veiculo_placa') }}">
            </div>
            <div class="field">
                <label>Destino</label>
                <select name="destino_producao">
                    @foreach ($destinos as $value => $label)
                        <option value="{{ $value }}" @selected($valorCarga('destino_producao', 'sem_destino') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Local destino</label>
                <input name="local_destino" value="{{ $valorCarga('local_destino') }}">
            </div>
            <div class="field">
                <label>Peso bruto kg</label>
                <input name="peso_bruto_kg" inputmode="decimal" value="{{ $valorCarga('peso_bruto_kg') }}">
            </div>
            <div class="field">
                <label>Tara kg</label>
                <input name="tara_kg" inputmode="decimal" value="{{ $valorCarga('tara_kg') }}">
            </div>
            <div class="field">
                <label>Desconto kg</label>
                <input name="desconto_kg" inputmode="decimal" value="{{ $valorCarga('desconto_kg') }}">
            </div>
            <div class="field">
                <label>Peso final kg</label>
                <input name="peso_final_kg" inputmode="decimal" value="{{ $valorCarga('peso_final_kg') }}">
            </div>
            <div class="field">
                <label>Área colhida</label>
                <input name="area_colhida" inputmode="decimal" value="{{ $valorCarga('area_colhida') }}">
            </div>
            <div class="field">
                <label>Umidade %</label>
                <input name="umidade" inputmode="decimal" value="{{ $valorCarga('umidade') }}">
            </div>
            <div class="field">
                <label>Impureza %</label>
                <input name="impureza_pct" inputmode="decimal" value="{{ $valorCarga('impureza_pct') }}">
            </div>
            <div class="field full">
                <label>Observações</label>
                <textarea name="observacoes">{{ $valorCarga('observacoes') }}</textarea>
            </div>
        </div>
    </div>
</section>
