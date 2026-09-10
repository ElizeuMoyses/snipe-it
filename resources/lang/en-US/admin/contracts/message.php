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

    'archive' => [
        'confirm' => 'Archive this contract record? This is not a financial termination.',
        'impact' => 'The contract will leave the active list. Its installments, amendments, linked assets and history remain consultable. Paid installments block archiving. A reason is required.',
        'detail' => 'Archiving contract :name preserves :installments installment(s) and :amendments amendment(s). It does not cancel obligations or restore files deleted separately.',
        'success' => 'Contract archived successfully. Its related records and audit history were preserved.',
        'blocked_paid' => 'This contract has :count paid installment(s) and cannot be archived. Use the contract termination/amendment flow instead.',
        'already_archived' => 'This contract is already archived.',
        'not_archived' => 'This contract is not archived.',
        'restore_reason' => 'Contract restored through the authorized archive recovery flow.',
        'restore_conflict' => 'The contract cannot be restored because its contract number is already used by an active contract.',
        'restored' => 'Contract restored successfully. Existing installments and amendments were preserved; no records or files were regenerated.',
        'error' => 'The contract lifecycle operation could not be completed. No partial archive or restore was kept.',
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
            'applied' => 'This amendment already applied contract or installment effects. Simple deletion is blocked; use a tracked correction.',
        ],
        'contract_terminal'  => 'Cannot create amendments on a terminated contract (expired or cancelled).',
        'contract_not_active' => 'Readjustments can only be applied to active contracts.',
        'not_activatable'    => 'Renewals can only be applied to active contracts.',
        'no_side_effects'    => 'This amendment type has no automatic side-effects.',
        'validation' => [
            'type_required' => 'Select an amendment type.',
            'type_invalid' => 'Select a valid amendment type.',
            'description_required' => 'Enter the amendment description/reason.',
            'date_required' => 'Enter the effective date.',
            'date_invalid' => 'Enter a valid date in YYYY-MM-DD format.',
            'value_invalid' => 'Enter a valid monetary value with up to two decimal places.',
            'new_value_required' => 'Enter the new value.',
            'new_value_invalid' => 'The new value must be greater than zero.',
            'old_end_required' => 'Enter the contract current end date.',
            'new_end_required' => 'Enter the new end date.',
            'new_end_after_old' => 'The new end date must be after the previous end date.',
            'old_value_mismatch' => 'The previous value must match the current server value. Generate a new preview.',
            'old_end_mismatch' => 'The previous end date does not match the current contract. Generate a new preview.',
            'ticket_too_long' => 'The documentary reference cannot exceed 100 characters.',
            'preview_required' => 'Generate a new preview before confirming the amendment.',
            'preview_invalid' => 'The preview could not be validated. Generate a new preview.',
            'preview_stale' => 'The preview is stale or the data changed. Review the form and generate a new preview.',
        ],
        'preview_details' => [
            'description' => 'Recorded description',
            'server_snapshot_help' => 'Current installment value. To correct it, review the contract before creating the amendment.',
            'current_value' => 'Current installment value',
            'new_value' => 'New value',
            'difference_absolute' => 'Absolute difference',
            'difference_percent' => 'Percentage change',
            'effective_date' => 'Effective date',
            'affected_installments' => 'Affected installments',
            'preserved_installments' => 'Preserved installments',
            'current_end_date' => 'Current end',
            'new_end_date' => 'New end',
            'period_added' => 'Period added',
            'generated_installments' => 'New installments',
            'result_status' => 'Resulting status',
            'cancelled_installments' => 'Cancelled installments',
            'financial_effect' => 'Automatic financial effect',
            'financial_policy' => 'Financial rule',
            'documentary_only' => 'None: documentary record only.',
            'termination_policy' => 'Fines, refunds and proration are not calculated automatically without an approved rule.',
            'cancelled_status' => 'Cancelled',
            'installments_summary' => ':count installment(s) — :value',
            'from_to' => '(:from → :to)',
            'days' => ':count day(s)',
            'none' => 'None',
            'not_calculated' => 'Not calculated',
        ],
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
        'not_available' => 'This asset is not available to link to this contract.',
        'not_linked' => 'This asset is not linked to this contract.',
        'contract_closed' => 'Closed contracts do not allow linked assets to be changed.',
        'no_permission' => 'You do not have permission to view assets; the link cannot be changed.',
        'selector' => [
            'help' => 'Search is limited to authorized assets from the contract company and excludes existing links.',
            'loading' => 'Loading authorized assets...',
            'no_results' => 'No authorized assets are available for this contract.',
            'error' => 'The assets could not be loaded. Please try again.',
        ],
    ],

];
