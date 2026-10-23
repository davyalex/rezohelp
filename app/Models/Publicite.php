<?php

namespace App\Models;

use App\Enums\EmplacementPublicite;
use App\Models\Concerns\HorodatageFrancais;
use App\Observers\ReferentielObserver;
use Database\Factories\PubliciteFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bannière publicitaire d'un emplacement du site (D-074).
 * Images : « principale » (format de l'emplacement, obligatoire) et « secondaire » (facultative),
 * converties en WebP sans agrandissement.
 *
 * @property int $id
 * @property string $titre
 * @property string $annonceur
 * @property string $lien
 * @property string $texte_alternatif
 * @property EmplacementPublicite $emplacement
 * @property ?Carbon $debut_le
 * @property ?Carbon $fin_le
 * @property bool $active
 * @property int $poids
 * @property int $nb_impressions
 * @property int $nb_clics
 */
#[ObservedBy(ReferentielObserver::class)]
class Publicite extends Model implements HasMedia
{
    /** @use HasFactory<PubliciteFactory> */
    use HasFactory, HorodatageFrancais, InteractsWithMedia, SoftDeletes;

    public const string IMAGE_PRINCIPALE = 'principale';

    public const string IMAGE_SECONDAIRE = 'secondaire';

    protected $table = 'publicites';

    /**
     * @var list<string>
     */
    protected $fillable = ['titre', 'annonceur', 'lien', 'texte_alternatif', 'emplacement', 'debut_le', 'fin_le', 'active', 'poids'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emplacement' => EmplacementPublicite::class,
            'debut_le' => 'date',
            'fin_le' => 'date',
            'active' => 'boolean',
            'poids' => 'integer',
            'nb_impressions' => 'integer',
            'nb_clics' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        foreach ([self::IMAGE_PRINCIPALE, self::IMAGE_SECONDAIRE] as $collection) {
            $this->addMediaCollection($collection)
                ->singleFile()
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Converties tout de suite : l'URL doit exister dès l'enregistrement dans l'admin.
        $this->addMediaConversion('web')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Max, 1200, 1200);
    }

    /**
     * Adresse de l'image relative au site (/storage/…) : elle fonctionne quelle que soit l'adresse
     * utilisée pour ouvrir le site (APP_URL, domaine local, IP), et reste autorisée par la CSP (img-src 'self').
     */
    public function urlImage(string $collection): ?string
    {
        $media = $this->getFirstMedia($collection);

        if ($media === null) {
            return null;
        }

        $url = $media->getUrl($media->hasGeneratedConversion('web') ? 'web' : '');
        $chemin = parse_url($url, PHP_URL_PATH);

        return is_string($chemin) && $chemin !== '' ? $chemin : $url;
    }

    /**
     * État affiché dans l'admin : inactive, programmée, terminée ou en cours.
     */
    public function etat(?Carbon $aujourdhui = null): string
    {
        $aujourdhui ??= Carbon::today();

        return match (true) {
            ! $this->active => 'inactive',
            $this->debut_le !== null && $this->debut_le->gt($aujourdhui) => 'programmee',
            $this->fin_le !== null && $this->fin_le->lt($aujourdhui) => 'terminee',
            default => 'en_cours',
        };
    }

    public function tauxDeClic(): ?float
    {
        return $this->nb_impressions > 0 ? round($this->nb_clics / $this->nb_impressions * 100, 2) : null;
    }
}
