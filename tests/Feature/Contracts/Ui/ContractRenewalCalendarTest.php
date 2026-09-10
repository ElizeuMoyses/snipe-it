<?php

namespace Tests\Feature\Contracts\Ui;

use App\Models\Contract;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContractRenewalCalendarTest extends TestCase
{
    public static function calendars(): array
    {
        return [
            ['2026-01-31', '2026-03-31', '2026-06-30', 'monthly', null, ['2026-04-30', '2026-05-31', '2026-06-30']],
            ['2024-01-31', '2024-01-31', '2024-03-31', 'monthly', null, ['2024-02-29', '2024-03-31']],
            ['2026-01-30', '2026-01-30', '2026-03-30', 'monthly', null, ['2026-02-28', '2026-03-30']],
            ['2026-01-31', '2026-02-28', '2026-07-31', 'quarterly', null, ['2026-04-30', '2026-07-31']],
            ['2024-02-29', '2024-12-31', '2028-02-29', 'annual', null, ['2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29']],
            ['2026-01-10', '2026-01-31', '2026-03-27', 'monthly', 28, ['2026-02-28']],
        ];
    }

    #[DataProvider('calendars')]
    public function test_preview_and_renewal_preserve_original_calendar($start, $oldEnd, $newEnd, $cycle, $day, $expected): void
    {
        $this->actingAs(User::factory()->superuser()->create());
        $contract = Contract::factory()->withActiveStatus()->create([
            'start_date'=>$start, 'end_date'=>$oldEnd, 'billing_cycle'=>$cycle,
            'billing_day'=>$day, 'installment_value'=>'100.00',
        ]);
        $this->assertTrue($contract->exists, (string) $contract->getErrors());
        $contract->generateInstallments();
        $original = $contract->installments()->orderBy('id')->get()->toArray();
        $input = ['amendment_type'=>'renewal', 'description'=>'Synthetic calendar renewal',
            'effective_date'=>\Carbon\Carbon::parse($oldEnd)->addDay()->format('Y-m-d'),
            'old_end_date'=>$oldEnd, 'new_end_date'=>$newEnd];
        $this->postJson(route('contracts.amendments.preview', $contract), $input)
            ->assertOk()->assertJsonPath('estimated_count', count($expected));
        $this->post(route('contracts.amendments.store', $contract), $input)->assertSessionHasNoErrors();
        $rows = $contract->installments()->orderBy('id')->get();
        $this->assertSame($original, $rows->take(count($original))->toArray());
        $this->assertSame($expected, $rows->skip(count($original))->map(fn ($row) => $row->due_date->format('Y-m-d'))->values()->all());
        $this->assertSame(0, $contract->fresh()->generateInstallments());
    }
}
