@php
    $filterTitle = $title ?? 'Filtros';
    $filterMethod = strtoupper($method ?? 'GET');
    $filterAction = $action ?? url()->current();
    $filterFields = $fields ?? [];
    $filterHidden = $hidden ?? [];
    $filterClearUrl = $clearUrl ?? $filterAction;
    $filterSubmitLabel = $submitLabel ?? 'Filtrar';
    $filterClearLabel = $clearLabel ?? 'Limpar';
    $filterClass = trim('panel ff-filter-panel '.($class ?? ''));
    $filterActionsColumns = max(1, min(16, (int) ($actionsColumns ?? 3)));

    $renderAttributes = static function (array $attributes): string {
        return collect($attributes)
            ->filter(static fn ($value) => $value !== false && $value !== null)
            ->map(static fn ($value, $key) => $value === true ? e($key) : e($key).'="'.e($value).'"')
            ->implode(' ');
    };
@endphp

<section class="{{ $filterClass }}">
    <div class="panel-head">
        <h2>{{ $filterTitle }}</h2>
    </div>

    <form method="{{ $filterMethod }}" action="{{ $filterAction }}" class="ff-filter-form">
        @foreach ($filterHidden as $name => $value)
            @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <div class="ff-filter-grid">
            @foreach ($filterFields as $field)
                @php
                    $fieldType = $field['type'] ?? 'text';
                    $fieldName = $field['name'] ?? '';
                    $fieldId = $field['id'] ?? 'filter_'.str_replace(['[', ']'], '_', $fieldName);
                    $fieldValue = $field['value'] ?? '';
                    $fieldColumns = max(1, min(16, (int) ($field['columns'] ?? 4)));
                    $fieldClass = trim('ff-filter-field '.($field['class'] ?? ''));
                    $controlClass = trim('ff-filter-control '.($field['controlClass'] ?? ''));
                @endphp

                <label class="{{ $fieldClass }}" style="--ff-filter-columns: {{ $fieldColumns }};" for="{{ $fieldId }}">
                    <span>{{ $field['label'] ?? $fieldName }}</span>

                    @if ($fieldType === 'select')
                        <select
                            id="{{ $fieldId }}"
                            name="{{ $fieldName }}"
                            class="{{ $controlClass }}"
                            {!! $renderAttributes($field['attributes'] ?? []) !!}
                        >
                            @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                @php
                                    if (is_array($optionLabel)) {
                                        $optionValue = $optionLabel['value'] ?? $optionValue;
                                        $optionLabel = $optionLabel['label'] ?? $optionValue;
                                    }
                                @endphp
                                <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>
                                    {{ $optionLabel }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="{{ $fieldType }}"
                            id="{{ $fieldId }}"
                            name="{{ $fieldName }}"
                            value="{{ $fieldValue }}"
                            class="{{ $controlClass }}"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            {!! $renderAttributes($field['attributes'] ?? []) !!}
                        >
                    @endif

                    @if (! empty($field['help']))
                        <small class="ff-filter-help">{{ $field['help'] }}</small>
                    @endif
                </label>
            @endforeach

            <div class="ff-filter-actions" style="--ff-filter-action-columns: {{ $filterActionsColumns }};">
                <a class="btn ff-filter-clear" href="{{ $filterClearUrl }}">{{ $filterClearLabel }}</a>
                <button class="btn primary" type="submit">
                    <i class="bi bi-search"></i> {{ $filterSubmitLabel }}
                </button>
            </div>
        </div>
    </form>
</section>
