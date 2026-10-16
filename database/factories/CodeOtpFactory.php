<?php

namespace Database\Factories;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Enums\ObjetOtp;
use App\Models\CodeOtp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeOtp>
 */
class CodeOtpFactory extends Factory
{
    protected $model = CodeOtp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telephone' => '+22507'.fake()->numerify('########'),
            'objet' => ObjetOtp::Verification,
            'empreinte_code' => EnvoyerCodeOtp::empreinte('123456'),
            'essais' => 0,
            'expire_le' => now()->addMinutes(10),
        ];
    }
}
