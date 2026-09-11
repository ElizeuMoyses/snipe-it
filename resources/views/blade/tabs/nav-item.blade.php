@props([
    'name' => null,
    'label' => null,
    'count' => 0,
    'icon' => null,
    'icon_style' => null,
    'tooltip' => null,
    'icon_type' => null,
    'show_label' => false,
    'show_count' => false,
])

@php
    $isActive = str_contains((string) $attributes->get('class', ''), 'active');
    $tabId = 'tab-' . ($name ?? 'details');
@endphp

<!-- start tab nav item -->
<li {{ $attributes->merge(['class' => 'snipetab']) }} role="presentation">

    <a id="{{ $tabId }}"
       href="#{{ $name ?? 'details' }}"
       role="tab"
       aria-controls="{{ $name ?? 'details' }}"
       aria-selected="{{ $isActive ? 'true' : 'false' }}"
       tabindex="{{ ! $show_label || $isActive ? '0' : '-1' }}"
       data-toggle="tab"
       data-tooltip="true"
       title="{{ $tooltip ?? $label }}">

        @if ($icon_type || $icon)

            @if ($icon)
                @if ($show_label)
                    <span class="tab-label">
                        <i class="{{ $icon }}" style="font-size: 16px" aria-hidden="true"></i>
                        {{ $label }}
                    </span>
                @else
                    <span class="hidden-lg hidden-md hidden-sm">
                        <i class="{{ $icon }}" style="font-size: 18px" aria-hidden="true"></i>
                        {{ $tooltip ?? $label }}
                    </span>

                    <span class="hidden-xs">
                        <i class="{{ $icon }}" style="font-size: 16px" aria-hidden="true"></i>
                    </span>
                @endif

            @elseif ($icon_type)
                @if ($show_label)
                    <span class="tab-label">
                        <x-icon type="{{ $icon_type }}" class="fa-fw" style="font-size: 16px;" />
                        {{ $label }}
                    </span>
                @else
                    <span class="hidden-lg hidden-md hidden-sm">
                        <x-icon type="{{ $icon_type }}" class="fa-fw" style="font-size: 18px;" />
                        {{ $tooltip ?? $label }}
                    </span>

                    <span class="hidden-xs">
                        <x-icon type="{{ $icon_type }}" class="fa-fw" style="font-size: 16px;" />
                    </span>
                @endif

            @endif

            @if (! $show_label)
                <span class="sr-only">
                    {{ $label }}
                </span>
            @endif

        @elseif ($label)
            {{ $label }}
        @endif


        @if ($show_count || $count > 0)
            <span class="badge">{{ number_format((int) $count) }}</span>
        @endif

    </a>
</li>
<!-- end tab nav item -->