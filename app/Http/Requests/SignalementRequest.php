<?php

namespace App\Http\Requests;

use App\Enums\MotifSignalement;
use App\Models\Publication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $publication = $this->route('publication');

        return $publication instanceof Publication && ($this->user()?->can('interagir', $publication) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif' => ['required', Rule::enum(MotifSignalement::class)],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function motif(): MotifSignalement
    {
        return MotifSignalement::from($this->string('motif')->toString());
    }
}
