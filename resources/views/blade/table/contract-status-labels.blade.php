@props([
    'route' => route('api.contract-status-labels.index'),
    'name' => 'contract-status-labels',
    'presenter' => \App\Presenters\ContractStatusLabelPresenter::dataTableLayout(),
    'fixed_right_number' => 1,
    'fixed_number' => 1,
    'table_header' => trans('admin/contract_status_labels/general.contract_status_labels'),
])

@aware(['name'])

@can('view', \App\Models\Contract::class)

    <x-slot:table_header>
        {{ $table_header }}
    </x-slot:table_header>

    <x-table
        :$presenter
        :$fixed_right_number
        :$fixed_number
        show_column_search="true"
        buttons="contractStatusLabelButtons"
        api_url="{{ $route }}"
        export_filename="export-{{ str_slug($name) }}-contract-status-labels-{{ date('Y-m-d') }}"
    />

@endcan
