@props([
    'tabnav',
    'tabpanes',
    'mobile_dropdown' => true,
])

<!-- start tab container -->
<div {{ $attributes->merge(['class' => 'nav-tabs-custom']) }}>

    <ul class="nav nav-tabs hidden-print {{ $mobile_dropdown ? 'nav-tabs-dropdown' : '' }}" role="tablist">
        @if (!$tabnav->isEmpty())
            {{ $tabnav }}
        @endif
    </ul>

    <div class="tab-content">
        @if (!$tabpanes->isEmpty())
            {{ $tabpanes }}
        @endif
    </div>


</div>
<!-- end tab container -->