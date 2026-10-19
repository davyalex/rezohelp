@extends('admin.layout')

@section('titre', 'Tableau de bord')

@section('contenu')
    <h1 class="text-2xl font-bold">Tableau de bord</h1>

    @if ($importEnRetard)
        <div role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900">
            Aucun import de la veille réussi depuis plus de {{ \App\Services\StatistiquesService::HEURES_ALERTE_IMPORT }} heures.
            @can('veille.gerer')<a href="{{ route('admin.veille.imports.index') }}" class="underline">Voir les imports</a>@endcan
        </div>
    @endif

    <section aria-labelledby="titre-compteurs" class="mt-4">
        <h2 id="titre-compteurs" class="sr-only">Compteurs</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.carte-stat libelle="En attente de modération" :valeur="$compteurs['en_attente']" ton="{{ $compteurs['en_attente'] > 0 ? 'attention' : 'neutre' }}"
                                :href="auth()->user()->can('publications.moderer') ? route('admin.moderation.index') : null" />
            <x-admin.carte-stat libelle="Signalements ouverts" :valeur="$compteurs['signalements']" ton="{{ $compteurs['signalements'] > 0 ? 'danger' : 'neutre' }}"
                                :href="auth()->user()->can('signalements.traiter') ? route('admin.signalements.index') : null" />
            <x-admin.carte-stat libelle="Publiées aujourd’hui" :valeur="array_sum($compteurs['publiees_aujourdhui'])"
                                :detail="'Emplois '.$compteurs['publiees_aujourdhui']['emploi'].' · Services '.$compteurs['publiees_aujourdhui']['service'].' · Formations '.$compteurs['publiees_aujourdhui']['formation']" />
            <x-admin.carte-stat libelle="Nouveaux inscrits" :valeur="$compteurs['inscrits_jour']" :detail="$compteurs['inscrits_7j'].' sur 7 jours'" />
            <x-admin.carte-stat libelle="Alertes actives" :valeur="$compteurs['alertes_actives']" />
            <x-admin.carte-stat libelle="Notifications envoyées aujourd’hui" :valeur="$compteurs['notifications_jour']" />
            <x-admin.carte-stat libelle="Dernier import de la veille"
                                :valeur="$dernierImport?->cree_le?->diffForHumans() ?? 'Aucun'"
                                :detail="$dernierImport ? $dernierImport->nb_importees.' importées · '.$dernierImport->nb_doublons.' doublons · '.$dernierImport->nb_rejetees.' rejetées · '.$dernierImport->statut->libelle() : null"
                                ton="{{ $importEnRetard ? 'danger' : 'neutre' }}" />
        </div>
        <p class="mt-2 text-xs text-slate-500">Compteurs actualisés toutes les 5 minutes.</p>
    </section>

    @php
        $maximum = max(1, max(array_column($serie, 'publications')), max(array_column($serie, 'inscriptions')));
        $largeurBarre = 8;
        $pas = 22;
        $hauteur = 120;
    @endphp
    <section aria-labelledby="titre-graphique" class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
        <h2 id="titre-graphique" class="font-semibold">30 derniers jours</h2>
        <p class="mt-1 flex gap-4 text-sm text-slate-600">
            <span><span aria-hidden="true" class="inline-block size-3 rounded-sm bg-brand"></span> Publications mises en ligne ({{ array_sum(array_column($serie, 'publications')) }})</span>
            <span><span aria-hidden="true" class="inline-block size-3 rounded-sm bg-amber-500"></span> Inscriptions ({{ array_sum(array_column($serie, 'inscriptions')) }})</span>
        </p>
        <div class="mt-3 overflow-x-auto">
            <svg viewBox="0 0 {{ count($serie) * $pas }} {{ $hauteur + 20 }}" class="h-44 min-w-[660px]" role="img"
                 aria-label="Publications et inscriptions par jour sur 30 jours">
                @foreach ($serie as $rang => $jour)
                    @php
                        $x = $rang * $pas;
                        $hPublications = round($jour['publications'] / $maximum * $hauteur);
                        $hInscriptions = round($jour['inscriptions'] / $maximum * $hauteur);
                    @endphp
                    <g>
                        <title>{{ \Illuminate\Support\Carbon::parse($jour['jour'])->translatedFormat('j M') }} : {{ $jour['publications'] }} publication(s), {{ $jour['inscriptions'] }} inscription(s)</title>
                        <rect x="{{ $x }}" y="{{ $hauteur - $hPublications }}" width="{{ $largeurBarre }}" height="{{ $hPublications }}" class="fill-brand" />
                        <rect x="{{ $x + $largeurBarre }}" y="{{ $hauteur - $hInscriptions }}" width="{{ $largeurBarre }}" height="{{ $hInscriptions }}" class="fill-amber-500" />
                        @if ($rang % 5 === 0)
                            <text x="{{ $x }}" y="{{ $hauteur + 14 }}" class="fill-slate-500 text-[9px]">{{ \Illuminate\Support\Carbon::parse($jour['jour'])->format('d/m') }}</text>
                        @endif
                    </g>
                @endforeach
            </svg>
        </div>
    </section>

    <div class="mt-6 flex flex-wrap gap-2">
        @can('publications.moderer')
            <x-bouton :href="route('admin.moderation.index')">Modérer ({{ $compteurs['en_attente'] }})</x-bouton>
        @endcan
        @can('signalements.traiter')
            <x-bouton :href="route('admin.signalements.index')" variante="secondaire">Signalements ({{ $compteurs['signalements'] }})</x-bouton>
        @endcan
    </div>
@endsection
