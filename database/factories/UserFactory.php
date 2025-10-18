<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
  /**
   * The current password being used by the factory.
   */

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'name' => fake()->name(),
      'email' => fake()->unique()->safeEmail(),
      'phone' => fake()->numerify('##########'),
      'gender' => fake()->randomElement(['Nam', 'Nữ']),
      'province_id' => fake()->numerify('##'),
      'district_id' => fake()->numerify('##'),
      'ward_id' => fake()->numerify('##'),
      'address' => fake()->address(),
      'birthday' => fake()->date('Y-m-d', '-18 years'),
      'description' => fake()->sentence(),
      'email_verified_at' => now(),
      'password' => Hash::make('password'),
      'status' => fake()->randomElement([1, 0]),
      'is_ngoai_gio' => fake()->randomElement([0, 1]),
      'remember_token' => Str::random(10),
      'ma_vai_tro' => 'NHAN_VIEN',
    ];
  }

  /**
   * Indicate that the model's email address should be unverified.
   */
  public function unverified(): static
  {
    return $this->state(fn(array $attributes) => [
      'email_verified_at' => null,
    ]);
  }
}