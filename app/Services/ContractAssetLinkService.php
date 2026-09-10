<?php

namespace App\Services;

use App\Events\ContractAssetLinkChanged;
use App\Exceptions\ContractAssetLinkException;
use App\Models\Asset;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

final class ContractAssetLinkService
{
    public const ATTACHED = 'attached';

    public const ALREADY_LINKED = 'already_linked';

    public const DETACHED = 'detached';

    public const NOT_LINKED = 'not_linked';

    public function attach(Contract $contract, int $assetId): string
    {
        return DB::transaction(function () use ($contract, $assetId): string {
            $lockedContract = Contract::query()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedContract) {
                throw new ContractAssetLinkException(ContractAssetLinkException::ASSET_NOT_AVAILABLE);
            }

            $lockedContract->load('statusLabel');
            $this->assertContractCanChangeLinks($lockedContract);

            $lockedAsset = Asset::query()
                ->whereKey($assetId)
                ->lockForUpdate()
                ->first();

            if (! $lockedAsset) {
                throw new ContractAssetLinkException(ContractAssetLinkException::ASSET_NOT_AVAILABLE);
            }

            $this->assertSameCompany($lockedContract, $lockedAsset);

            $inserted = DB::table('contract_asset')->insertOrIgnore([
                'contract_id' => $lockedContract->getKey(),
                'asset_id' => $lockedAsset->getKey(),
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return self::ALREADY_LINKED;
            }

            $audit = app(\App\Services\Contracts\ContractAuditService::class);
            $audit->record($lockedContract, 'asset.attached', $lockedAsset,
                [], $audit->snapshot($lockedAsset), ['asset_id' => $assetId]);
            ContractAssetLinkChanged::dispatch(
                $lockedContract,
                $lockedAsset,
                ContractAssetLinkChanged::LINKED,
                auth()->id(),
                now()->toImmutable(),
            );

            return self::ATTACHED;
        });
    }

    public function detach(Contract $contract, int $assetId): string
    {
        return DB::transaction(function () use ($contract, $assetId): string {
            $lockedContract = Contract::query()
                ->whereKey($contract->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedContract) {
                throw new ContractAssetLinkException(ContractAssetLinkException::ASSET_NOT_AVAILABLE);
            }

            $lockedContract->load('statusLabel');
            $this->assertContractCanChangeLinks($lockedContract);

            $lockedAsset = Asset::query()
                ->whereKey($assetId)
                ->lockForUpdate()
                ->first();

            if (! $lockedAsset) {
                throw new ContractAssetLinkException(ContractAssetLinkException::ASSET_NOT_AVAILABLE);
            }

            $this->assertSameCompany($lockedContract, $lockedAsset);

            $deleted = DB::table('contract_asset')
                ->where('contract_id', $lockedContract->getKey())
                ->where('asset_id', $lockedAsset->getKey())
                ->delete();

            if ($deleted === 0) {
                return self::NOT_LINKED;
            }

            $audit = app(\App\Services\Contracts\ContractAuditService::class);
            $audit->record($lockedContract, 'asset.detached', $lockedAsset,
                $audit->snapshot($lockedAsset), [], ['asset_id' => $assetId]);
            ContractAssetLinkChanged::dispatch(
                $lockedContract,
                $lockedAsset,
                ContractAssetLinkChanged::UNLINKED,
                auth()->id(),
                now()->toImmutable(),
            );

            return self::DETACHED;
        });
    }

    private function assertContractCanChangeLinks(Contract $contract): void
    {
        if ($contract->assetLinksAreLocked()) {
            throw new ContractAssetLinkException(ContractAssetLinkException::CONTRACT_CLOSED);
        }
    }

    private function assertSameCompany(Contract $contract, Asset $asset): void
    {
        if ($contract->company_id !== $asset->company_id) {
            throw new ContractAssetLinkException(ContractAssetLinkException::COMPANY_MISMATCH);
        }
    }
}
