<?php

namespace Database\Factories;

use App\Models\MessModel;
use App\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserModel>
 */
class UserFactory extends Factory
{
    protected $model = UserModel::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mess_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->numerify('9#########'),
            'room_no' => null,
            'member_code' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'member',
            'is_loggedin' => false,
            'status' => 'active',
            'joining_date' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'mess_id' => null,
            'role' => 'admin',
            'member_code' => null,
        ]);
    }

    public function forMess(MessModel $mess, string $role = 'member'): static
    {
        return $this->state(fn (array $attributes) => [
            'mess_id' => $mess->id,
            'role' => $role,
        ]);
    }
}
