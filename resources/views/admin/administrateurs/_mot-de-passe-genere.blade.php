{{-- Mot de passe généré, affiché une seule fois (session flash « mot_de_passe_genere »). --}}
@if (session('mot_de_passe_genere'))
    @php($genere = session('mot_de_passe_genere'))
    <div role="alert" class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950" x-data="{ copie: false }">
        <p class="font-semibold">Mot de passe provisoire de « {{ $genere['nom_utilisateur'] }} »</p>
        <p class="mt-1 text-sm">Transmettez-le de façon sûre. Il n’est affiché qu’une fois et devra être changé à la première connexion.</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <code x-ref="mdp" class="rounded-lg bg-white px-3 py-2 font-mono text-lg tracking-widest ring-1 ring-amber-300">{{ $genere['mot_de_passe'] }}</code>
            <button type="button" class="inline-flex min-h-10 items-center rounded-lg bg-amber-900 px-3 text-sm font-semibold text-white hover:bg-amber-950"
                    @click="navigator.clipboard?.writeText($refs.mdp.textContent.trim()).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                <span x-show="!copie">Copier</span><span x-cloak x-show="copie">Copié ✓</span>
            </button>
        </div>
    </div>
@endif
