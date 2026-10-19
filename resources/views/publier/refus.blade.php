@extends('layouts.public')

@section('titre', 'Publication impossible')

@section('contenu')
    <x-carte-formulaire titre="Publication impossible pour le moment">
        <x-message type="erreur">{{ $message }}</x-message>
        <x-bouton :href="route('espace.publications.index')" variante="secondaire" class="mt-6 w-full">Voir mes publications</x-bouton>
    </x-carte-formulaire>
@endsection
