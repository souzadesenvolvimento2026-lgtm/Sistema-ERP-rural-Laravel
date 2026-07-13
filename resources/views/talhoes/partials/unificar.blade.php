<div class="modal fade ff-talhao-unify-modal" id="talhoesUnificacaoModal" tabindex="-1" aria-labelledby="talhoesUnificacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            @if ($unification['can_unify'])
                <form method="POST" action="{{ route('talhoes.unificar') }}">
                    @csrf
                    <input type="hidden" name="somar_area" value="1">

                    <div class="modal-header modal-header-green">
                        <h5 class="modal-title" id="talhoesUnificacaoModalLabel">
                            <i class="bi bi-intersect"></i> Unificar talhões
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-warning ff-talhao-unify-alert" role="alert">
                            Escolha o talhão que permanecerá ativo. Os lançamentos, chuvas, atividades, máquinas,
                            colheitas e vínculos com safras dos demais talhões serão movidos para ele. A área e a
                            geometria do destino serão compostas pela soma dos talhões selecionados.
                        </div>

                        <label class="ff-talhao-create-field">
                            <span>Talhão destino *</span>
                            <select name="talhao_destino_id" required>
                                <option value="">Selecione o talhão que ficará ativo</option>
                                @foreach ($talhoesAtivos as $talhao)
                                    <option value="{{ $talhao->id }}">{{ $talhao->nome }} — {{ $talhao->area }} ha</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="ff-talhao-create-field">
                            <span>Talhões que serão incorporados *</span>
                            <div class="ff-talhao-unify-list">
                                @foreach ($talhoesAtivos as $talhao)
                                    <label class="ff-talhao-unify-option">
                                        <input type="checkbox" name="talhoes_origem[]" value="{{ $talhao->id }}">
                                        <span>
                                            <strong>{{ $talhao->nome }}</strong>
                                            <small>{{ $talhao->area }} ha</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <small>O talhão destino não deve ser marcado como origem.</small>
                        </div>

                        <label class="check-row ff-talhao-unify-sum">
                            <input type="checkbox" checked disabled>
                            <span>Somar área e unir a geometria dos talhões incorporados ao talhão destino</span>
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-intersect"></i> Unificar talhões
                        </button>
                    </div>
                </form>
            @else
                <div class="modal-header modal-header-green">
                    <h5 class="modal-title" id="talhoesUnificacaoModalLabel">
                        <i class="bi bi-intersect"></i> Unificar talhões
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="muted mb-0">{{ $unification['block_reason'] }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Fechar</button>
                </div>
            @endif
        </div>
    </div>
</div>
