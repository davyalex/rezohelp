{{-- Achat d'un produit de visibilité, en entrée du menu « Actions » : seul le code est transmis, le prix vient de la base. --}}
<li>
    <form method="POST" action="{{ route('commandes.store') }}">
        @csrf
        <input type="hidden" name="article_type" value="produit">
        <input type="hidden" name="code" value="{{ $code }}">
        <input type="hidden" name="publication_id" value="{{ $publication->id }}">
        @if ($publication->entreprise_id)
            <input type="hidden" name="entreprise_id" value="{{ $publication->entreprise_id }}">
        @endif
        <button type="submit" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50">
            <span aria-hidden="true" class="w-5 text-center">{{ $libelle }}</span>{{ $texte }}
        </button>
    </form>
</li>
