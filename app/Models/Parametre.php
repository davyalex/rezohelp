<?php

namespace App\Models;

use App\Enums\TypeParametre;
use App\Observers\ReferentielObserver;
use App\Services\CacheFil;
use Database\Factories\ParametreFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Paramètre clé / valeur réglable dans l'admin (§13.G). Lecture via Parametre::valeur()
 * (cache « ref:parametres »), jamais de valeur en dur dans le code.
 */
#[ObservedBy(ReferentielObserver::class)]
class Parametre extends Model
{
    /** @use HasFactory<ParametreFactory> */
    use HasFactory;

    protected $table = 'parametres';

    protected $primaryKey = 'cle';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['cle', 'valeur', 'type', 'libelle', 'modifie_par'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeParametre::class,
        ];
    }

    public function getCreatedAtColumn(): ?string
    {
        return null;
    }

    public function getUpdatedAtColumn(): ?string
    {
        return 'modifie_le';
    }

    /**
     * @return BelongsTo<Administrateur, $this>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'modifie_par');
    }

    /**
     * Valeur convertie selon le type du paramètre.
     */
    public function valeurTypee(): mixed
    {
        return match ($this->type) {
            TypeParametre::Entier => (int) $this->valeur,
            TypeParametre::Booleen => filter_var($this->valeur, FILTER_VALIDATE_BOOLEAN),
            TypeParametre::Json => json_decode((string) $this->valeur, true) ?? [],
            TypeParametre::Texte, TypeParametre::Html => (string) $this->valeur,
        };
    }

    public static function valeur(string $cle): mixed
    {
        $valeurs = app(CacheFil::class)->referentiel('parametres', fn (): array => self::query()
            ->get()
            ->mapWithKeys(fn (Parametre $parametre): array => [$parametre->cle => $parametre->valeurTypee()])
            ->all());

        if (! array_key_exists($cle, $valeurs)) {
            throw new InvalidArgumentException("Paramètre inconnu : {$cle}.");
        }

        return $valeurs[$cle];
    }

    public static function entier(string $cle): int
    {
        $valeur = self::valeur($cle);

        return is_numeric($valeur) ? (int) $valeur : 0;
    }

    public static function booleen(string $cle): bool
    {
        return (bool) self::valeur($cle);
    }

    public static function texte(string $cle): string
    {
        $valeur = self::valeur($cle);

        return is_scalar($valeur) ? (string) $valeur : '';
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function liste(string $cle): array
    {
        $valeur = self::valeur($cle);

        return is_array($valeur) ? $valeur : [];
    }
}
