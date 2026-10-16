@foreach ($salve->publications as $publication)
    <li>{!! $rendu->rendre($publication) !!}</li>
@endforeach
