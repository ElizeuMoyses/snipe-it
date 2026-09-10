@props([
    'route' => route('api.contract-types.index'),
    'name' => 'contract-types',
    'presenter' => \App\Presenters\ContractTypePresenter::dataTableLayout(),
])

@can('view', \App\Models\ContractType::class)
    <x-slot:table_header>{{ trans('admin/contract_types/general.title') }}</x-slot:table_header>
    <x-table
        :$presenter
        show_column_search="true"
        buttons="contractTypeButtons"
        api_url="{{ $route }}"
        export_filename="export-{{ str_slug($name) }}-{{ date('Y-m-d') }}"
    />
@endcan
