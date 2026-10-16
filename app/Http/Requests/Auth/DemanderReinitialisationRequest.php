<?php

namespace App\Http\Requests\Auth;

use App\Services\TelephoneService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemanderReinitialisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pays_code' => ['required', 'string', 'size:2', Rule::exists('pays', 'code')->where('actif', true)],
            'telephone' => ['required', 'string', 'max:30', 'phone:pays_code,mobile'],
        ];
    }

    /**
     * Numéro saisi, normalisé en E.164 (la validation garantit qu'il est valide).
     */
    public function telephoneE164(): string
    {
        return (string) app(TelephoneService::class)->normaliser(
            $this->string('telephone')->toString(),
            $this->string('pays_code')->toString(),
        );
    }
}
