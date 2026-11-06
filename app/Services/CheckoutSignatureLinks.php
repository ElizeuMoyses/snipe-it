<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CheckoutSignatureLinks
{
    /**
     * Present only acceptances created by this successful asset checkout.
     * The caller has already authorized each asset. Never regenerate tokens here.
     */
    public static function forAssets(array $assetIds, Model $target, int $afterId): array
    {
        if (! $target instanceof User || ! $assetIds) {
            return [];
        }

        return CheckoutAcceptance::query()
            ->where('id', '>', $afterId)
            ->where('checkoutable_type', Asset::class)
            ->whereIn('checkoutable_id', $assetIds)
            ->where('assigned_to_id', $target->id)
            ->pending()
            ->with('checkoutable')
            ->orderBy('id')
            ->get()
            ->filter(fn (CheckoutAcceptance $acceptance) => $acceptance->checkoutable && $acceptance->isTokenValid())
            ->map(fn (CheckoutAcceptance $acceptance) => [
                'acceptance_id' => $acceptance->id,
                'asset_tag' => $acceptance->checkoutable->asset_tag,
                'asset_name' => $acceptance->checkoutable->name,
                'assignee' => $target->getFullNameAttribute(),
                'url' => url('/eula/sign/'.$acceptance->token),
                'expires_at' => $acceptance->token_expires_at->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();
    }
}
