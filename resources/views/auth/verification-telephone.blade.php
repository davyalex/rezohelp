@extends('layouts.public')

@section('titre', 'Vérifier mon numéro')

@section('contenu')
    <x-carte-formulaire titre="Vérifiez votre numéro"
                        :sous-titre="'Saisissez le code à 6 chiffres envoyé par SMS au '.app(\App\Services\TelephoneService::class)->afficher($telephone, auth()->user()->pays_code).'.'">
        <form method="POST" action="{{ route('telephone.verification.verifier') }}" class="space-y-5" novalidate>
            @csrf
            <x-champ name="code" label="Code reçu par SMS" inputmode="numeric" autocomplete="one-time-code"
                     maxlength="6" pattern="[0-9]{6}" required autofocus aide="Le code est valable 10 minutes." />
            <x-bouton class="w-full">Vérifier</x-bouton>
        </form>

        <form method="POST" action="{{ route('telephone.verification.envoyer') }}" class="mt-4">
            @csrf
            <x-bouton variante="lien" class="w-full">Je n’ai rien reçu : renvoyer le code</x-bouton>
        </form>

        <p class="mt-2 text-center">
            <a href="{{ session('url.intended', route('fil.index')) }}" class="inline-flex min-h-11 items-center text-sm text-slate-600 underline-offset-4 hover:underline">Vérifier plus tard</a>
        </p>
    </x-carte-formulaire>
@endsection
