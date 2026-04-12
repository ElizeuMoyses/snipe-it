<?php

namespace App\Presenters;

class ContractStatusLabelPresenter extends Presenter
{
    public static function dataTableLayout()
    {
        $layout = [
            [
                'field' => 'id',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.id'),
                'visible' => false,
            ],
            [
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('general.name'),
                'visible' => true,
                'formatter' => 'contractStatusLabelsLinkFormatter',
            ],
            [
                'field' => 'scope',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/contract_status_labels/table.scope'),
                'visible' => true,
            ],
            [
                'field' => 'meta_type',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/contract_status_labels/table.meta_type'),
                'visible' => true,
            ],
            [
                'field' => 'color',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/contract_status_labels/table.color'),
                'visible' => true,
                'formatter' => 'colorTagFormatter',
            ],
            [
                'field' => 'is_default',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/contract_status_labels/table.is_default'),
                'visible' => true,
                'formatter' => 'trueFalseFormatter',
            ],
            [
                'field' => 'notes',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.notes'),
                'visible' => false,
            ],
            [
                'field' => 'created_by',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_by'),
                'visible' => false,
                'formatter' => 'usersLinkObjFormatter',
            ],
            [
                'field' => 'created_at',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
                'visible' => true,
                'formatter' => 'contractStatusLabelsActionsFormatter',
            ],
        ];

        return json_encode($layout);
    }
}
