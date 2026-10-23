<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConnexionAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom_utilisateur' => ['required', 'string', 'max:50'],
            'mot_de_passe' => ['required', 'string', 'max:255'],
            'se_souvenir' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nom_utilisateur' => 'nom d’utilisateur', 'mot_de_passe' => 'mot de passe'];
    }
}
