<label>
    Data início *
    <input type="date" name="data_inicio" value="{{ old('data_inicio', now()->format('Y-m-d')) }}" required>
</label>

<label>
    Data fim
    <input type="date" name="data_fim" value="{{ old('data_fim') }}">
</label>

<label>
    Status *
    <select name="status" required>
        @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('status', 'planejada') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
