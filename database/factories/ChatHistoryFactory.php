<?php

namespace Database\Factories;

use App\Models\ChatHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatHistory>
 */
class ChatHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'thread_id' => fake()->unique()->uuid(),
            'messages' => [],
        ];
    }
}
