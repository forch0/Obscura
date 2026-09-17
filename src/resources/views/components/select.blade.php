@props([
    'name',
    'id' => null,
    'options' => [],        // ['value' => 'Label'] or [['value'=>..,'label'=>..,'disabled'=>true]]
    'selected' => null,
    'placeholder' => 'Select…',
    'align' => 'left',
    'disabledOptions' => '', // comma-separated values to disable
])

@php
    $id = $id ?? $name;
    $disabledValues = array_filter(array_map('trim', explode(',', $disabledOptions)));
    $normalized = [];
    foreach ($options as $key => $opt) {
        if (is_array($opt)) {
            $normalized[] = [
                'value' => $opt['value'],
                'label' => $opt['label'],
                'disabled' => $opt['disabled'] ?? false,
            ];
        } else {
            $normalized[] = [
                'value' => $key,
                'label' => $opt,
                'disabled' => in_array((string)$key, $disabledValues),
            ];
        }
    }
    $currentLabel = collect($normalized)->firstWhere('value', $selected)['label'] ?? $placeholder;
@endphp

<div class="dropdown select-dropdown" {{ $attributes }}>
    <button type="button" class="form-input select-toggle dropdown-toggle" id="{{ $id }}-toggle" aria-haspopup="listbox" aria-expanded="false">
        <span class="select-value">{{ $currentLabel }}</span>
        <span class="caret">&#9662;</span>
    </button>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $selected }}">
    <div class="dropdown-menu select-menu {{ $align === 'right' ? 'dropdown-menu-right' : '' }}" role="listbox">
        @foreach($normalized as $opt)
            <button type="button"
                class="dropdown-item {{ $opt['value'] === $selected ? 'selected' : '' }}"
                data-value="{{ $opt['value'] }}"
                role="option"
                @if($opt['disabled']) disabled @endif
                aria-selected="{{ $opt['value'] === $selected ? 'true' : 'false' }}">{{ $opt['label'] }}</button>
        @endforeach
    </div>
</div>
