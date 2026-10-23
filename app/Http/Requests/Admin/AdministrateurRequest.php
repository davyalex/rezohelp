<?php

namespace App\Http\Requests\Admin;

use App\Models\Administrateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdministrateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom_utilisateur' => mb_strtolower(trim((string) $this->input('nom_utilisateur'))),
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $admin = $this->route('administrateur');
        $id = $admin instanceof Administrateur ? $admin->id : null;

        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenoms' => ['nullable', 'string', 'max:120'],
            'nom_utilisateur' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('administrateurs', 'nom_utilisateur')->ignore($id)],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('administrateurs', 'email')->ignore($id)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', Administrateur::GARDE)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nom_utilisateur.regex' => 'Le nom d’utilisateur ne contient que des lettres minuscules sans accent, des chiffres, des points, des tirets ou des tirets bas.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nom_utilisateur' => 'nom d’utilisateur', 'prenoms' => 'prénoms', 'role' => 'rôle'];
    }

    /**
     * @return array{nom: string, prenoms: ?string, nom_utilisateur: string, email: ?string, role: string}
     */
    public function donnees(): array
    {
        return [
            'nom' => $this->string('nom')->trim()->toString(),
            'prenoms' => $this->filled('prenoms') ? $this->string('prenoms')->trim()->toString() : null,
            'nom_utilisateur' => $this->string('nom_utilisateur')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'role' => $this->string('role')->toString(),
        ];
    }
}
