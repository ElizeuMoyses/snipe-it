<?php

namespace App\Presenters;

class ContractAuditPresenter
{
    public static function dataTableLayout(): string
    {
        return json_encode([
            [
                'field' => 'occurred_at',
                'sortable' => true,
                'title' => trans('general.created_at'),
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'actor_name',
                'sortable' => true,
                'searchable' => true,
                'title' => trans('general.created_by'),
            ],
            [
                'field' => 'action',
                'sortable' => true,
                'searchable' => true,
                'title' => trans('general.action'),
            ],
            [
                'field' => 'entity_label',
                'sortable' => true,
                'searchable' => true,
                'title' => trans('general.item'),
            ],
            [
                'field' => 'details',
                'sortable' => false,
                'searchable' => true,
                'title' => trans('admin/hardware/table.changed'),
            ],
            [
                'field' => 'source',
                'sortable' => true,
                'searchable' => true,
                'title' => trans('general.action_source'),
            ],
            [
                'field' => 'historical_label',
                'sortable' => false,
                'searchable' => false,
                'title' => trans('general.history'),
            ],
        ]);
    }
}
