<?php

namespace App\Http\Requests\Auth;

use App\Actions\Otp\EnvoyerCodeOtp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifierTelephoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.EnvoyerCodeOtp::LONGUEUR],
        ];
    }
}
