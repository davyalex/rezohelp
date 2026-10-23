<div class="rounded-xl bg-fond px-3 py-2">
    <p class="flex items-baseline justify-between gap-2">
        <span class="font-semibold text-slate-900">{{ $commentaire->utilisateur?->nomAffiche() ?? 'Utilisateur supprimé' }}</span>
        <span class="text-xs text-slate-600">{{ $commentaire->cree_le?->diffForHumans(['short' => true]) }}</span>
    </p>
    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $commentaire->contenu }}</p>
    @if (auth('web')->user()?->can('delete', $commentaire) || auth('admin')->user()?->can('delete', $commentaire))
        <form method="POST" action="{{ route('commentaires.destroy', $commentaire->id) }}" class="mt-1">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex min-h-11 items-center text-xs font-semibold text-red-700 hover:underline">Supprimer</button>
        </form>
    @endif
</div>
