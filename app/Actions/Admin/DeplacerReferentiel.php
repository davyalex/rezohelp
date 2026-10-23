<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Models\Administrateur;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Pays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ordre d'affichage d'un référentiel (CONTEXTE §13.3.G) : échange avec le voisin du dessus ou du
 * dessous, dans le même groupe (même type et même parent pour une catégorie, même ville pour une commune).
 */
class DeplacerReferentiel
{
    use JournaliseActivite;

    public function executer(Categorie|Commune|Pays $element, string $sens, Administrateur $admin): void
    {
        $voisin = $this->groupe($element)
            ->whereKeyNot($element->getKey())
            ->where(fn (Builder $r) => $sens === 'haut'
                ? $r->where('ordre', '<', $element->ordre)->orWhere(fn (Builder $q) => $q->where('ordre', $element->ordre)->where($element->getKeyName(), '<', $element->getKey()))
                : $r->where('ordre', '>', $element->ordre)->orWhere(fn (Builder $q) => $q->where('ordre', $element->ordre)->where($element->getKeyName(), '>', $element->getKey())))
            ->orderBy('ordre', $sens === 'haut' ? 'desc' : 'asc')
            ->orderBy($element->getKeyName(), $sens === 'haut' ? 'desc' : 'asc')
            ->first();

        if ($voisin === null) {
            return;
        }

        DB::transaction(function () use ($element, $voisin, $sens, $admin): void {
            [$ordreElement, $ordreVoisin] = [$voisin->ordre, $element->ordre];

            // Ordres égaux : on les départage pour que l'échange soit visible.
            if ($ordreElement === $ordreVoisin) {
                $ordreElement += $sens === 'haut' ? -1 : 1;
            }

            $element->ordre = $ordreElement;
            $element->save();
            $voisin->ordre = $ordreVoisin;
            $voisin->save();

            $this->journaliser($admin, $element->getTable().'.deplace', $element, ['sens' => $sens]);
        });
    }

    /**
     * @return Builder<Categorie>|Builder<Commune>|Builder<Pays>
     */
    private function groupe(Categorie|Commune|Pays $element): Builder
    {
        return match (true) {
            $element instanceof Categorie => Categorie::query()->where('type', $element->type->value)
                ->when($element->parent_id === null, fn (Builder $r) => $r->whereNull('parent_id'), fn (Builder $r) => $r->where('parent_id', $element->parent_id)),
            $element instanceof Commune => Commune::query()->where('pays_code', $element->pays_code)->where('ville', $element->ville),
            default => Pays::query(),
        };
    }
}
