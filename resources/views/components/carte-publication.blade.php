@php
    use App\Support\JetonsCarte;
    $p = $publication;
    $lien = $p->exists ? route('publications.show', $p->slug) : '#';
    $idTitre = 'carte-'.($p->id ?? 'apercu').'-titre';
@endphp
<article class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70" aria-labelledby="{{ $idTitre }}">
    {{-- En-tête : avatar, auteur, date relative, commune, badge, menu --}}
    <header class="flex items-start gap-3 px-4 pt-4">
        @if ($p->auteur_avatar_url)
            <img src="{{ $p->auteur_avatar_url }}" alt="" width="40" height="40" loading="lazy" class="size-10 shrink-0 rounded-full object-cover">
        @else
            <span aria-hidden="true" @class([
                'flex size-10 shrink-0 items-center justify-center rounded-full text-base font-bold text-white',
                'bg-emploi' => $p->type === \App\Enums\TypePublication::Emploi,
                'bg-service' => $p->type === \App\Enums\TypePublication::Service,
                'bg-formation' => $p->type === \App\Enums\TypePublication::Formation,
            ])>{{ $initiale() }}</span>
        @endif

        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-1 truncate font-semibold text-slate-900">
                <span class="truncate">{{ $auteur }}</span>
                @if ($p->auteur_verifie)
                    <span class="text-brand" title="Entreprise vérifiée"><span aria-hidden="true">✔</span><span class="sr-only">Entreprise vérifiée</span></span>
                @endif
                @if ($p->auteur_badge)
                    <span class="shrink-0 rounded bg-slate-900 px-1.5 text-[11px] font-bold uppercase leading-5 text-white">{{ $p->auteur_badge === 'entreprise' ? 'Entreprise' : 'Pro' }}</span>
                @endif
            </p>
            <p class="truncate text-sm text-slate-600">
                {{ JetonsCarte::ETIQUETTE }}{{ JetonsCarte::DEPUIS }}@if ($commune) · {{ $commune }}@endif
            </p>
        </div>

        <x-badge-type :type="$p->type" :sous-type="$p->sous_type" />

        <details class="relative">
            <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full text-xl text-slate-600 hover:bg-slate-100" aria-label="Plus d’actions">…</summary>
            <div class="absolute right-0 z-10 mt-1 w-48 rounded-xl bg-white p-1 shadow-lg ring-1 ring-slate-200">
                <form method="POST" action="{{ $p->exists ? route('publications.enregistrer', $p->id) : '#' }}"
                      x-data="bascule({{ JetonsCarte::ENREGISTRE }})" @submit.prevent="envoyer($el)">
                    <input type="hidden" name="_token" value="{{ JetonsCarte::CSRF }}">
                    <button type="submit" class="group flex min-h-11 w-full items-center gap-2 rounded-lg px-3 text-left text-sm hover:bg-slate-50"
                            aria-pressed="{{ JetonsCarte::ENREGISTRE }}" :aria-pressed="actif.toString()">
                        <span aria-hidden="true">🔖</span>
                        <span class="group-aria-pressed:hidden">Enregistrer</span>
                        <span class="hidden group-aria-pressed:inline">Enregistré</span>
                    </button>
                </form>
                <a href="{{ $lien }}#signaler" class="flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm hover:bg-slate-50">
                    <span aria-hidden="true">🚩</span> Signaler
                </a>
            </div>
        </details>
    </header>

    {{-- Corps --}}
    <div class="px-4 pt-3">
        <h2 id="{{ $idTitre }}" class="text-lg font-bold leading-snug text-slate-900">
            <a href="{{ $lien }}" class="hover:underline">{{ $titre }}</a>
        </h2>
        @if ($categorie)
            <p class="mt-0.5 text-sm text-slate-600">{{ $categorie }}</p>
        @endif
        <p class="mt-2 line-clamp-4 text-slate-800">{{ $extrait }}</p>

        @if ($faits !== [])
            <ul class="mt-3 flex flex-wrap gap-2" aria-label="Informations clés">
                @foreach ($faits as $fait)
                    <li class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-800">{{ $fait }}</li>
                @endforeach
            </ul>
        @endif

        @if ($estVeille() && $p->source_nom)
            <p class="mt-3 text-sm text-slate-600">via {{ \App\Support\JetonsCarte::neutraliser($p->source_nom) }}</p>
        @endif

        {{ JetonsCarte::PREVENTION }}
    </div>

    @if ($p->image_url)
        <img src="{{ $p->image_url }}" alt="{{ $titre }}" width="{{ $p->image_largeur ?? 640 }}" height="{{ $p->image_hauteur ?? 360 }}"
             loading="lazy" decoding="async" class="mt-3 h-auto w-full object-cover">
    @endif

    {{-- Compteurs et actions --}}
    <footer class="px-2 pb-2 pt-3" x-data="reaction({{ JetonsCarte::AIME }}, {{ JetonsCarte::NB_JAIME }})">
        <p class="flex gap-3 px-2 pb-2 text-sm text-slate-600">
            <span><span aria-hidden="true">👍</span> <span x-text="nb">{{ JetonsCarte::NB_JAIME }}</span><span class="sr-only"> J’aime</span></span>
            <span>·</span>
            <a href="{{ $lien }}#commentaires" class="hover:underline">{{ JetonsCarte::NB_COMMENTAIRES }} commentaire(s)</a>
        </p>
        <div class="flex items-center gap-1 border-t border-slate-100 pt-2">
            <form method="POST" action="{{ $p->exists ? route('publications.jaime', $p->id) : '#' }}" @submit.prevent="basculer($el)" class="flex-1">
                <input type="hidden" name="_token" value="{{ JetonsCarte::CSRF }}">
                <button type="submit" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 aria-pressed:text-brand"
                        aria-pressed="{{ JetonsCarte::AIME }}" :aria-pressed="aime.toString()">
                    <span aria-hidden="true">👍</span> J’aime
                </button>
            </form>
            <a href="{{ $lien }}#commentaires" class="flex min-h-11 flex-1 items-center justify-center gap-1 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <span aria-hidden="true">💬</span> Commenter
            </a>
            <a href="{{ $p->exists ? route('publications.partager', $p->slug) : '#' }}" target="_blank" rel="noopener"
               class="flex min-h-11 flex-1 items-center justify-center gap-1 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <span aria-hidden="true">↗</span> Partager<span class="sr-only"> sur WhatsApp</span>
            </a>
            {{-- Les vraies actions (postuler, contacter, s'inscrire…) sont sur la page détail. --}}
            <a href="{{ $lien }}" @class([
                'flex min-h-11 flex-1 items-center justify-center gap-1 rounded-lg px-2 text-sm font-semibold text-white',
                'bg-emploi hover:opacity-90' => $p->type === \App\Enums\TypePublication::Emploi,
                'bg-service hover:opacity-90' => $p->type === \App\Enums\TypePublication::Service,
                'bg-formation hover:opacity-90' => $p->type === \App\Enums\TypePublication::Formation,
            ])>Voir plus<span aria-hidden="true">→</span><span class="sr-only"> : {{ $titre }}</span></a>
        </div>
    </footer>
</article>
