@extends('admin.layout')

@section('titre', 'Commande '.$commande->numero)

@section('contenu')
    <a href="{{ route('admin.commandes.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Commandes</a>
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold">Commande {{ $commande->numero }}</h1>
        <x-admin.badge-statut :statut="$commande->statut" />
    </div>

    <div class="mt-4 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-slate-600">Article :</dt> <dd class="inline">{{ $commande->article()?->nom ?? '—' }} ({{ $commande->article_type->libelle() }})</dd></div>
                    <div><dt class="inline text-slate-600">Montant :</dt> <dd class="inline">{{ \App\Support\Montant::fcfa($commande->montant_ttc_fcfa) }} TTC · HT {{ \App\Support\Montant::fcfa($commande->montant_ht_fcfa) }} · TVA {{ $commande->taux_tva }} %</dd></div>
                    <div><dt class="inline text-slate-600">Acheteur :</dt>
                        <dd class="inline">@if ($commande->utilisateur)<a href="{{ route('admin.utilisateurs.show', $commande->utilisateur) }}" class="text-brand underline">{{ $commande->utilisateur->nomAffiche() }}</a>@else — @endif</dd></div>
                    <div><dt class="inline text-slate-600">Titulaire :</dt> <dd class="inline">{{ $commande->titulaire instanceof \App\Models\Entreprise ? $commande->titulaire->nom : ($commande->titulaire instanceof \App\Models\Utilisateur ? $commande->titulaire->nomAffiche() : '—') }}</dd></div>
                    <div><dt class="inline text-slate-600">Passerelle :</dt> <dd class="inline">{{ $commande->passerelle->libelle() }} · {{ $commande->moyen_paiement ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Référence :</dt> <dd class="inline font-mono">{{ $commande->reference_passerelle ?? '—' }}</dd></div>
                    <div><dt class="inline text-slate-600">Créée le :</dt> <dd class="inline">{{ $commande->cree_le?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="inline text-slate-600">Payée le :</dt> <dd class="inline">{{ $commande->payee_le?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    @if ($commande->publication)
                        <div class="sm:col-span-2"><dt class="inline text-slate-600">Publication :</dt> <dd class="inline">{{ $commande->publication->titre }}</dd></div>
                    @endif
                    @if ($commande->abonnement)
                        <div class="sm:col-span-2"><dt class="inline text-slate-600">Abonnement :</dt> <dd class="inline">{{ $commande->abonnement->plan->nom }} · {{ $commande->abonnement->statut->libelle() }} · jusqu’au {{ $commande->abonnement->fin_le?->format('d/m/Y') }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-passerelle">
                <h2 id="titre-passerelle" class="font-semibold">Données de la passerelle</h2>
                <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ json_encode($commande->donnees_passerelle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titre-notifications">
                <h2 id="titre-notifications" class="font-semibold">Notifications reçues (webhook)</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($notifications as $notification)
                        <li class="py-2">
                            {{ $notification->cree_le?->format('d/m/Y H:i:s') }} · signature {{ $notification->signature_valide ? 'valide' : 'invalide' }}
                            · {{ $notification->traitee_le ? 'traitée' : 'non traitée' }}@if ($notification->erreur) · <span class="text-red-700">{{ $notification->erreur }}</span>@endif
                        </li>
                    @empty
                        <li class="py-2 text-slate-600">Aucune.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-4" aria-label="Actions">
            <section class="space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                @if ($commande->statut === \App\Enums\StatutCommande::EnAttente)
                    <form method="POST" action="{{ route('admin.commandes.reverifier', $commande) }}">
                        @csrf
                        <x-bouton class="w-full">Revérifier le statut</x-bouton>
                    </form>
                @endif
                @can('voirRecu', $commande)
                    <x-bouton :href="route('admin.commandes.recu-pdf', $commande)" variante="secondaire" class="w-full">Télécharger le reçu {{ $commande->numero_recu }}</x-bouton>
                @endcan
                @if ($commande->statut === \App\Enums\StatutCommande::Payee)
                    <form method="POST" action="{{ route('admin.commandes.rembourser', $commande) }}" class="space-y-2" data-confirmer="Marquer cette commande comme remboursée ? Les droits associés seront arrêtés." data-danger="1">
                        @csrf
                        <x-champ name="motif" label="Motif du remboursement" maxlength="255" required />
                        <x-bouton variante="danger" class="w-full">Marquer remboursée</x-bouton>
                    </form>
                    <p class="text-xs text-slate-500">Le remboursement lui-même se fait auprès de l’opérateur (CinetPay).</p>
                @endif
            </section>
        </aside>
    </div>
@endsection
