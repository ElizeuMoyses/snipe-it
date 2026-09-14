<?php
return [
    'bulk_delete' => 'Delete selected installments',
    'select_all' => 'Select all eligible installments',
    'select' => 'Select installment :number',
    'bulk_confirm' => 'Delete selected installments? Count: ',
    'bulk_locked' => 'Selection contains paid or cancelled installments. No installments were deleted.',
    'bulk_success' => ':count installment(s) deleted with history preserved.',
    'reopen' => 'Correct payment',
    'reopen_help' => 'Undoing the payment record reopens the installment for editing, cancellation, deletion or recording the correct payment. Original payment data stays in history. This does not issue a bank refund.',
    'reason' => 'Reason for correction',
    'reopen_submit' => 'Undo payment record and reopen installment',
    'reopen_locked' => 'Only paid installments on open contracts can be reopened.',
    'reopen_success' => 'Payment record undone. Installment reopened; original data and reason preserved in history.',
];
