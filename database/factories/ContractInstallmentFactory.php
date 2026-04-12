<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractInstallmentFactory extends Factory
{
    protected $model = ContractInstallment::class;

    public function definition()
    {
        return [
            'contract_id' => Contract::factory(),
            'installment_number' => $this->faker->numberBetween(1, 12),
            'reference_date' => $this->faker->date(),
            'due_date' => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'expected_value' => $this->faker->randomFloat(2, 100, 5000),
            'paid_value' => null,
            'payment_date' => null,
            'payment_method' => null,
            'status_label_id' => ContractStatusLabel::factory()->pending(),
            'ticket_reference' => null,
            'notes' => null,
            'created_by' => User::factory()->superuser(),
        ];
    }

    public function paid()
    {
        return $this->state([
            'status_label_id' => ContractStatusLabel::factory()->paid(),
            'paid_value' => $this->faker->randomFloat(2, 100, 5000),
            'payment_date' => $this->faker->date(),
            'payment_method' => 'transfer',
        ]);
    }
}
