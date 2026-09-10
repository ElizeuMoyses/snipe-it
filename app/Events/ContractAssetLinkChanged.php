<?php

namespace App\Events;

use App\Models\Asset;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Integration event for the unified contract history.
 *
 * The contract asset flow does not write an Actionlog directly. The history
 * implementation coordinated in issue #13 can consume this event and record
 * the pivot change exactly once after the transaction commits.
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
