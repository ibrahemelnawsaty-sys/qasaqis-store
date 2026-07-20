<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['إعلانات', 'رواتب', 'تغليف', 'إيجار', 'أخرى']),
            'title' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'incurred_on' => now()->toDateString(),
            'note' => null,
            'created_by' => null,
        ];
    }
}
