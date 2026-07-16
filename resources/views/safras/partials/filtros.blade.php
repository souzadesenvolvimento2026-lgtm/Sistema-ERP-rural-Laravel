@include('partials.filter-panel', [
    'action' => route('safras.index'),
    'clearUrl' => route('safras.index'),
    'fields' => [
        [
            'type' => 'select',
            'name' => 'status',
            'label' => 'Status',
            'value' => $filtros['status'] ?? 'ativas',
            'options' => $statusOptions,
            'columns' => 5,
        ],
        [
            'type' => 'search',
            'name' => 'search',
            'label' => 'Buscar',
            'value' => $filtros['search'] ?? '',
            'placeholder' => 'Descrição, cultura ou observação',
            'columns' => 8,
        ],
    ],
    'actionsColumns' => 3,
])
