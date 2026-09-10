<?php

namespace App\Exceptions;

use RuntimeException;

final class ContractAssetLinkException extends RuntimeException
{
    public const CONTRACT_CLOSED = 'contract_closed';

    public const COMPANY_MISMATCH = 'company_mismatch';

    public const ASSET_NOT_AVAILABLE = 'asset_not_available';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
