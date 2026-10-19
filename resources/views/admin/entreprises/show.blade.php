@extends('admin.layout')

@section('titre', $entreprise->nom)

@section('contenu')
    <a href="{{ route('admin.entreprises.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Entreprises</a>
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold">{{ $entreprise->nom }}</h1>
        @if ($entreprise->verifiee)
            <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-900">✔ Entreprise vérifiée</span>
        @endif
    </div>

    <div class="mt-4 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-slate-600">Secteur :</dt> <dd class="inline">{{ $entreprise->secteur ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Commune :</dt> <dd class="inline">{{ $commune ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Site web :</dt> <dd class="inline">{{ $entreprise->site_web ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Propriétaire :</dt>
                        <dd class="inline">
                            @if ($entreprise->proprietaire)
                                <a href="{{ route('admin.utilisateurs.show', $entreprise->proprietaire) }}" class="text-brand underline">{{ $entreprise->proprietaire->nomAffiche() }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($abonnements->isNotEmpty())
                    <p class="mt-2 text-sm">Formules : {{ $abonnements->map(fn ($a) => $a->plan->nom.' ('.$a->statut->libelle().')')->implode(', ') }}</p>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-publications">
                <h2 id="titre-publications" class="font-semibold">Publications</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($publications as $publication)
                        <li class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $publication->titre }} · {{ $publication->cree_le?->format('d/m/Y') }}</span><x-admin.badge-statut :statut="$publication->statut" /></li>
                    @empty
                        <li class="py-2 text-slate-600">Aucune.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        @can('entreprises.verifier')
            <aside class="space-y-4" aria-label="Actions">
                <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="font-semibold">Vérification</h2>
                    <form method="POST" action="{{ route('admin.entreprises.verifier', $entreprise) }}" class="mt-3"
                          data-confirmer="{{ $entreprise->verifiee ? 'Retirer le badge « Entreprise vérifiée » ?' : 'Attribuer le badge « Entreprise vérifiée » ? Ses offres sans signal de risque seront publiées sans modération.' }}">
                        @csrf
                        <x-bouton :variante="$entreprise->verifiee ? 'secondaire' : 'primaire'" class="w-full">{{ $entreprise->verifiee ? 'Retirer le badge' : 'Vérifier l’entreprise' }}</x-bouton>
                    </form>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="font-semibold">Fusionner un doublon</h2>
                    <p class="mt-1 text-sm text-slate-600">Cette fiche sera supprimée ; ses publications, abonnements et commandes passeront sur la fiche choisie.</p>
                    <form method="POST" action="{{ route('admin.entreprises.fusionner', $entreprise) }}" class="mt-3 space-y-3"
                          data-confirmer="Fusionner « {{ $entreprise->nom }} » dans la fiche choisie ? Cette fiche sera supprimée." data-danger="1">
                        @csrf
                        <label for="entreprise_conservee_id" class="block text-sm font-medium">Fiche conservée</label>
                        <select id="entreprise_conservee_id" name="entreprise_conservee_id" required data-select2 data-ajax="{{ route('admin.recherche.entreprises') }}" data-placeholder="Nom de l’entreprise"></select>
                        <x-bouton variante="danger" class="w-full">Fusionner</x-bouton>
                    </form>
                </section>
            </aside>
        @endcan
    </div>
@endsection
