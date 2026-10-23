<?php

namespace App\Http\Requests\Admin;

use App\Models\Administrateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class MotDePasseAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user(Administrateur::GARDE) instanceof Administrateur;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => ['required', 'string', 'current_password:'.Administrateur::GARDE],
            'mot_de_passe' => ['required', 'string', 'confirmed', 'different:mot_de_passe_actuel', Password::min(8)->letters()->numbers()->uncompromised()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['mot_de_passe_actuel' => 'mot de passe actuel', 'mot_de_passe' => 'nouveau mot de passe'];
    }
}
