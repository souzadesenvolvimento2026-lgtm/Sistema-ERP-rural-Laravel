@if (($activeModule ?? '') === 'financeiro')
    <a class="btn" href="{{ route('financeiro.agenda.index') }}">Agenda</a>
    <a class="btn" href="{{ route('financeiro.analise-despesas.index') }}">Analise de Despesas</a>
    <a class="btn" href="{{ route('financeiro.categorias.index') }}">Categorias</a>
    <a class="btn" href="{{ route('financeiro.contas.index') }}">Contas bancarias</a>
    <a class="btn" href="{{ route('financeiro.despesas.index') }}">Despesas</a>
    <a class="btn" href="{{ route('financeiro.livro-caixa.index') }}">Livro Caixa</a>
    <a class="btn" href="{{ route('financeiro.movimentacoes.index') }}">Movimentacoes</a>
    <a class="btn" href="{{ route('financeiro.planejamento.index') }}">Planejamento</a>
    <a class="btn" href="{{ route('financeiro.receitas.index') }}">Receitas</a>
    <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.index') }}">Relatorio</a>
    <a class="btn primary" href="{{ route('financeiro.lancamentos.create') }}">+ Novo lancamento</a>
@endif
@if (($activeModule ?? '') === 'estoque-produtos')
    <a class="btn" href="{{ route('produtos.index') }}">Produtos</a>
    <a class="btn primary" href="{{ route('produtos.create') }}">+ Novo produto</a>
@endif
@if (($activeModule ?? '') === 'patrimonio')
    <a class="btn" href="{{ route('patrimonio.index') }}">Patrimonios</a>
    <a class="btn primary" href="{{ route('patrimonio.create') }}">+ Novo patrimônio</a>
@endif
@if (($activeModule ?? '') === 'safras')
    <a class="btn" href="{{ route('safras.index') }}">Safras</a>
    <a class="btn primary" href="{{ route('safras.create') }}">+ Nova safra</a>
@endif
@if (($activeModule ?? '') === 'talhoes')
    <a class="btn" href="{{ route('talhoes.index') }}">Talhoes</a>
    <a class="btn" href="{{ route('talhoes.atividades.index') }}">Atividades</a>
    <a class="btn" href="{{ route('talhoes.chuva.index') }}">Chuva</a>
    <a class="btn" href="{{ route('talhoes.mapa') }}">Mapa de talhões</a>
    <a class="btn primary" href="{{ route('talhoes.create') }}">+ Novo talhão</a>
@endif
@if (($activeModule ?? '') === 'colheita')
    <a class="btn" href="{{ route('colheita.index') }}">Colheitas</a>
    <a class="btn primary" href="{{ route('colheita.create') }}">+ Nova colheita</a>
@endif
@if (($activeModule ?? '') === 'usuarios')
    <a class="btn" href="{{ route('usuarios.index') }}">Usuarios</a>
    <a class="btn primary" href="{{ route('usuarios.create') }}">+ Novo usuário</a>
@endif
@if (($activeModule ?? '') === 'propriedades')
    <a class="btn" href="{{ route('propriedades.index') }}">Propriedades</a>
    <a class="btn" href="{{ route('propriedades.grupos.index') }}">Grupos de fazendas</a>
    <a class="btn primary" href="{{ route('propriedades.create') }}">+ Nova propriedade</a>
@endif
@if (($activeModule ?? '') === 'estoque-producao')
    <a class="btn primary" href="{{ route('estoque-producao.contratos.index') }}">Contratos e entregas</a>
@endif
@if (($activeModule ?? '') === 'fiscal')
    <a class="btn" href="{{ route('fiscal.certificados.index') }}">Certificados</a>
    <a class="btn" href="{{ route('fiscal.consolidado.index') }}">Consolidado</a>
    <a class="btn" href="{{ route('fiscal.documentos.index') }}">Documentos</a>
    <a class="btn" href="{{ route('fiscal.notas.index') }}">Notas Fiscais</a>
    <a class="btn" href="{{ route('fiscal.produtores.index') }}">Produtores</a>
    <a class="btn" href="{{ route('fiscal.notas.create') }}">Importar NF-e</a>
    <a class="btn primary" href="{{ route('fiscal.entrada-nf.create') }}">+ Entrada de NF</a>
@endif
