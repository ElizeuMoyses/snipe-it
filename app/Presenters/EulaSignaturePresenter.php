<?php

namespace App\Presenters;

/**
 * Class EulaSignaturePresenter
 */
class EulaSignaturePresenter extends Presenter
{
    /**
     * Json Column Layout for bootstrap table
     * @return string
     */
    public static function dataTableLayout()
    {
        $layout = [
            [
                'field' => 'checkbox',
                'checkbox' => true,
                'titleTooltip' => trans('general.select_all_none'),
                'printIgnore' => true,
            ],
            [
                'field' => 'id',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.id'),
                'visible' => true,
            ],
            [
                'field' => 'assigned_to',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/hardware/form.checkedout_to'),
                'visible' => true,
            ],
            [
                'field' => 'checkoutable',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.item'),
                'visible' => true,
            ],
            [
                'field' => 'checkoutable_type',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.type'),
                'visible' => true,
            ],
            [
                'field' => 'accepted_at',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.accepted_date'),
                'visible' => true,
            ],
            [
                'field' => 'signature_device_type',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.device_type'),
                'visible' => true,
            ],
            [
                'field' => 'location_display',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('general.location'),
                'visible' => true,
            ],
            [
                'field' => 'signature_ip',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.ip_address'),
                'visible' => false,
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
            ],
        ];

        return json_encode($layout);
    }

    /**
     * Presets for EULA signature reports
     * @return array
     */
    public static function presets()
    {
        return [
            'all' => [
                'displayName' => trans('general.all'),
                'columns' => [
                    'id',
                    'assigned_to',
                    'checkoutable',
                    'checkoutable_type',
                    'accepted_at',
                    'signature_device_type',
                    'signature_latitude',
                ],
            ],
            'minimal' => [
                'displayName' => trans('general.minimal'),
                'columns' => [
                    'id',
                    'assigned_to',
                    'checkoutable',
                    'accepted_at',
                ],
            ],
            'detailed' => [
                'displayName' => trans('general.detailed'),
                'columns' => [
                    'id',
                    'assigned_to',
                    'checkoutable',
                    'checkoutable_type',
                    'accepted_at',
                    'signature_device_type',
                    'signature_latitude',
                    'signature_ip',
                    'created_at',
                ],
            ],
        ];
    }
}