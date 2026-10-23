{{-- Actions d'un abonnement en cours : prolonger, résilier (menu « ⋯ »). --}}
@if (in_array($abonnement->statut, \App\Services\Droits::STATUTS_EN_COURS, true))
    <x-admin.menu-actions :id="'abonnement-'.$abonnement->id" libelle="Actions sur l’abonnement">
        <form method="POST" action="{{ route('admin.abonnements.prolonger', $abonnement) }}" class="flex items-center gap-2 px-2 py-1" data-confirmer="Prolonger cet abonnement ?">
            @csrf
            <label for="jours-{{ $abonnement->id }}" class="sr-only">Nombre de jours</label>
            <input id="jours-{{ $abonnement->id }}" type="number" name="jours" value="30" min="1" max="3660" class="h-9 w-20 rounded-lg border border-slate-300 px-2 text-sm">
            <button class="inline-flex min-h-9 flex-1 items-center justify-center gap-1 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-fonce">Prolonger</button>
        </form>
        <hr class="my-1 border-slate-100">
        <form method="POST" action="{{ route('admin.abonnements.resilier', $abonnement) }}" data-confirmer="Résilier cet abonnement ? Les droits s’arrêtent tout de suite." data-danger="1">
            @csrf
            <x-admin.action ton="danger"><x-admin.icone nom="fermer" class="size-4" />Résilier</x-admin.action>
        </form>
    </x-admin.menu-actions>
@endif
