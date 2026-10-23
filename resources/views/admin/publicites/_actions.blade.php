{{-- Actions d'une publicité (menu « ⋯ »). --}}
<x-admin.menu-actions :id="'publicite-'.$publicite->id" :libelle="'Actions pour « '.$publicite->titre.' »'">
    <x-admin.action :href="route('admin.publicites.edit', $publicite)">
        <x-admin.icone nom="crayon" class="size-4" />Modifier
    </x-admin.action>
    <form method="POST" action="{{ route('admin.publicites.basculer', $publicite) }}">
        @csrf
        @if ($publicite->active)
            <x-admin.action ton="attention"><x-admin.icone nom="pause" class="size-4" />Désactiver</x-admin.action>
        @else
            <x-admin.action><x-admin.icone nom="lecture" class="size-4" />Activer</x-admin.action>
        @endif
    </form>
    <hr class="my-1 border-slate-100">
    <form method="POST" action="{{ route('admin.publicites.destroy', $publicite) }}" data-confirmer="Supprimer la publicité « {{ $publicite->titre }} » ? Elle ne sera plus affichée." data-danger="1">
        @csrf
        @method('DELETE')
        <x-admin.action ton="danger"><x-admin.icone nom="corbeille" class="size-4" />Supprimer</x-admin.action>
    </form>
</x-admin.menu-actions>
