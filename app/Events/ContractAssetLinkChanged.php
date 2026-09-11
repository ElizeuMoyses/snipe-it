<?php

namespace App\Events;

use App\Models\Asset;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Post-commit integration notification. The service records the asset and
 * contract audit trails atomically before dispatching this event.
 */
final class ContractAssetLinkChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public const LINKED = 'linked';

    public const UNLINKED = 'unlinked';

    public function __construct(
        public readonly Contract $contract,
        public readonly Asset $asset,
        public readonly string $action,
        public readonly ?int $actorId = null,
        public readonly ?CarbonImmutable $occurredAt = null,
    ) {
    }
}
