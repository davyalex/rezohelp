{{-- Actions d'une ligne de la liste des publications (menu « ⋯ »). --}}
@php($statut = $publication->statut)
@php($S = \App\Enums\StatutPublication::class)
<x-admin.menu-actions :id="'publication-'.$publication->id" :libelle="'Actions pour « '.$publication->titre.' »'">
    @if (in_array($statut, [$S::Publiee, $S::Cloturee, $S::Expiree], true))
        <x-admin.action :href="route('publications.show', $publication->slug)" externe>
            <x-admin.icone nom="site" class="size-4" />Voir sur le site
        </x-admin.action>
    @endif
    <x-admin.action :href="route('admin.publications.edit', $publication)">
        <x-admin.icone nom="document" class="size-4" />Modifier
    </x-admin.action>
    @if ($statut === $S::EnAttente)
        <x-admin.action :href="route('admin.moderation.show', $publication)" ton="attention">
            <x-admin.icone nom="bouclier" class="size-4" />Modérer
        </x-admin.action>
    @endif
    @if ($statut === $S::Publiee)
        <form method="POST" action="{{ route('admin.publications.depublier', $publication) }}" data-confirmer="Retirer cette publication du fil ? Elle repassera en modération.">
            @csrf
            <x-admin.action ton="attention"><x-admin.icone nom="import" class="size-4 rotate-180" />Dépublier</x-admin.action>
        </form>
    @endif
    @if (in_array($statut, [$S::Publiee, $S::Expiree], true))
        <form method="POST" action="{{ route('admin.publications.prolonger', $publication) }}" data-confirmer="Prolonger cette publication de {{ \App\Models\Parametre::entier('jours_prolongation') }} jours ?">
            @csrf
            <x-admin.action><x-admin.icone nom="horloge" class="size-4" />Prolonger</x-admin.action>
        </form>
    @endif
    <hr class="my-1 border-slate-100">
    <form method="POST" action="{{ route('admin.publications.destroy', $publication) }}" data-confirmer="Supprimer cette publication ?" data-danger="1">
        @csrf
        @method('DELETE')
        <x-admin.action ton="danger"><x-admin.icone nom="corbeille" class="size-4" />Supprimer</x-admin.action>
    </form>
</x-admin.menu-actions>
