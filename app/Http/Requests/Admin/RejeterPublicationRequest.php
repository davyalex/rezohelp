<?php

namespace App\Http\Requests\Admin;

use App\Enums\MotifRejet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rejet d'une publication, à l'unité ou en lot : motif prédéfini, texte libre obligatoire pour « autre ».
 */
class RejeterPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publications.moderer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif_rejet' => ['required', Rule::enum(MotifRejet::class)],
            'motif_rejet_detail' => ['nullable', 'string', 'max:500', Rule::requiredIf($this->input('motif_rejet') === MotifRejet::Autre->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motif_rejet' => 'motif', 'motif_rejet_detail' => 'précision'];
    }

    public function motif(): MotifRejet
    {
        return $this->enum('motif_rejet', MotifRejet::class) ?? MotifRejet::Autre;
    }

    public function detail(): ?string
    {
        return $this->filled('motif_rejet_detail') ? $this->string('motif_rejet_detail')->toString() : null;
    }
}
