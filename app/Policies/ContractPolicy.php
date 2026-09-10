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
        return ($item === null || ! $item->trashed())
            && $user->hasAccess('contracts.installments');
    }

    public function files(User $user, $item = null)
    {
        return ($item === null || ! $item->trashed())
            && $user->hasAccess('contracts.files');
    }

    public function update(User $user, $item = null)
    {
        return ($item === null || ! $item->trashed())
            && $user->hasAccess('contracts.edit');
    }

    public function restore(User $user, $item = null)
    {
        return ($item !== null && $item->trashed())
            && $user->hasAccess('contracts.delete');
    }
}
