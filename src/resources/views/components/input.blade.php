@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'error' => null,
    'helper' => null,
])

<div class="form-group">
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        class="form-input"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        {{ $attributes }}
    >
    @if($error)
        <p class="error-text">{{ $error }}</p>
    @elseif($helper)
        <p class="text-caption text-muted" style="margin-top:4px">{{ $helper }}</p>
    @endif
</div>
