<section class="panel">
    <div class="panel-head"><h2>Certificados vinculados</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Identificação</th>
                    <th>Ambiente</th>
                    <th>Titular</th>
                    <th>Emissor</th>
                    <th>Validade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($certificados as $certificado)
                    <tr>
                        <td>
                            <strong>{{ $certificado->nome_identificacao }}</strong>
                            @if ($certificado->principal)
                                <span class="status open">Principal</span>
                            @endif
                            <br>
                            <span class="muted">
                                {{ $certificado->tipo_certificado }}{{ $certificado->numero_serie ? ' · Série '.$certificado->numero_serie : '' }}
                            </span>
                        </td>
                        <td>{{ ucfirst($certificado->ambiente) }}</td>
                        <td>{{ $certificado->titular ?: '-' }}</td>
                        <td>{{ $certificado->emissor ?: '-' }}</td>
                        <td>
                            {{ $certificado->validade_fim ? \Illuminate\Support\Carbon::parse($certificado->validade_fim)->format('d/m/Y') : '-' }}
                            <br>
                            <span class="muted">{{ app(\App\Services\CertificadoDigitalService::class)->validadeTexto($certificado->validade_fim) }}</span>
                        </td>
                        <td>{{ ucfirst($certificado->status) }}</td>
                        <td>
                            <div class="inline-actions">
                                @if (!$certificado->principal && $certificado->status !== 'inativo')
                                    <form method="POST" action="{{ route('fiscal.certificados.principal', $certificado->id) }}">
                                        @csrf
                                        <button class="btn small" type="submit">Principal</button>
                                    </form>
                                @endif
                                @if ($certificado->status !== 'inativo')
                                    <form method="POST" action="{{ route('fiscal.certificados.desativar', $certificado->id) }}">
                                        @csrf
                                        <button class="btn small danger" type="submit">Desativar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Nenhum certificado vinculado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
