<?php

namespace App\View\Components;

use App\Models\Pays;
use App\Services\CacheFil;
use App\Services\TelephoneService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Sélecteur de pays (drapeau + indicatif) suivi du numéro au format national.
 * Seuls les pays actifs sont proposés ; la Côte d'Ivoire est choisie par défaut.
 */
class ChampTelephone extends Component
{
    /**
     * @var list<array{code: string, nom: string, indicatif: string, drapeau: string}>
     */
    public array $pays;

    public function __construct(
        CacheFil $cache,
        public string $name = 'telephone',
        public string $nomPays = 'pays_code',
        public string $label = 'Numéro de téléphone',
        public ?string $valeur = null,
        public ?string $paysSelectionne = null,
        public ?string $aide = 'Votre numéro ne sera jamais affiché publiquement.',
    ) {
        $this->paysSelectionne ??= TelephoneService::PAYS_PAR_DEFAUT;

        /** @var list<array{code: string, nom: string, indicatif: string}> $actifs */
        $actifs = $cache->referentiel('pays', fn (): array => Pays::query()->actifs()->get(['code', 'nom', 'indicatif'])
            ->map(fn (Pays $pays): array => ['code' => $pays->code, 'nom' => $pays->nom, 'indicatif' => $pays->indicatif])
            ->all());

        $this->pays = array_map(fn (array $pays): array => [...$pays, 'drapeau' => self::drapeau($pays['code'])], $actifs);
    }

    /**
     * Drapeau emoji à partir du code ISO (indicateurs régionaux Unicode).
     */
    public static function drapeau(string $code): string
    {
        return implode('', array_map(
            fn (string $lettre): string => (string) mb_chr(0x1F1E6 + ord($lettre) - ord('A')),
            str_split(strtoupper($code)),
        ));
    }

    public function render(): View
    {
        return view('components.champ-telephone');
    }
}
