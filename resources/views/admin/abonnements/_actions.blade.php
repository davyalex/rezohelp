{{-- Actions d'un abonnement en cours : prolonger, résilier. --}}
@if (in_array($abonnement->statut, \App\Services\Droits::STATUTS_EN_COURS, true))
    <div class="flex flex-wrap items-center gap-1">
        <form method="POST" action="{{ route('admin.abonnements.prolonger', $abonnement) }}" class="flex items-center gap-1" data-confirmer="Prolonger cet abonnement ?">
            @csrf
            <label for="jours-{{ $abonnement->id }}" class="sr-only">Jours</label>
            <input id="jours-{{ $abonnement->id }}" type="number" name="jours" value="30" min="1" max="3660" class="h-8 w-16 rounded border border-slate-300 px-1 text-xs">
            <button class="rounded px-2 py-1 text-xs font-semibold text-brand hover:bg-slate-100">+ jours</button>
        </form>
        <form method="POST" action="{{ route('admin.abonnements.resilier', $abonnement) }}" data-confirmer="Résilier cet abonnement ? Les droits s’arrêtent tout de suite." data-danger="1">
            @csrf
            <button class="rounded px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Résilier</button>
        </form>
    </div>
@endif
