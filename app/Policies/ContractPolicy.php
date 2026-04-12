<?php

namespace App\Policies;

use App\Models\User;

class ContractPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'contracts';
    }

    public function installments(User $user, $item = null)
    {
        return $user->hasAccess('contracts.installments');
    }

    public function files(User $user, $item = null)
    {
        return $user->hasAccess('contracts.files');
    }
}
