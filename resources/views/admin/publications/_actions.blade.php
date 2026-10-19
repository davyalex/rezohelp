{{-- Actions d'une ligne de la liste des publications. --}}
<div class="flex flex-wrap gap-1">
    @if (in_array($publication->statut, [\App\Enums\StatutPublication::Publiee, \App\Enums\StatutPublication::Cloturee, \App\Enums\StatutPublication::Expiree], true))
        <a href="{{ route('publications.show', $publication->slug) }}" target="_blank" rel="noopener" class="rounded px-2 py-1 text-xs font-semibold text-brand hover:bg-slate-100">Voir</a>
    @endif
    <a href="{{ route('admin.publications.edit', $publication) }}" class="rounded px-2 py-1 text-xs font-semibold text-brand hover:bg-slate-100">Modifier</a>
    @if ($publication->statut === \App\Enums\StatutPublication::EnAttente)
        <a href="{{ route('admin.moderation.show', $publication) }}" class="rounded px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-slate-100">Modérer</a>
    @endif
    @if ($publication->statut === \App\Enums\StatutPublication::Publiee)
        <form method="POST" action="{{ route('admin.publications.depublier', $publication) }}" data-confirmer="Retirer cette publication du fil ? Elle repassera en modération.">
            @csrf
            <button class="rounded px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-slate-100">Dépublier</button>
        </form>
    @endif
    @if (in_array($publication->statut, [\App\Enums\StatutPublication::Publiee, \App\Enums\StatutPublication::Expiree], true))
        <form method="POST" action="{{ route('admin.publications.prolonger', $publication) }}" data-confirmer="Prolonger cette publication de {{ \App\Models\Parametre::entier('jours_prolongation') }} jours ?">
            @csrf
            <button class="rounded px-2 py-1 text-xs font-semibold text-brand hover:bg-slate-100">Prolonger</button>
        </form>
    @endif
    <form method="POST" action="{{ route('admin.publications.destroy', $publication) }}" data-confirmer="Supprimer cette publication ?" data-danger="1">
        @csrf
        @method('DELETE')
        <button class="rounded px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
    </form>
</div>
