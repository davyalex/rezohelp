<?php

namespace App\Http\Requests\Admin;

use App\Enums\EmplacementPublicite;
use App\Models\Publicite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Publicité (D-074). Les images doivent avoir au moins la taille de leur format et ses proportions
 * (à 5 % près) : une bannière déformée ou floue ne passe pas.
 */
class PubliciteRequest extends FormRequest
{
    /** Écart toléré sur les proportions d'une image. */
    private const float TOLERANCE = 0.05;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creation = ! $this->route('publicite') instanceof Publicite;
        $image = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'];

        return [
            'titre' => ['required', 'string', 'max:120'],
            'annonceur' => ['required', 'string', 'max:120'],
            'lien' => ['required', 'string', 'max:500', 'url:https'],
            'texte_alternatif' => ['required', 'string', 'max:200'],
            'emplacement' => ['required', Rule::enum(EmplacementPublicite::class)],
            'debut_le' => ['nullable', 'date'],
            'fin_le' => ['nullable', 'date', 'after_or_equal:debut_le'],
            'active' => ['nullable', 'boolean'],
            'poids' => ['required', 'integer', 'between:1,10'],
            'image_principale' => [$creation ? 'required' : 'nullable', ...$image],
            'image_secondaire' => ['nullable', ...$image],
            'retirer_image_secondaire' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validateur): void {
            $emplacement = EmplacementPublicite::tryFrom((string) $this->input('emplacement'));
            if ($emplacement === null) {
                return;
            }

            $this->verifierFormat($validateur, 'image_principale', $emplacement->formatPrincipal());

            $secondaire = $emplacement->formatSecondaire();
            if ($secondaire === null && $this->hasFile('image_secondaire')) {
                $validateur->errors()->add('image_secondaire', 'Cet emplacement n’utilise pas d’image secondaire.');
            } elseif ($secondaire !== null) {
                $this->verifierFormat($validateur, 'image_secondaire', $secondaire);
            }

            // Changer d'emplacement change le format attendu : il faut une nouvelle image.
            $publicite = $this->route('publicite');
            if ($publicite instanceof Publicite && $publicite->emplacement !== $emplacement && ! $this->hasFile('image_principale')) {
                $validateur->errors()->add('image_principale', 'Le format change avec l’emplacement : envoyez une image au nouveau format.');
            }
        }];
    }

    /**
     * @param  array{largeur: int, hauteur: int}  $format
     */
    private function verifierFormat(Validator $validateur, string $champ, array $format): void
    {
        $fichier = $this->file($champ);
        if (! $fichier instanceof UploadedFile || $validateur->errors()->has($champ)) {
            return;
        }

        $taille = @getimagesize($fichier->getRealPath());
        if ($taille === false) {
            return;
        }

        [$largeur, $hauteur] = $taille;
        $attendu = $format['largeur'] / $format['hauteur'];
        $message = "L’image doit mesurer au moins {$format['largeur']} × {$format['hauteur']} px, dans ces proportions (reçu : {$largeur} × {$hauteur}).";

        if ($largeur < $format['largeur'] || $hauteur < $format['hauteur'] || abs(($largeur / $hauteur) - $attendu) / $attendu > self::TOLERANCE) {
            $validateur->errors()->add($champ, $message);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'texte_alternatif' => 'description de l’image',
            'debut_le' => 'date de début',
            'fin_le' => 'date de fin',
            'image_principale' => 'image principale',
            'image_secondaire' => 'image secondaire',
        ];
    }

    /**
     * @return array{titre: string, annonceur: string, lien: string, texte_alternatif: string, emplacement: string, debut_le: ?string, fin_le: ?string, active: bool, poids: int}
     */
    public function donnees(): array
    {
        return [
            'titre' => $this->string('titre')->trim()->toString(),
            'annonceur' => $this->string('annonceur')->trim()->toString(),
            'lien' => $this->string('lien')->trim()->toString(),
            'texte_alternatif' => $this->string('texte_alternatif')->trim()->toString(),
            'emplacement' => $this->string('emplacement')->toString(),
            'debut_le' => $this->filled('debut_le') ? $this->string('debut_le')->toString() : null,
            'fin_le' => $this->filled('fin_le') ? $this->string('fin_le')->toString() : null,
            'active' => $this->boolean('active'),
            'poids' => $this->integer('poids'),
        ];
    }
}
