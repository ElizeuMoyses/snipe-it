@props([
    'route' => route('api.contracts.index'),
    'name' => 'contracts',
    'presenter' => \App\Presenters\ContractPresenter::dataTableLayout(),
    'fixed_right_number' => 1,
    'fixed_number' => 1,
    'table_header' => trans('admin/contracts/general.contracts'),
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
        buttons="contractButtons"
        api_url="{{ $route }}"
        export_filename="export-{{ str_slug($name) }}-contracts-{{ date('Y-m-d') }}"
    />

@endcan
