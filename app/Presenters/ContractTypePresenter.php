<?php

namespace App\Presenters;

class ContractTypePresenter extends Presenter
{
    public static function dataTableLayout()
    {
        return json_encode([
            ['field' => 'name', 'searchable' => true, 'sortable' => true, 'title' => trans('admin/contract_types/table.name'), 'visible' => true, 'formatter' => 'contractTypesLinkFormatter'],
            ['field' => 'code', 'searchable' => true, 'sortable' => true, 'title' => trans('admin/contract_types/table.code'), 'visible' => true],
            ['field' => 'is_active', 'searchable' => false, 'sortable' => true, 'title' => trans('admin/contract_types/table.is_active'), 'visible' => true],
            ['field' => 'contracts_count', 'searchable' => false, 'sortable' => true, 'title' => trans('admin/contract_types/table.contracts_count'), 'visible' => true],
            ['field' => 'actions', 'searchable' => false, 'sortable' => false, 'title' => trans('table.actions'), 'visible' => true, 'formatter' => 'contractTypesActionsFormatter'],
        ]);
    }
}
