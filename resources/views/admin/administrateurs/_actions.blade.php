{{-- Actions d'un compte de l'équipe (menu « ⋯ »). Un super administrateur n'est géré que par un autre super administrateur. --}}
@if ($protege)
    <span class="inline-flex size-9 items-center justify-center text-slate-400" title="Seul un super administrateur peut gérer ce compte">
        <x-admin.icone nom="cadenas" class="size-4" /><span class="sr-only">Compte protégé</span>
    </span>
@else
    <x-admin.menu-actions :id="'administrateur-'.$administrateur->id" :libelle="'Actions pour '.$administrateur->nom_utilisateur">
        <x-admin.action :href="route('admin.administrateurs.edit', $administrateur)">
            <x-admin.icone nom="crayon" class="size-4" />Modifier
        </x-admin.action>
        @unless ($soiMeme)
            <form method="POST" action="{{ route('admin.administrateurs.reinitialiser-mot-de-passe', $administrateur) }}"
                  data-confirmer="Générer un nouveau mot de passe pour {{ $administrateur->nom_utilisateur }} ? L’ancien ne fonctionnera plus.">
                @csrf
                <x-admin.action><x-admin.icone nom="cle" class="size-4" />Nouveau mot de passe</x-admin.action>
            </form>
            @if ($administrateur->estSuspendu())
                <form method="POST" action="{{ route('admin.administrateurs.reactiver', $administrateur) }}" data-confirmer="Réactiver ce compte ?">
                    @csrf
                    <x-admin.action><x-admin.icone nom="lecture" class="size-4" />Réactiver</x-admin.action>
                </form>
            @else
                <form method="POST" action="{{ route('admin.administrateurs.suspendre', $administrateur) }}" data-confirmer="Suspendre {{ $administrateur->nom_utilisateur }} ? Il sera déconnecté au clic suivant." data-danger="1">
                    @csrf
                    <x-admin.action ton="attention"><x-admin.icone nom="pause" class="size-4" />Suspendre</x-admin.action>
                </form>
            @endif
            <hr class="my-1 border-slate-100">
            <form method="POST" action="{{ route('admin.administrateurs.destroy', $administrateur) }}" data-confirmer="Supprimer le compte {{ $administrateur->nom_utilisateur }} ?" data-danger="1">
                @csrf
                @method('DELETE')
                <x-admin.action ton="danger"><x-admin.icone nom="corbeille" class="size-4" />Supprimer</x-admin.action>
            </form>
        @endunless
    </x-admin.menu-actions>
@endif
