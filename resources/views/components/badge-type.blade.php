@props(['type', 'sousType' => null])
@php
    /** @var \App\Enums\TypePublication $type */
    $libelle = match (true) {
        $type === \App\Enums\TypePublication::Emploi => 'Emploi',
        $sousType === \App\Enums\SousTypeService::Demande => 'Demande de prestation',
        $type === \App\Enums\TypePublication::Service => 'Service',
        default => 'Formation',
    };
    $demande = $sousType === \App\Enums\SousTypeService::Demande;
    $icone = $demande ? '🙋' : ['emploi' => '💼', 'service' => '🔧', 'formation' => '🎓'][$type->value];
@endphp
<span {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
    'bg-emploi-clair text-emploi' => $type === \App\Enums\TypePublication::Emploi,
    // Une demande se voit au premier coup d'œil : pastille pleine (D-069).
    'bg-service text-white' => $demande,
    'bg-service-clair text-service' => $type === \App\Enums\TypePublication::Service && ! $demande,
    'bg-formation-clair text-formation' => $type === \App\Enums\TypePublication::Formation,
]) }}><span aria-hidden="true">{{ $icone }}</span>{{ $libelle }}</span>
