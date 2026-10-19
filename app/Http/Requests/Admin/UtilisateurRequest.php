<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un compte par un administrateur (le numéro se change par l'OTP du membre).
 */
class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('utilisateurs.gerer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenoms' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'commune_id' => ['nullable', 'integer', Rule::exists('communes', 'id')],
        ];
    }

    /**
     * @return array{nom: string, prenoms: ?string, email: ?string, commune_id: ?int}
     */
    public function donnees(): array
    {
        return [
            'nom' => trim($this->string('nom')->toString()),
            'prenoms' => $this->filled('prenoms') ? trim($this->string('prenoms')->toString()) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim($this->string('email')->toString())) : null,
            'commune_id' => $this->filled('commune_id') ? $this->integer('commune_id') : null,
        ];
    }
}
