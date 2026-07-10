<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Importar arquivo geográfico</h2>
            <p class="subtitle">Cria ou atualiza talhões a partir de KML, KMZ ou SHP.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('talhoes.importar-geo') }}" class="form-grid" enctype="multipart/form-data">
        @csrf

        <label class="field wide">
            <span>Arquivo KML/KMZ/SHP</span>
            <input type="file" name="geo" accept=".kml,.kmz,.shp,.zip" required>
        </label>

        <label class="field">
            <span>Nome do talhão</span>
            <input name="nome_importacao" maxlength="80" placeholder="Opcional">
        </label>

        <div class="actions full">
            <button type="submit" class="btn primary">Importar talhões</button>
        </div>
    </form>
</section>
