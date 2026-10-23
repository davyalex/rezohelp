@extends('layouts.public')

@php
    use App\Enums\MotifSignalement;
    use App\Enums\StatutPublication;
    use App\Enums\TypePublication;

    $p = $publication;
    $url = route('publications.show', $p->slug);
    $aime = $etat->aime($p);
    $enregistree = $etat->estEnregistree($p);
@endphp

@section('titre', $p->titre)
@section('description', $p->extrait)

@push('entete')
    <link rel="canonical" href="{{ $url }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $p->titre }}">
    <meta property="og:description" content="{{ $p->extrait }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $p->image_url ?? asset('images/partage.png') }}">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">
    @if ($jsonLd !== null)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
@endpush

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('fil.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Retour au fil</a>

        <article class="mt-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-publication">
            <header class="flex items-start gap-3">
                @php($nomAuteur = $p->auteur_nom_affiche ?? 'Membre Rezohelp')
                @if ($p->auteur_avatar_url)
                    <img src="{{ $p->auteur_avatar_url }}" alt="" width="48" height="48" class="size-12 shrink-0 rounded-full object-cover" loading="lazy">
                @else
                    <span aria-hidden="true" @class([
                        'flex size-12 shrink-0 items-center justify-center rounded-full text-lg font-bold',
                        'bg-emploi-clair text-emploi' => $p->type === TypePublication::Emploi,
                        'bg-service-clair text-service' => $p->type === TypePublication::Service,
                        'bg-formation-clair text-formation' => $p->type === TypePublication::Formation,
                    ])>{{ mb_strtoupper(mb_substr($nomAuteur, 0, 1)) }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-1 font-semibold text-slate-900">
                        <span class="break-words">{{ $nomAuteur }}</span>
                        @if ($p->auteur_verifie)
                            <span class="text-sm font-normal text-brand">✔ Entreprise vérifiée</span>
                        @endif
                    </p>
                    <p class="text-sm text-slate-600">
                        Publiée {{ ($p->publiee_le ?? $p->cree_le)?->diffForHumans() }}@if ($commune) · {{ $commune }}@endif
                        @if ($p->source === \App\Enums\SourcePublication::VeilleIa && $p->source_nom)
                            · via @if ($p->source_url)<a href="{{ $p->source_url }}" target="_blank" rel="noopener nofollow" class="font-semibold text-brand underline underline-offset-2 hover:text-brand-fonce">{{ $p->source_nom }}<span aria-hidden="true"> ↗</span><span class="sr-only"> (nouvel onglet)</span></a>@else{{ $p->source_nom }}@endif
                        @endif
                    </p>
                </div>
                <x-badge-type :type="$p->type" :sous-type="$p->sous_type" />
            </header>

            <h1 id="titre-publication" class="mt-4 text-2xl font-bold leading-tight text-slate-900">{{ $p->titre }}</h1>
            @if ($categorie)
                <p class="mt-1 text-slate-600">{{ $categorie }}</p>
            @endif

            @if ($p->statut === StatutPublication::Cloturee)
                <x-message type="info" class="mt-4">Cette publication est clôturée{{ $p->motif_cloture ? ' : '.$p->motif_cloture : '' }}.</x-message>
            @elseif ($p->statut === StatutPublication::Expiree)
                <x-message type="info" class="mt-4">Cette publication a expiré.</x-message>
            @elseif ($p->statut !== StatutPublication::Publiee)
                <x-message type="info" class="mt-4">Cette publication n’est pas visible dans le fil ({{ $p->statut->libelle() }}).</x-message>
            @endif

            {{-- 1. L'essentiel (contrat, lieu, salaire, date limite…), 2. l'action, 3. le texte. --}}
            @include('publications._infos')

            {{-- Répondre / contacter --}}
            @include('publications._repondre')

            @if ($p->type === TypePublication::Emploi)
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><span aria-hidden="true">⚠️</span> {{ \App\Models\Parametre::texte('texte_bandeau_prevention') }}</p>
            @endif

            <section aria-labelledby="titre-description" class="mt-6 border-t border-slate-100 pt-4">
                <h2 id="titre-description" class="text-lg font-bold text-slate-900">{{ $p->type === TypePublication::Formation ? 'Programme' : 'Description' }}</h2>

                @if ($p->image_url)
                    <img src="{{ $p->image_url }}" alt="{{ $p->titre }}" width="{{ $p->image_largeur ?? 640 }}" height="{{ $p->image_hauteur ?? 360 }}" class="mt-3 h-auto w-full rounded-xl object-cover">
                @endif

                <div class="mt-2 space-y-3 leading-relaxed text-slate-800">{!! nl2br(e($p->contenu)) !!}</div>
            </section>

            {{-- Actions --}}
            <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4" x-data="reaction({{ $aime ? 'true' : 'false' }}, {{ $p->nb_jaime }})">
                <form method="POST" action="{{ route('publications.jaime', $p->id) }}" @submit.prevent="basculer($el)">
                    @csrf
                    <x-bouton variante="secondaire" aria-pressed="{{ $aime ? 'true' : 'false' }}" x-bind:aria-pressed="aime.toString()" class="aria-pressed:border-brand aria-pressed:text-brand">
                        👍 J’aime · <span x-text="nb">{{ $p->nb_jaime }}</span>
                    </x-bouton>
                </form>
                <form method="POST" action="{{ route('publications.enregistrer', $p->id) }}" x-data="bascule({{ $enregistree ? 'true' : 'false' }})" @submit.prevent="envoyer($el)">
                    @csrf
                    <x-bouton variante="secondaire" aria-pressed="{{ $enregistree ? 'true' : 'false' }}" x-bind:aria-pressed="actif.toString()" class="group">
                        🔖 <span class="group-aria-pressed:hidden">Enregistrer</span><span class="hidden group-aria-pressed:inline">Enregistré</span>
                    </x-bouton>
                </form>
                @if ($p->statut === StatutPublication::Publiee)
                    <x-bouton :href="route('publications.partager', $p->slug)" target="_blank" rel="noopener" variante="secondaire">↗ Partager sur WhatsApp</x-bouton>
                @endif
            </div>
        </article>

        {{-- Bannière publicitaire sous l'annonce (rien si aucune annonce). --}}
        <x-publicite emplacement="detail_bas" class="mt-4" />

        {{-- Commentaires --}}
        <section id="commentaires" class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-commentaires">
            <h2 id="titre-commentaires" class="text-lg font-bold">Commentaires ({{ $p->nb_commentaires }})</h2>

            @auth
                @if ($p->statut === StatutPublication::Publiee)
                    <form method="POST" action="{{ route('publications.commenter', $p->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="contenu" class="sr-only">Votre commentaire</label>
                        <textarea id="contenu" name="contenu" rows="2" maxlength="1000" required placeholder="Écrire un commentaire…"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base focus:border-brand focus:ring-2 focus:ring-brand/30">{{ old('contenu') }}</textarea>
                        @error('contenu')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <x-bouton>Publier</x-bouton>
                    </form>
                @endif
            @else
                <p class="mt-3 text-sm text-slate-700"><a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Connectez-vous</a> pour commenter.</p>
            @endauth

            <ul class="mt-4 space-y-4">
                @forelse ($p->commentaires as $commentaire)
                    <li id="commentaire-{{ $commentaire->id }}">
                        @include('publications._commentaire', ['commentaire' => $commentaire])
                        @if ($commentaire->sousCommentaires->isNotEmpty())
                            <ul class="ml-6 mt-3 space-y-3 border-l-2 border-slate-100 pl-4">
                                @foreach ($commentaire->sousCommentaires as $reponse)
                                    <li id="commentaire-{{ $reponse->id }}">@include('publications._commentaire', ['commentaire' => $reponse])</li>
                                @endforeach
                            </ul>
                        @endif
                        @auth
                            @if ($p->statut === StatutPublication::Publiee)
                                <details class="ml-6 mt-2">
                                    <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-brand">Répondre</summary>
                                    <form method="POST" action="{{ route('publications.commenter', $p->id) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $commentaire->id }}">
                                        <label for="reponse-{{ $commentaire->id }}" class="sr-only">Votre réponse</label>
                                        <textarea id="reponse-{{ $commentaire->id }}" name="contenu" rows="2" maxlength="1000" required
                                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                                        <x-bouton variante="secondaire">Répondre</x-bouton>
                                    </form>
                                </details>
                            @endif
                        @endauth
                    </li>
                @empty
                    <li class="text-sm text-slate-600">Aucun commentaire pour le moment.</li>
                @endforelse
            </ul>
        </section>

        {{-- Signaler --}}
        <section id="signaler" class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-signaler">
            <details>
                <summary id="titre-signaler" class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-slate-800">🚩 Signaler cette publication</summary>
                @auth
                    <form method="POST" action="{{ route('publications.signaler', $p->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <fieldset>
                            <legend class="text-sm font-medium text-slate-800">Motif</legend>
                            <div class="mt-2 space-y-1">
                                @foreach (MotifSignalement::cases() as $motif)
                                    <label class="flex min-h-11 items-center gap-2 text-sm">
                                        <input type="radio" name="motif" value="{{ $motif->value }}" required class="size-5 text-brand" @checked(old('motif') === $motif->value)>
                                        {{ $motif->libelle() }}
                                    </label>
                                @endforeach
                            </div>
                            @error('motif')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        </fieldset>
                        <div>
                            <label for="commentaire-signalement" class="block text-sm font-medium text-slate-800">Précisions (facultatif)</label>
                            <textarea id="commentaire-signalement" name="commentaire" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                        </div>
                        <x-bouton variante="danger">Envoyer le signalement</x-bouton>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-700"><a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Connectez-vous</a> pour signaler une publication.</p>
                @endauth
            </details>
        </section>
    </div>
@endsection
