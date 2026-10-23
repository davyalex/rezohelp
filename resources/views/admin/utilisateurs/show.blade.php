@extends('admin.layout')

@section('titre', $utilisateur->nomAffiche())

@section('contenu')
    <a href="{{ route('admin.utilisateurs.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Utilisateurs</a>
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold">{{ $utilisateur->nomAffiche() }}</h1>
        @if ($utilisateur->estSuspendu())
            <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-900">Suspendu{{ $utilisateur->suspendu_jusqu_au ? ' jusqu’au '.$utilisateur->suspendu_jusqu_au->format('d/m/Y') : ' (définitif)' }}</span>
        @endif
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm">Confiance : {{ $utilisateur->confiance->libelle() }}</span>
    </div>

    @if (session('mot_de_passe_temporaire'))
        <div role="alert" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Mot de passe temporaire (affiché une seule fois, à transmettre au membre) :
            <code class="ml-1 select-all rounded bg-white px-2 py-1 font-mono text-base font-bold">{{ session('mot_de_passe_temporaire') }}</code>
            <br>Il devra le changer à sa prochaine connexion.
        </div>
    @endif

    <div class="mt-4 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-infos">
                <h2 id="titre-infos" class="font-semibold">Informations</h2>
                <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-slate-600">Téléphone :</dt> <dd class="inline">{{ $telephone ?? '—' }} {{ $utilisateur->telephone_verifie_le ? '(vérifié)' : '(non vérifié)' }}</dd></div>
                    <div><dt class="inline text-slate-600">E-mail :</dt> <dd class="inline">{{ $utilisateur->email ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Commune :</dt> <dd class="inline">{{ $utilisateur->commune->nom ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Rôles :</dt> <dd class="inline">{{ $utilisateur->roles->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Inscrit le :</dt> <dd class="inline">{{ $utilisateur->cree_le?->format('d/m/Y') }}</dd></div>
                    <div><dt class="inline text-slate-600">Dernière connexion :</dt> <dd class="inline">{{ $utilisateur->derniere_connexion_le?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Publications :</dt> <dd class="inline">{{ $nbPublications }}</dd></div>
                    <div><dt class="inline text-slate-600">Alertes actives :</dt> <dd class="inline">{{ $nbAlertes }}</dd></div>
                </dl>
                @if ($utilisateur->motif_suspension && $utilisateur->estSuspendu())
                    <p class="mt-2 text-sm text-red-800">Motif de la suspension : {{ $utilisateur->motif_suspension }}</p>
                @endif
                @if ($utilisateur->entreprises->isNotEmpty())
                    <p class="mt-2 text-sm">Entreprises :
                        @foreach ($utilisateur->entreprises as $entreprise)
                            <a href="{{ route('admin.entreprises.show', $entreprise) }}" class="text-brand underline">{{ $entreprise->nom }}</a>{{ $entreprise->verifiee ? ' ✔' : '' }}@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif
                @if ($abonnements->isNotEmpty())
                    <p class="mt-2 text-sm">Formules : {{ $abonnements->map(fn ($a) => $a->plan->nom.' ('.$a->statut->libelle().')')->implode(', ') }}</p>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-publications">
                <h2 id="titre-publications" class="font-semibold">Publications récentes</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($publications as $publication)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>{{ $publication->titre }} · {{ $publication->type->libelle() }} · {{ $publication->cree_le?->format('d/m/Y') }}</span>
                            <span>@if ($publication->trashed())<span class="text-xs text-red-700">supprimée</span>@else<x-admin.badge-statut :statut="$publication->statut" />@endif</span>
                        </li>
                    @empty
                        <li class="py-2 text-slate-600">Aucune.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-signalements">
                <h2 id="titre-signalements" class="font-semibold">Signalements reçus</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($signalementsRecus as $signalement)
                        <li class="py-2">{{ $signalement->motif->libelle() }} · « {{ $signalement->publication?->titre }} » · {{ $signalement->cree_le?->format('d/m/Y') }}{{ $signalement->traite_le ? ($signalement->retenu ? ' · retenu' : ' · non retenu') : ' · ouvert' }}</li>
                    @empty
                        <li class="py-2 text-slate-600">Aucun.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-historique">
                <h2 id="titre-historique" class="font-semibold">Historique des actions de l’équipe</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($sanctions as $ligne)
                        <li class="py-2">{{ $ligne->cree_le?->format('d/m/Y H:i') }} · {{ $ligne->action }} · par {{ $ligne->administrateur?->nomAffiche() ?? '—' }}@if ($ligne->donnees['motif'] ?? null) · {{ $ligne->donnees['motif'] }}@endif</li>
                    @empty
                        <li class="py-2 text-slate-600">Aucune.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-4" aria-label="Actions">
            @can('utilisateurs.suspendre')
                <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="font-semibold">Suspension</h2>
                    @if ($utilisateur->estSuspendu())
                        <form method="POST" action="{{ route('admin.utilisateurs.reactiver', $utilisateur) }}" class="mt-3" data-confirmer="Réactiver ce compte ?">
                            @csrf
                            <x-bouton class="w-full">Réactiver le compte</x-bouton>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.utilisateurs.suspendre', $utilisateur) }}" class="mt-3 space-y-3" data-confirmer="Suspendre ce compte ? Ses publications en ligne seront retirées du fil." data-danger="1">
                            @csrf
                            <x-champ name="motif_suspension" label="Motif" required maxlength="255" />
                            <x-champ name="suspendu_jusqu_au" label="Jusqu’au (vide = définitif)" type="date" />
                            <x-bouton variante="danger" class="w-full">Suspendre</x-bouton>
                        </form>
                    @endif
                </section>
            @endcan

            @can('utilisateurs.gerer')
                <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="font-semibold">Rôles</h2>
                    <form method="POST" action="{{ route('admin.utilisateurs.roles', $utilisateur) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PUT')
                        <label for="roles" class="sr-only">Rôles</label>
                        <select id="roles" name="roles[]" multiple data-select2 data-placeholder="Aucun rôle">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected($utilisateur->hasRole($role))>{{ $role }}</option>
                            @endforeach
                        </select>
                        <x-bouton variante="secondaire" class="w-full">Enregistrer les rôles</x-bouton>
                    </form>
                </section>

                <section class="space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="font-semibold">Compte</h2>
                    <x-bouton :href="route('admin.utilisateurs.edit', $utilisateur)" variante="secondaire" class="w-full">Modifier les informations</x-bouton>
                    <form method="POST" action="{{ route('admin.utilisateurs.reinitialiser-mot-de-passe', $utilisateur) }}" data-confirmer="Générer un mot de passe temporaire ? L’ancien ne fonctionnera plus.">
                        @csrf
                        <x-bouton variante="secondaire" class="w-full">Réinitialiser le mot de passe</x-bouton>
                    </form>
                    @if ($utilisateur->confiance === \App\Enums\ConfianceUtilisateur::Restreint)
                        <form method="POST" action="{{ route('admin.utilisateurs.lever-restriction', $utilisateur) }}" data-confirmer="Lever la restriction ? Le compte pourra de nouveau publier.">
                            @csrf
                            <x-bouton variante="secondaire" class="w-full">Lever la restriction</x-bouton>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.utilisateurs.destroy', $utilisateur) }}" data-confirmer="Supprimer ce compte (ARTCI) ? Ses données personnelles seront effacées, ses publications conservées sous « Utilisateur supprimé ». Cette action est définitive." data-danger="1">
                        @csrf
                        @method('DELETE')
                        <x-bouton variante="danger" class="w-full">Supprimer et anonymiser</x-bouton>
                    </form>
                </section>
            @endcan
        </aside>
    </div>
@endsection
