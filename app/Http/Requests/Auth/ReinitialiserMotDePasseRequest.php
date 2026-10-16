<?php

namespace App\Http\Requests\Auth;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ReinitialiserMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return is_string($this->session()->get(MotDePasseOublieController::CLE_SESSION));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.EnvoyerCodeOtp::LONGUEUR],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->uncompromised()],
        ];
    }
}
