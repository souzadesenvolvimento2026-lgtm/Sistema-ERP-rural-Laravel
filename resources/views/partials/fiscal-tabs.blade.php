<div class="ff-fiscal-shell mb-4">
    <ul class="nav ff-fiscal-tabs">
        @foreach ($fiscalTabs as $tab)
            <li class="nav-item">
                <a class="nav-link {{ $tab['active'] ? 'active' : '' }}" href="{{ route($tab['route']) }}">
                    <i class="bi {{ $tab['icon'] }}"></i>
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
