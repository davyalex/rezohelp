@foreach ($salve->publications as $publication)
    @isset($sponsorisees[$loop->index])
        {!! $rendu->rendreMiseEnAvant($sponsorisees[$loop->index]) !!}
    @endisset
    <li data-carte>{!! $rendu->rendre($publication) !!}</li>
    {{-- Bannière publicitaire entre la 8e et la 9e carte de chaque salve (rien si aucune annonce). --}}
    @if ($loop->iteration === 8)
        <li><x-publicite emplacement="fil_entre_cartes" /></li>
    @endif
@endforeach
