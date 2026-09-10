<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only financial summary for the contract list/detail surfaces.
 *
 * This service deliberately consumes the values already stored on the
 * contract and its installments. It does not generate installments, apply
 * amendments, redistribute a manual total, or infer a payment that was not
 * recorded.
 */
class ContractFinancialSummary
{
    public function summarize(Contract $contract, ?Carbon $today = null): array
    {
        $installments = $contract->relationLoaded('installments')
            ? $contract->installments
            : $contract->installments()->with('statusLabel')->get();

        if ($installments instanceof Collection) {
            $installments->loadMissing('statusLabel');
        }

        $today = ($today ?: today())->copy()->startOfDay();
        $plannedTotal = 0;
        $paidTotal = 0;
        $openTotal = 0;
        $overdueTotal = 0;
        $cancelledTotal = 0;
        $cancelledCount = 0;
        $openCount = 0;
        $paidCount = 0;
        $nextDue = null;

        foreach ($installments as $installment) {
            $expected = self::toCents($installment->expected_value);
            $paid = $installment->paid_value === null
                ? 0
                : self::toCents($installment->paid_value);
            $status = $installment->statusLabel?->meta_type;
            $dueDate = $installment->due_date
                ? Carbon::parse($installment->due_date)
                : null;
            $remaining = max($expected - $paid, 0);

            // A recorded payment remains part of the historical paid amount,
            // including when the installment was later cancelled.
            if ($installment->paid_value !== null) {
                $paidTotal += $paid;
                $paidCount++;
            }

            if ($status === 'cancelled') {
                $cancelledTotal += $expected;
                $cancelledCount++;

                continue;
            }

            $plannedTotal += $expected;

            if ($remaining > 0) {
                $openTotal += $remaining;
                $openCount++;

                if (($status === 'overdue' || ($dueDate && $dueDate->lt($today)))) {
                    $overdueTotal += $remaining;
                }

                if ($dueDate && ($nextDue === null
                    || $dueDate->lt($nextDue['date'])
                    || ($dueDate->equalTo($nextDue['date'])
                        && $installment->installment_number < $nextDue['number']))) {
                    $nextDue = [
                        'date' => $dueDate,
                        'number' => (int) $installment->installment_number,
                    ];
                }
            }
        }

        $negotiatedTotal = $contract->total_value === null
            ? null
            : self::toCents($contract->total_value);
        $statusMeta = $contract->statusLabel?->meta_type;
        $endDate = $contract->end_date ? Carbon::parse($contract->end_date) : null;
        $startDate = $contract->start_date ? Carbon::parse($contract->start_date) : null;

        if ($endDate === null) {
            $validity = 'indefinite';
        } elseif ($endDate->lt($today)) {
            $validity = 'expired';
        } elseif ($startDate && $startDate->gt($today)) {
            $validity = 'future';
        } elseif ($endDate->lte($today->copy()->addDays(30))) {
            $validity = 'expiring';
        } else {
            $validity = 'current';
        }

        $renewalCount = 0;
        $lastRenewal = null;
        if ($contract->relationLoaded('amendments')) {
            $renewals = $contract->amendments
                ->where('amendment_type', 'renewal')
                ->sortByDesc('effective_date');
            $renewalCount = $renewals->count();
            $lastRenewal = $renewals->first()?->effective_date;
        }

        return [
            'negotiated_total_cents' => $negotiatedTotal,
            'planned_total_cents' => $plannedTotal,
            'paid_total_cents' => $paidTotal,
            'open_total_cents' => $openTotal,
            'overdue_total_cents' => $overdueTotal,
            'cancelled_total_cents' => $cancelledTotal,
            'cancelled_count' => $cancelledCount,
            'installments_count' => $installments->count(),
            'open_count' => $openCount,
            'paid_count' => $paidCount,
            'negotiated_planned_difference_cents' => $negotiatedTotal === null
                ? null
                : $negotiatedTotal - $plannedTotal,
            'next_due_date' => $nextDue['date'] ?? null,
            'next_due_installment_number' => $nextDue['number'] ?? null,
            'validity' => $validity,
            'status_meta' => $statusMeta,
            'is_closed' => in_array($statusMeta, ['cancelled', 'expired'], true),
            'renewal_count' => $renewalCount,
            'last_renewal_date' => $lastRenewal,
        ];
    }

    /**
     * Convert a persisted decimal value to integer cents without float math.
     */
    public static function toCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = str_replace(',', '.', trim((string) $value));
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function formatCents(?int $cents, ?string $currency = null): ?string
    {
        if ($cents === null) {
            return null;
        }

        $isBrazilian = in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true);
        $decimalSeparator = $isBrazilian ? ',' : '.';
        $thousandsSeparator = $isBrazilian ? '.' : ',';
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
        $number = number_format($whole, 0, $decimalSeparator, $thousandsSeparator)
            .$decimalSeparator.$fraction;
        $prefix = $currency !== null && $currency !== '' ? trim($currency).' ' : '';

        return ($cents < 0 ? '-' : '').$prefix.$number;
    }

    public static function formatValue(mixed $value, ?string $currency = null): string
    {
        return self::formatCents(self::toCents($value), $currency) ?? '';
    }
}
