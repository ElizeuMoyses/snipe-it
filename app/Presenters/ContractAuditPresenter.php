<?php

namespace App\Presenters;

class ContractAuditPresenter
{
    public static function dataTableLayout(): string
    {
        return json_encode([
            [
                'field' => 'occurred_at',
                'sortable' => false,
                'title' => trans('general.created_at'),
                'formatter' => 'dateDisplayFormatter',
            ],
            [
                'field' => 'actor_name',
                'sortable' => false,
                'searchable' => true,
                'title' => trans('general.created_by'),
            ],
            [
                'field' => 'action_label',
                'sortable' => false,
                'searchable' => true,
                'title' => trans('general.action'),
            ],
            [
                'field' => 'entity_label',
                'sortable' => false,
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
                'sortable' => false,
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
