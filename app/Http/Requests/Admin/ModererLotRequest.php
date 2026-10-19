<?php

namespace App\Http\Requests\Admin;

use App\Enums\MotifRejet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Actions groupées de la modération : valider ou rejeter les publications cochées.
 */
class ModererLotRequest extends RejeterPublicationRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['valider', 'rejeter'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'motif_rejet' => ['nullable', 'required_if:action,rejeter', Rule::enum(MotifRejet::class)],
            'motif_rejet_detail' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ids.required' => 'Cochez au moins une publication.'];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids', [])));
    }
}
