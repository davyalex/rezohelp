<?php

namespace App\Jobs;

use App\Enums\CleDroit;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Badge « Pro » (artisan) ou « Entreprise » sur les cartes d'un titulaire qui a le droit
 * profil.badge (§14.3). Dénormalisé sur publications ; chaque mise à jour passe par l'observer.
 */
class RafraichirBadges implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $titulaireType, public readonly int $titulaireId) {}

    public function handle(): void
    {
        $titulaire = match ($this->titulaireType) {
            'entreprise' => Entreprise::query()->find($this->titulaireId),
            'utilisateur' => Utilisateur::query()->find($this->titulaireId),
            default => null,
        };

        if ($titulaire === null) {
            return;
        }

        $badge = self::badge($titulaire);

        Publication::query()
            ->when($titulaire instanceof Entreprise,
                fn ($requete) => $requete->where('entreprise_id', $titulaire->getKey()),
                fn ($requete) => $requete->where('auteur_id', $titulaire->getKey())->whereNull('entreprise_id'))
            ->where(fn ($requete) => $badge === null ? $requete->whereNotNull('auteur_badge') : $requete->whereNull('auteur_badge')->orWhere('auteur_badge', '!=', $badge))
            ->lazyById(200)
            ->each(function (Publication $publication) use ($badge): void {
                $publication->auteur_badge = $badge;
                $publication->save();
            });
    }

    public static function badge(Utilisateur|Entreprise $titulaire): ?string
    {
        if (! Droits::pour($titulaire)->peut(CleDroit::ProfilBadge)) {
            return null;
        }

        return $titulaire instanceof Entreprise ? 'entreprise' : 'pro';
    }
}
