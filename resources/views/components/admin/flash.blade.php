{{-- Messages de retour : affichés en toast par admin.js, et lisibles sans JavaScript. --}}
@if (session('statut'))
    <div data-toast="succes" data-message="{{ session('statut') }}" role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('statut') }}</div>
@endif
@if (session('erreur'))
    <div data-toast="erreur" data-message="{{ session('erreur') }}" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('erreur') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $erreur)
                <li>{{ $erreur }}</li>
            @endforeach
        </ul>
    </div>
@endif
