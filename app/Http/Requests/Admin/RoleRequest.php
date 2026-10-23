<?php

namespace App\Http\Requests\Admin;

use App\Support\PermissionsAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
            'libelle' => [$this->isMethod('post') ? 'required' : 'prohibited', 'string', 'min:3', 'max:50'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionsAdmin::cles())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['libelle' => 'nom du rôle'];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values($this->input('permissions', []));

        return $permissions;
    }
}
