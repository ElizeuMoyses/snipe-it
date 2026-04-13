<?php

return [

    'does_not_exist' => 'Contract does not exist.',
    'assoc_installments' => 'This contract has paid installments and cannot be deleted.',
    'dashboard_info' => 'The contracts dashboard will be available in Phase 4.',

    'create' => [
        'error' => 'Contract was not created, please try again.',
        'success' => 'Contract created successfully.',
    ],

    'update' => [
        'error' => 'Contract was not updated, please try again.',
        'success' => 'Contract updated successfully.',
    ],

    'delete' => [
        'confirm' => 'Are you sure you wish to delete this contract?',
        'error' => 'There was an issue deleting the contract. Please try again.',
        'success' => 'Contract was deleted successfully.',
    ],

    'installment' => [
        'contract_terminal' => 'Cannot create installments on a terminated contract (expired or cancelled).',
        'create' => [
            'success' => 'Installment created successfully.',
            'error'   => 'Could not create installment. Please try again.',
            'missing_default_status' => 'Default pending status not configured. Please create a default installment status label with meta_type "pending".',
        ],
        'update' => [
            'success' => 'Installment updated successfully.',
            'error'   => 'Could not update installment. Please try again.',
        ],
        'delete' => [
            'success' => 'Installment deleted successfully.',
            'error'   => 'Could not delete installment. Please try again.',
        ],
        'payment' => [
            'success'                => 'Payment registered successfully.',
            'error'                  => 'Could not register payment. Please try again.',
            'already_terminal'       => 'This installment is already paid or cancelled. No further changes allowed.',
            'missing_default_status' => 'Default paid status not configured. Please create a default installment status label with meta_type "paid".',
        ],
        'status' => [
            'success'            => 'Installment status updated successfully.',
            'error'              => 'Could not update installment status. Please try again.',
            'terminal_locked'    => 'This installment has a terminal status (paid/cancelled) and cannot be modified.',
            'transition_blocked' => 'This status transition is not allowed. Check the permitted transitions.',
            'invalid_scope'      => 'The selected status does not belong to the installment scope.',
        ],
        'terminal_locked' => 'This installment has a terminal status and cannot be modified.',
    ],

];
