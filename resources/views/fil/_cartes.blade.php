@foreach ($salve->publications as $publication)
    @isset($sponsorisees[$loop->index])
        {!! $rendu->rendreMiseEnAvant($sponsorisees[$loop->index]) !!}
    @endisset
    <li data-carte>{!! $rendu->rendre($publication) !!}</li>
@endforeach
