<?php

return [

    'does_not_exist' => 'Contract does not exist.',
    'assoc_installments' => 'This contract has paid installments and cannot be deleted.',
    'dashboard_info' => 'Contracts Dashboard',

    // Overdue command
    'overdue' => [
        'check_complete' => 'Overdue check complete.',
        'marked'         => ':count installment(s) marked as overdue.',
        'none_found'     => 'No pending installments past due date.',
    ],

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
        'generate' => [
            'success' => ':count installment(s) generated successfully.',
            'error'   => 'Could not generate installments. Check contract dates and billing cycle.',
        ],
        'already_generated' => 'Installments already exist for this contract.',
    ],

    'amendment' => [
        'no_amendments' => 'No amendments registered for this contract.',
        'create' => [
            'success' => 'Amendment created successfully.',
            'error'   => 'Could not create amendment. Please try again.',
        ],
        'update' => [
            'success' => 'Amendment updated successfully.',
            'error'   => 'Could not update amendment. Please try again.',
        ],
        'delete' => [
            'success' => 'Amendment deleted successfully.',
            'error'   => 'Could not delete amendment. Please try again.',
            'confirm' => 'Are you sure you wish to delete this amendment? Side-effects will NOT be reverted.',
        ],
        'contract_terminal'  => 'Cannot create amendments on a terminated contract (expired or cancelled).',
        'contract_not_active' => 'Readjustments can only be applied to active contracts.',
        'not_activatable'    => 'Renewals can only be applied to active contracts.',
        'no_side_effects'    => 'This amendment type has no automatic side-effects.',
        'readjustment' => [
            'auto_update' => ':count installment(s) updated from :old to :new.',
            'preview'     => 'This readjustment will update :count pending installment(s) from :old to :new.',
        ],
        'renewal' => [
            'generated' => ':count new installment(s) generated until :date.',
            'overlap'   => 'The new end date must be after the previous end date.',
            'preview'   => 'This renewal will extend the contract to :date and generate :count new installment(s).',
        ],
        'termination' => [
            'cancelled' => ':count pending installment(s) cancelled.',
            'preview'   => 'This termination will cancel :count pending installment(s) after :date.',
        ],
    ],

    'asset' => [
        'no_assets' => 'No assets linked to this contract.',
        'attach' => [
            'success' => 'Asset linked to contract successfully.',
            'error'   => 'Could not link asset. Please try again.',
        ],
        'detach' => [
            'success' => 'Asset unlinked from contract successfully.',
            'confirm' => 'Are you sure you wish to unlink this asset from the contract?',
        ],
        'already_linked' => 'This asset is already linked to the contract.',
    ],

];
