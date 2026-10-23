<?php

namespace App\View\Components;

use App\Enums\SourcePublication;
use App\Models\Publication;
use App\Services\Referentiels;
use App\Support\JetonsCarte;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Carte d'une publication, identique dans le fil, l'aperçu avant publication
 * et la modération. Produit un gabarit à jetons (voir RenduCarte) qui ne lit que
 * les colonnes dénormalisées de publications.
 */
class CartePublication extends Component
{
    public string $titre;

    public string $extrait;

    public string $auteur;

    public ?string $commune;

    public ?string $categorie;

    /**
     * @var list<string>
     */
    public array $faits;

    public function __construct(Referentiels $referentiels, public Publication $publication)
    {
        $this->titre = JetonsCarte::neutraliser($publication->titre);
        $this->extrait = JetonsCarte::neutraliser($publication->extrait);
        $this->auteur = JetonsCarte::neutraliser($publication->auteur_nom_affiche ?? 'Membre Rezohelp');
        $this->commune = $referentiels->nomCommune($publication->commune_id);
        $this->categorie = $referentiels->nomCategorie($publication->categorie_id);
        $this->faits = array_map(JetonsCarte::neutraliser(...), $publication->faits_cles ?? []);
    }

    public function estVeille(): bool
    {
        return $this->publication->source === SourcePublication::VeilleIa;
    }

    public function initiale(): string
    {
        return mb_strtoupper(mb_substr($this->auteur, 0, 1));
    }

    public function render(): View
    {
        return view('components.carte-publication');
    }
}
