@extends('layouts.farmfort', ['title' => 'FarmFort - Propriedades'])

@section('content')
    <div class="ff-property-page">
        <section class="panel ff-property-admin-card">
            <div class="panel-head ff-property-admin-head">
                <h2><i class="bi bi-houses me-2"></i>Propriedades / Fazendas</h2>

                <div class="ff-property-toolbar">
                    <a class="btn small {{ $filtros['status'] === 'ativas' ? 'primary' : '' }}" href="{{ route('propriedades.index', ['status' => 'ativas']) }}">
                        <i class="bi bi-check-circle"></i> Ativas
                    </a>
                    <a class="btn small ff-property-warning-btn {{ $filtros['status'] === 'inativas' ? 'is-active' : '' }}" href="{{ route('propriedades.index', ['status' => 'inativas']) }}">
                        <i class="bi bi-archive"></i> Inativas
                    </a>
                    <a class="btn small {{ $filtros['status'] === 'todas' ? 'primary' : '' }}" href="{{ route('propriedades.index', ['status' => 'todas']) }}">
                        <i class="bi bi-list-ul"></i> Todas
                    </a>

                    @if ($canCreateProperty)
                        <button class="btn primary small" type="button" data-bs-toggle="modal" data-bs-target="#propertyCreateModal">
                            <i class="bi bi-plus-lg"></i> Adicionar Fazenda
                        </button>
                    @else
                        <button class="btn primary small" type="button" disabled title="Adicionar fazenda exige plano Premium ou admin do sistema.">
                            <i class="bi bi-plus-lg"></i> Adicionar Fazenda
                        </button>
                    @endif
                </div>
            </div>

            <div class="table-wrap ff-property-table-wrap">
                <table class="datatable ff-property-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Município/UF</th>
                            <th>Área (ha)</th>
                            <th>Plano</th>
                            <th>Pecuária</th>
                            <th>Usuários</th>
                            <th>Responsável</th>
                            <th>Aprovador</th>
                            <th>Grupos</th>
                            <th>Cotação soja</th>
                            <th>Georreferência</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <div class="ff-property-name-cell">
                                        <strong>{{ $row->nome }}</strong>
                                        <span class="ff-property-badge {{ $row->ativo ? 'is-active' : 'is-inactive' }}">{{ $row->status }}</span>
                                    </div>
                                    <small>{{ $row->cnpj_cpf }}</small>
                                </td>
                                <td>{{ $row->municipio_uf }}</td>
                                <td>{{ $row->area_total }}</td>
                                <td><span class="ff-property-badge is-plan">{{ $row->plano }}</span></td>
                                <td>
                                    <span class="ff-property-badge {{ $row->pecuaria_ativa ? 'is-active' : 'is-muted' }}">
                                        {{ $row->pecuaria }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $row->usuarios_total }}/{{ $row->limite_usuarios }}</strong>
                                    <small>limite do plano</small>
                                </td>
                                <td>{{ $row->responsavel }}</td>
                                <td>{{ $row->aprovador }}</td>
                                <td>{{ $row->grupos }}</td>
                                <td class="ff-property-quote-cell">
                                    <strong>{{ $row->cotacao_soja }}/sc</strong>
                                    <small>{{ $row->regiao_cotacao }}</small>
                                    <small>Atualizada em {{ $row->cotacao_data }}</small>
                                    <small>Fonte: {{ $row->cotacao_fonte }}</small>
                                    <small>Última busca: {{ $row->cotacao_ultima_busca }}</small>
                                    <small>Automática a partir das 05:00, de hora em hora</small>
                                </td>
                                <td>
                                    @if ($row->has_geo)
                                        <a href="{{ $row->geo_url }}" target="_blank" rel="noopener">{{ $row->geo }}</a>
                                        <small>{{ $row->talhoes_total }} talhão(ões)</small>
                                    @else
                                        <span class="muted">Sem KML</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ff-property-row-actions">
                                        @if ($row->can_edit)
                                            <button class="ff-icon-action is-edit" type="button" data-bs-toggle="modal" data-bs-target="#propertyEditModal-{{ $row->id }}" title="Editar propriedade" aria-label="Editar {{ $row->nome }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endif

                                        @if ($row->can_toggle)
                                            <button class="ff-icon-action {{ $row->ativo ? 'is-danger' : 'is-transfer' }}" type="button" data-bs-toggle="modal" data-bs-target="#propertyStatusModal-{{ $row->id }}" title="{{ $row->ativo ? 'Desativar propriedade' : 'Reativar propriedade' }}" aria-label="{{ $row->ativo ? 'Desativar' : 'Reativar' }} {{ $row->nome }}">
                                                <i class="bi {{ $row->ativo ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center muted py-4">Nenhuma propriedade encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($canCreateProperty)
        @include('propriedades.partials.modal-form', [
            'modalId' => 'propertyCreateModal',
            'title' => 'Adicionar Fazenda',
            'action' => route('propriedades.store'),
            'method' => 'POST',
            'propriedade' => null,
            'linkedUsers' => collect(),
            'aprovadores' => $aprovadores,
            'usuariosDisponiveis' => $usuariosDisponiveis,
            'perfisUsuario' => $perfisUsuario,
            'planOptions' => $planOptions,
        ])
    @endif

    @foreach ($rows as $row)
        @if ($row->can_edit)
            @include('propriedades.partials.modal-form', [
                'modalId' => 'propertyEditModal-'.$row->id,
                'title' => 'Editar Fazenda',
                'action' => route('propriedades.update', $row->id),
                'method' => 'PUT',
                'propriedade' => $row,
                'linkedUsers' => $row->usuarios_vinculados,
                'aprovadores' => $aprovadores,
                'usuariosDisponiveis' => $usuariosDisponiveis,
                'perfisUsuario' => $perfisUsuario,
                'planOptions' => $planOptions,
            ])
        @endif

        @if ($row->can_toggle)
            @include('propriedades.partials.status-modal', ['row' => $row])
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modalId = @json(old('modal_id'));
            if (!modalId) return;

            const modalEl = document.getElementById(modalId);
            if (!modalEl || !window.bootstrap) return;

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    </script>
@endpush
