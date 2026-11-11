@php
    $labels = [
        'filters' => __('filament-dynamic-tables::dynamic-tables.form.summary.filters'),
        'search' => __('filament-dynamic-tables::dynamic-tables.form.summary.search'),
        'sort' => __('filament-dynamic-tables::dynamic-tables.form.summary.sort'),
        'grouping' => __('filament-dynamic-tables::dynamic-tables.form.summary.grouping'),
        'columns' => __('filament-dynamic-tables::dynamic-tables.form.summary.columns'),
    ];
@endphp

<div class="fdt-summary">
    <p class="fdt-summary-heading">{{ __('filament-dynamic-tables::dynamic-tables.form.summary.heading') }}</p>

    @if (blank($summary))
        <p class="fdt-summary-empty">{{ __('filament-dynamic-tables::dynamic-tables.form.summary.empty') }}</p>
    @else
        <dl class="fdt-summary-list">
            @foreach ($summary as $section => $items)
                <div class="fdt-summary-row">
                    <dt>{{ $labels[$section] ?? $section }}</dt>
                    <dd>
                        @foreach ($items as $item)
                            <x-filament::badge size="sm" color="gray">{{ $item }}</x-filament::badge>
                        @endforeach
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if (filled($presetHelperText))
        <p class="fdt-summary-help fdt-summary-help-warning">{{ $presetHelperText }}</p>
    @endif

    @if (filled($helperText))
        <p class="fdt-summary-help">{{ $helperText }}</p>
    @endif
</div>
