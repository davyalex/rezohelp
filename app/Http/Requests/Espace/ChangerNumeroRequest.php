<?php

namespace App\Http\Requests\Espace;

use App\Rules\TelephoneDisponible;
use App\Services\TelephoneService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangerNumeroRequest extends FormRequest
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
            'pays_code' => ['required', 'string', 'size:2', Rule::exists('pays', 'code')->where('actif', true)],
            'telephone' => ['required', 'string', 'max:30', 'phone:pays_code,mobile', new TelephoneDisponible],
        ];
    }

    public function telephoneE164(): string
    {
        return (string) app(TelephoneService::class)->normaliser(
            $this->string('telephone')->toString(),
            $this->string('pays_code')->toString(),
        );
    }
}
