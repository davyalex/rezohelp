{{--
    Actions d'une publication dans « Mes publications » : l'action principale (Modifier) reste visible,
    les autres sont regroupées dans un menu « Actions » (Alpine : menuDeroulant). Clôturer ouvre une
    boîte pour choisir le motif ; Supprimer passe par la boîte de confirmation du site.
--}}
@php
    use App\Enums\StatutPublication;
    use App\Http\Requests\Espace\CloturerPublicationRequest;

    $enLigne = $publication->statut === StatutPublication::Publiee;
    $itemMenu = 'flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50';
    $visibilite = $enLigne && auth()->user()?->can('mettreEnAvant', $publication);
    $remonteeIncluse = $visibilite && $droitsTitulaire['remontees'] !== 0;
    $produitsVisibilite = $visibilite ? collect(['une_commune' => '⭐ À la une', 'sponsorise_fil' => '📣 Sponsoriser'])->filter(fn ($libelle, $type) => $produits->has($type)) : collect();
    $achatRemontee = $visibilite && ! $remonteeIncluse && $produits->has('remontee');
@endphp

<div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
    @can('update', $publication)
        <x-bouton :href="route('espace.publications.edit', $publication->id)" variante="secondaire" class="min-h-11 text-sm">
            {{ match ($publication->statut) { StatutPublication::Brouillon => 'Compléter et publier', StatutPublication::Rejetee => 'Corriger et renvoyer', default => 'Modifier' } }}
        </x-bouton>
    @endcan

    <div x-data="menuDeroulant" class="relative" @keydown.escape.window="fermer()" @click.outside="fermer(false)"
         @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && !$refs.cloture?.open && fermer(false)">
        <button type="button" x-ref="bouton" @click="basculer()" @keydown.arrow-down.prevent="ouvrir()"
                :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="actions-{{ $publication->id }}"
                class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50 aria-expanded:bg-slate-100">
            Actions
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-slate-600 transition-transform motion-reduce:transition-none" :class="ouvert && 'rotate-180'">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
            </svg>
            <span class="sr-only">: {{ $publication->titre }}</span>
        </button>

        <div id="actions-{{ $publication->id }}" x-ref="panneau" x-cloak x-show="ouvert"
             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
             class="absolute left-0 top-full z-30 mt-2 w-72 origin-top-left overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
            <ul class="py-1">
                @if ($publication->nb_reponses > 0 || $enLigne)
                    <li><a href="{{ route('espace.reponses.index', $publication->id) }}" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">💬</span>Réponses ({{ $publication->nb_reponses }})</a></li>
                @endif
                @if ($enLigne)
                    <li><a href="{{ route('publications.show', $publication->slug) }}" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">👁</span>Voir sur le site</a></li>
                    <li><a href="{{ route('publications.partager', $publication->slug) }}" target="_blank" rel="noopener" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">↗</span>Partager sur WhatsApp</a></li>
                    @if ($publication->nb_prolongations < $maxProlongations)
                        <li>
                            <form method="POST" action="{{ route('espace.publications.prolonger', $publication->id) }}">
                                @csrf
                                <button type="submit" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">⏳</span>Prolonger</button>
                            </form>
                        </li>
                    @endif
                @endif
                <li>
                    <form method="POST" action="{{ route('espace.publications.dupliquer', $publication->id) }}">
                        @csrf
                        <button type="submit" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">⧉</span>Dupliquer</button>
                    </form>
                </li>
            </ul>

            @if ($remonteeIncluse || $achatRemontee || $produitsVisibilite->isNotEmpty())
                <ul class="border-t border-slate-100 py-1" aria-label="Visibilité">
                    @if ($remonteeIncluse)
                        <li>
                            <form method="POST" action="{{ route('espace.publications.remonter', $publication->id) }}">
                                @csrf
                                <button type="submit" class="{{ $itemMenu }}"><span aria-hidden="true" class="w-5 text-center">⬆</span>
                                    Remonter en tête du fil{{ $droitsTitulaire['remontees'] > 0 ? ' (incluse, '.$droitsTitulaire['remontees'].' restante(s))' : '' }}</button>
                            </form>
                        </li>
                    @elseif ($achatRemontee)
                        @include('espace.publications._achat', ['code' => $produits['remontee']->code, 'libelle' => '⬆', 'texte' => 'Remonter en tête du fil · '.\App\Support\Montant::fcfa($produits['remontee']->prix_fcfa)])
                    @endif
                    @foreach ($produitsVisibilite as $type => $libelle)
                        @include('espace.publications._achat', ['code' => $produits[$type]->code, 'libelle' => mb_substr($libelle, 0, 1), 'texte' => trim(mb_substr($libelle, 1)).' '.$produits[$type]->duree_jours.' j · '.\App\Support\Montant::fcfa($produits[$type]->prix_fcfa)])
                    @endforeach
                </ul>
            @endif

            <ul class="border-t border-slate-100 py-1">
                @if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::EnAttente], true))
                    <li>
                        <button type="button" class="{{ $itemMenu }}" @click="fermer(false); $refs.cloture.showModal()">
                            <span aria-hidden="true" class="w-5 text-center">✔</span>Clôturer…
                        </button>
                    </li>
                @endif
                <li>
                    <form method="POST" action="{{ route('espace.publications.destroy', $publication->id) }}"
                          data-confirmer="Supprimer cette publication ?" data-detail="Elle disparaîtra du fil et de vos publications. Les réponses reçues ne seront plus accessibles." data-bouton="Supprimer" data-danger>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:bg-red-50"><span aria-hidden="true" class="w-5 text-center">🗑</span>Supprimer</button>
                    </form>
                </li>
            </ul>
        </div>

        @if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::EnAttente], true))
            {{-- Clôture : le motif s'affiche sur la page de la publication (« Poste pourvu »…). --}}
            <dialog x-ref="cloture" aria-labelledby="cloture-titre-{{ $publication->id }}"
                    class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-white p-0 shadow-xl backdrop:bg-slate-900/50"
                    @click="$event.target === $el && $el.close()">
                <form method="POST" action="{{ route('espace.publications.cloturer', $publication->id) }}" class="space-y-4 p-5">
                    @csrf
                    <p id="cloture-titre-{{ $publication->id }}" class="text-lg font-bold text-slate-900">Clôturer cette publication ?</p>
                    <p class="text-sm text-slate-700">Elle quitte le fil ; sa page reste visible avec le motif choisi.</p>
                    <div>
                        <label for="motif-{{ $publication->id }}" class="block text-sm font-medium text-slate-800">Motif</label>
                        <select id="motif-{{ $publication->id }}" name="motif_cloture" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-base">
                            @foreach (CloturerPublicationRequest::MOTIFS as $motif)
                                <option value="{{ $motif }}">{{ $motif }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="$refs.cloture.close()" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 font-semibold text-slate-800 hover:bg-slate-50">Annuler</button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 font-semibold text-white hover:bg-brand-fonce">Clôturer</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
</div>
