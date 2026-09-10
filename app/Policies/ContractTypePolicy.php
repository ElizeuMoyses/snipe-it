<?php

namespace App\Policies;

class ContractTypePolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'contract_types';
    }
}
