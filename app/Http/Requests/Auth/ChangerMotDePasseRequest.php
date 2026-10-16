<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangerMotDePasseRequest extends FormRequest
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
            'mot_de_passe_actuel' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:mot_de_passe_actuel', Password::min(8)->uncompromised()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mot_de_passe_actuel' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ];
    }
}
