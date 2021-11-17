<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'bid' => $this->faker->numberBetween($min = 20, $max = 200),
            'user_id' => 1,
            'item_id' => $this->faker->randomElement($array = \App\Models\Item::pluck('id'))
        ];
    }
}
