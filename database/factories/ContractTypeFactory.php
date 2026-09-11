<?php

namespace Database\Factories;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractTypeFactory extends Factory
{
    protected $model = ContractType::class;

    public function definition()
    {
        $code = $this->faker->unique()->bothify('custom-####');

        return [
            'name' => 'Custom '.$this->faker->unique()->words(2, true),
            'code' => $code,
            'is_active' => true,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function inactive()
    {
        return $this->state(['is_active' => false]);
    }
}
