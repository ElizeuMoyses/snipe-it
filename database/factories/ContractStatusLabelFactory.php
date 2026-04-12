<?php

namespace Database\Factories;

use App\Models\ContractStatusLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractStatusLabelFactory extends Factory
{
    protected $model = ContractStatusLabel::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'scope' => 'contract',
            'meta_type' => 'draft',
            'color' => $this->faker->hexColor(),
            'icon' => 'fa-file-contract',
            'sort_order' => $this->faker->numberBetween(0, 99),
            'is_default' => false,
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory()->superuser(),
        ];
    }

    public function forContracts()
    {
        return $this->state(['scope' => 'contract']);
    }

    public function forInstallments()
    {
        return $this->state(['scope' => 'installment']);
    }

    public function draft()
    {
        return $this->state(['scope' => 'contract', 'meta_type' => 'draft']);
    }

    public function active()
    {
        return $this->state(['scope' => 'contract', 'meta_type' => 'active']);
    }

    public function suspended()
    {
        return $this->state(['scope' => 'contract', 'meta_type' => 'suspended']);
    }

    public function cancelled()
    {
        return $this->state(['scope' => 'contract', 'meta_type' => 'cancelled']);
    }

    public function expired()
    {
        return $this->state(['scope' => 'contract', 'meta_type' => 'expired']);
    }

    public function pending()
    {
        return $this->state(['scope' => 'installment', 'meta_type' => 'pending']);
    }

    public function paid()
    {
        return $this->state(['scope' => 'installment', 'meta_type' => 'paid']);
    }

    public function overdue()
    {
        return $this->state(['scope' => 'installment', 'meta_type' => 'overdue']);
    }

    public function default()
    {
        return $this->state(['is_default' => true]);
    }
}
