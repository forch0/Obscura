@props([
    'label' => 'Menu',
    'variant' => 'secondary',
    'size' => 'sm',
    'align' => 'left', // 'left' or 'right'
])

<div class="dropdown" {{ $attributes }}>
    <button type="button" class="btn btn-{{ $variant }} btn-{{ $size }} dropdown-toggle" aria-haspopup="true" aria-expanded="false">
        {{ $label }}<span class="caret">&#9662;</span>
    </button>
    <div class="dropdown-menu {{ $align === 'right' ? 'dropdown-menu-right' : '' }}" role="menu">
        {{ $slot }}
    </div>
</div>
