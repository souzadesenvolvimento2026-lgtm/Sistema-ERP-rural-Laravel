<?php

namespace App\Support;

class ModuleCatalog
{
    public static function menu(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard'],
            ['key' => 'financeiro', 'label' => 'Financeiro', 'route' => 'modules.show', 'params' => ['module' => 'financeiro']],
            ['key' => 'fiscal', 'label' => 'Fiscal', 'route' => 'modules.show', 'params' => ['module' => 'fiscal']],
            ['key' => 'compras', 'label' => 'Compras', 'route' => 'compras.pedidos.index'],
            ['key' => 'patrimonio', 'label' => 'Patrimônio', 'route' => 'modules.show', 'params' => ['module' => 'patrimonio']],
            ['key' => 'safras', 'label' => 'Safras', 'route' => 'modules.show', 'params' => ['module' => 'safras']],
            ['key' => 'orcamento', 'label' => 'Orçamento', 'route' => 'orcamento.index'],
            ['key' => 'talhoes', 'label' => 'Talhões', 'route' => 'modules.show', 'params' => ['module' => 'talhoes']],
            ['key' => 'colheita', 'label' => 'Colheita', 'route' => 'modules.show', 'params' => ['module' => 'colheita']],
            ['key' => 'estoque-produtos', 'label' => 'Estoque de produtos', 'route' => 'modules.show', 'params' => ['module' => 'estoque-produtos']],
            ['key' => 'estoque-producao', 'label' => 'Estoque de produção', 'route' => 'modules.show', 'params' => ['module' => 'estoque-producao']],
            ['key' => 'usuarios', 'label' => 'Usuários', 'route' => 'modules.show', 'params' => ['module' => 'usuarios']],
            ['key' => 'propriedades', 'label' => 'Propriedades', 'route' => 'modules.show', 'params' => ['module' => 'propriedades']],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'route' => 'auditoria.index'],
            ['key' => 'relatorios', 'label' => 'Indicadores e relatórios', 'route' => 'relatorios.index'],
        ];
    }

    public static function config(string $module): ?array
    {
        return self::configs()[$module] ?? null;
    }

    public static function configs(): array
    {
        return [
            'financeiro' => ['type' => 'financeiro', 'title' => 'Financeiro', 'subtitle' => 'Lançamentos, receitas, despesas e situação financeira migrados para Laravel.'],
            'fiscal' => ['type' => 'fiscal', 'title' => 'Fiscal', 'subtitle' => 'Entradas de NF, notas fiscais e documentos fiscais do banco atual.'],
            'patrimonio' => [
                'title' => 'Patrimônio',
                'subtitle' => 'Máquinas, implementos, veículos e imóveis cadastrados.',
                'table' => 'maquinas',
                'select' => ['id', 'nome', 'tipo', 'tipo_outro', 'marca_modelo', 'identificacao', 'ano', 'valor_aquisicao', 'ativo'],
                'columns' => ['nome' => 'Nome', 'tipo' => 'Tipo', 'marca_modelo' => 'Marca/Modelo', 'identificacao' => 'Identificação', 'ano' => 'Ano', 'valor_aquisicao' => 'Valor', 'ativo' => 'Ativo'],
                'order' => ['ativo'],
            ],
            'safras' => [
                'title' => 'Safras',
                'subtitle' => 'Safras, áreas, produção estimada e status.',
                'table' => 'safras',
                'select' => ['id', 'descricao', 'safra_referencia', 'data_inicio', 'data_fim', 'area_plantada', 'producao_estimada', 'producao_realizada', 'status'],
                'columns' => ['descricao' => 'Safra', 'safra_referencia' => 'Referência', 'data_inicio' => 'Início', 'data_fim' => 'Fim', 'area_plantada' => 'Área', 'producao_estimada' => 'Estimado', 'producao_realizada' => 'Realizado', 'status' => 'Status'],
                'order' => ['id'],
            ],
            'talhoes' => [
                'title' => 'Talhões',
                'subtitle' => 'Cadastro de áreas, pivôs e geometrias dos talhões.',
                'table' => 'talhoes',
                'select' => ['id', 'nome', 'area', 'area_bruta', 'area_excluida_ha', 'geometria_tipo', 'pivo_ativo', 'ativo'],
                'columns' => ['nome' => 'Talhão', 'area' => 'Área útil', 'area_bruta' => 'Área bruta', 'area_excluida_ha' => 'Exclusões', 'geometria_tipo' => 'Geometria', 'pivo_ativo' => 'Pivô', 'ativo' => 'Ativo'],
                'order' => ['ativo'],
            ],
            'colheita' => [
                'title' => 'Colheita',
                'subtitle' => 'Entradas de colheita por talhão, peso e produtividade.',
                'table' => 'colheita_talhoes',
                'select' => ['id', 'ticket_numero', 'motorista', 'veiculo_placa', 'destino_producao', 'data_colheita', 'peso_final_kg', 'sacas', 'produtividade_sc_ha'],
                'columns' => ['data_colheita' => 'Data', 'ticket_numero' => 'Ticket', 'motorista' => 'Motorista', 'veiculo_placa' => 'Veículo', 'destino_producao' => 'Destino', 'peso_final_kg' => 'Peso final', 'sacas' => 'Sacas', 'produtividade_sc_ha' => 'Produtividade'],
                'order' => ['data_colheita'],
            ],
            'estoque-produtos' => [
                'title' => 'Estoque de produtos',
                'subtitle' => 'Produtos, unidades, categorias fiscais e status de cadastro.',
                'table' => 'produtos',
                'joins' => [['categorias', 'categorias.id', 'produtos.categoria_id']],
                'select' => ['produtos.id', 'produtos.descricao_generica', 'produtos.codigo_interno', 'produtos.codigo_fornecedor', 'produtos.unidade_medida', 'categorias.nome as categoria', 'produtos.ncm', 'produtos.ativo'],
                'columns' => ['descricao_generica' => 'Produto', 'codigo_interno' => 'Código', 'codigo_fornecedor' => 'Código fornecedor', 'unidade_medida' => 'Unidade', 'categoria' => 'Categoria', 'ncm' => 'NCM', 'ativo' => 'Ativo'],
                'order' => ['produtos.ativo'],
            ],
            'estoque-producao' => [
                'title' => 'Estoque de produção',
                'subtitle' => 'Contratos, entregas e movimentações de produção.',
                'table' => 'contratos',
                'select' => ['id', 'tipo', 'numero', 'contraparte', 'produto', 'quantidade', 'unidade', 'valor_total', 'data_contrato', 'status'],
                'columns' => ['data_contrato' => 'Data', 'tipo' => 'Tipo', 'numero' => 'Número', 'contraparte' => 'Contraparte', 'produto' => 'Produto', 'quantidade' => 'Quantidade', 'unidade' => 'Un.', 'valor_total' => 'Valor', 'status' => 'Status'],
                'order' => ['data_contrato'],
            ],
            'usuarios' => [
                'title' => 'Usuários',
                'subtitle' => 'Logins, perfis e status de acesso.',
                'table' => 'usuarios',
                'property_scoped' => false,
                'select' => ['id', 'nome', 'email', 'perfil', 'ativo', 'ultimo_acesso'],
                'columns' => ['nome' => 'Nome', 'email' => 'E-mail', 'perfil' => 'Perfil', 'ativo' => 'Ativo', 'ultimo_acesso' => 'Último acesso'],
                'order' => ['ativo'],
            ],
            'propriedades' => [
                'title' => 'Propriedades',
                'subtitle' => 'Fazendas, responsáveis, localização e plano.',
                'table' => 'propriedades',
                'property_scoped' => false,
                'select' => ['id', 'nome', 'municipio', 'estado', 'area_total', 'responsavel', 'plano', 'ativo'],
                'columns' => ['nome' => 'Nome', 'municipio' => 'Município', 'estado' => 'UF', 'area_total' => 'Área', 'responsavel' => 'Responsável', 'plano' => 'Plano', 'ativo' => 'Ativo'],
                'order' => ['ativo'],
            ],
        ];
    }
}
