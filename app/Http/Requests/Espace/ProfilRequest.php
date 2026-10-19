<?php

namespace App\Http\Requests\Espace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilRequest extends FormRequest
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
            'prenoms' => ['required', 'string', 'max:150'],
            'nom' => ['required', 'string', 'max:100'],
            'commune_id' => ['nullable', 'integer', Rule::exists('communes', 'id')->where('actif', true)],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
