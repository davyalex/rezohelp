@extends('layouts.public')

@section('titre', 'Limite d’alertes atteinte')

@section('contenu')
    <x-carte-formulaire titre="Limite d’alertes atteinte">
        <x-message type="info">{{ $message }}</x-message>
        <x-bouton :href="route('alertes.index')" class="mt-6 w-full">Gérer mes alertes</x-bouton>
    </x-carte-formulaire>
@endsection
