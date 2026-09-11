<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company() . ' Contract',
            'contract_number' => 'CTR-' . $this->faker->unique()->numerify('####'),
            'contract_type' => 'recurring',
            'status_label_id' => ContractStatusLabel::factory()->draft(),
            'supplier_id' => Supplier::factory(),
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('+6 months', '+2 years')->format('Y-m-d'),
            'billing_cycle' => 'monthly',
            'installment_value' => $this->faker->randomFloat(2, 100, 10000),
            'total_value' => null,
            'total_installments' => null,
            'readjustment_index' => null,
            'readjustment_month' => null,
            'description' => $this->faker->optional()->paragraph(),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory()->superuser(),
        ];
    }

    public function recurring()
    {
        return $this->state([
            'contract_type' => 'recurring',
            'billing_cycle' => 'monthly',
        ]);
    }

    public function oneTime()
    {
        return $this->state([
            'contract_type' => 'one_time',
            'billing_cycle' => 'one_time',
            'total_installments' => 1,
        ]);
    }

    public function quarterly()
    {
        return $this->state([
            'contract_type' => 'recurring',
            'billing_cycle' => 'quarterly',
        ]);
    }

    public function withActiveStatus()
    {
        return $this->state([
            'status_label_id' => ContractStatusLabel::factory()->active(),
        ]);
    }
}
