@props(['table', 'url', 'filtres' => null, 'id' => null, 'libelle'])
{{-- Liste DataTables côté serveur : admin.js lit la configuration dans data-datatable. --}}
<div class="overflow-x-auto rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
    <table @if ($id) id="{{ $id }}" @endif class="display w-full text-sm" aria-label="{{ $libelle }}"
           data-datatable="{{ json_encode($table->configuration($url)) }}"
           @if ($filtres) data-filtres="{{ $filtres }}" @endif>
        <thead>
            <tr>
                @foreach ($table->colonnes() as $colonne)
                    <th scope="col">
                        @if ($colonne['data'] === 'selection')
                            <input type="checkbox" data-tout-cocher class="size-5" aria-label="Tout sélectionner">
                        @else
                            {{ $colonne['titre'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
    </table>
</div>
