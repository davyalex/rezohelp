<?php

namespace App\Http\Requests\Publication;

use App\Enums\CleDroit;
use App\Enums\ParcoursPublication;
use App\Enums\StatutPublication;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Règles communes aux quatre parcours de publication (CONTEXTE §11.4).
 * Chaque parcours ajoute ses champs dans reglesDuParcours().
 */
abstract class PublierPublicationRequest extends FormRequest
{
    public const int TAILLE_PHOTO_KO = 5120;

    /** Garde-fous techniques seulement (taille des colonnes), sans minimum : l'auteur écrit librement (D-069). */
    public const int TITRE_MAX = 255;

    public const int CONTENU_MAX = 15000;

    abstract public function parcours(): ParcoursPublication;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function reglesDuParcours(): array;

    public function authorize(): bool
    {
        $publication = $this->route('publication');
        $utilisateur = $this->user();

        if ($utilisateur === null) {
            return false;
        }

        if (! $publication instanceof Publication) {
            return $utilisateur->can('create', Publication::class);
        }

        // Publier un brouillon, c'est créer une publication : mêmes limites qu'une nouvelle.
        $publieUnBrouillon = $publication->statut === StatutPublication::Brouillon && ! $this->enBrouillon();

        return $utilisateur->can('update', $publication)
            && (! $publieUnBrouillon || $utilisateur->can('create', Publication::class));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->parcours()->type()->value;
        $droits = Droits::pour($this->titulaire());
        $photosMax = max(0, $droits->limite(CleDroit::PublicationPhotosMax));
        $dureeMax = max($droits->limite(CleDroit::PublicationDureeMaxJours), Parametre::entier('jours_expiration_defaut'));

        return array_merge([
            'action' => ['nullable', Rule::in(['publier', 'brouillon'])],
            'titre' => ['required', 'string', 'max:'.self::TITRE_MAX],
            'categorie_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', $type)->where('actif', true)],
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')->where('actif', true)],
            'contenu' => ['required', 'string', 'max:'.self::CONTENU_MAX],
            'photos' => ['nullable', 'array', 'max:'.$photosMax],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::TAILLE_PHOTO_KO],
            'expire_le' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addDays($dureeMax)->toDateString()],
        ], $this->reglesDuParcours());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categorie_id' => 'catégorie',
            'commune_id' => 'commune',
            'photos' => 'photos',
            'photos.*' => 'photo',
            'entreprise_id' => 'entreprise',
            'entreprise_nom' => 'nom de l’entreprise',
            'type_contrat' => 'type de contrat',
            'nb_postes' => 'nombre de postes',
            'salaire_min' => 'salaire minimum',
            'salaire_max' => 'salaire maximum',
            'date_limite' => 'date limite',
            'mode_candidature' => 'mode de candidature',
            'contact_candidature' => 'contact de candidature',
            'tarif_indicatif' => 'tarif indicatif',
            'unite_tarif' => 'unité du tarif',
            'zones_intervention' => 'zones d’intervention',
            'telephone_contact' => 'téléphone de contact',
            'date_souhaitee' => 'date souhaitée',
            'modalite' => 'modalité',
            'date_debut' => 'date de début',
            'mode_inscription' => 'mode d’inscription',
            'contact_inscription' => 'contact d’inscription',
            'organisme' => 'organisme',
        ];
    }

    public function enBrouillon(): bool
    {
        return $this->input('action') === 'brouillon';
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        $photos = $this->file('photos', []);

        return array_values(is_array($photos) ? $photos : [$photos]);
    }

    public function membre(): Utilisateur
    {
        $utilisateur = $this->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }

    /**
     * Titulaire des droits pour ce formulaire (§14.5) : l'entreprise choisie,
     * une entreprise à créer (plan par défaut), sinon le membre lui-même.
     */
    public function titulaire(): Utilisateur|Entreprise
    {
        $choix = $this->input('employeur', $this->input('organisateur'));

        if ($choix === 'entreprise' && $this->filled('entreprise_id')) {
            $entreprise = $this->membre()->entreprises()->whereKey($this->integer('entreprise_id'))->first();
            if ($entreprise !== null) {
                return $entreprise;
            }
        }

        return $choix === 'nouvelle' ? new Entreprise : $this->membre();
    }

    /**
     * Règles d'un employeur ou organisme : une de mes entreprises, une nouvelle, ou autre choix.
     *
     * @param  list<string>  $choix
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglesEntreprise(string $champ, array $choix): array
    {
        return [
            $champ => ['required', Rule::in($choix)],
            'entreprise_id' => [
                "required_if:{$champ},entreprise", 'nullable', 'integer',
                Rule::exists('entreprises', 'id')->where('proprietaire_id', $this->user()?->getAuthIdentifier())->whereNull('supprime_le'),
            ],
            'entreprise_nom' => ["required_if:{$champ},nouvelle", 'nullable', 'string', 'min:2', 'max:150'],
            'entreprise_secteur' => ['nullable', 'string', 'max:100'],
            'entreprise_commune_id' => ['nullable', 'integer', Rule::exists('communes', 'id')->where('actif', true)],
            'entreprise_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Contact selon le mode : e-mail, lien http(s) ou numéro de téléphone mobile.
     *
     * @return list<mixed>
     */
    protected function reglesContact(string $champMode): array
    {
        $pays = $this->membre()->pays_code;

        return [
            "required_unless:{$champMode},rezohelp",
            'nullable',
            'string',
            'max:255',
            Rule::when($this->input($champMode) === 'email', ['email:rfc']),
            Rule::when($this->input($champMode) === 'lien', ['url:http,https']),
            Rule::when($this->input($champMode) === 'telephone', ["phone:{$pays},INTERNATIONAL"]),
        ];
    }
}
