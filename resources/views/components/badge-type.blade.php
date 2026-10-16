@props(['type', 'sousType' => null])
@php
    /** @var \App\Enums\TypePublication $type */
    $libelle = match (true) {
        $type === \App\Enums\TypePublication::Emploi => 'Emploi',
        $sousType === \App\Enums\SousTypeService::Demande => 'Demande',
        $type === \App\Enums\TypePublication::Service => 'Service',
        default => 'Formation',
    };
    $icone = ['emploi' => '💼', 'service' => '🔧', 'formation' => '🎓'][$type->value];
@endphp
<span {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
    'bg-emploi-clair text-emploi' => $type === \App\Enums\TypePublication::Emploi,
    'bg-service-clair text-service' => $type === \App\Enums\TypePublication::Service,
    'bg-formation-clair text-formation' => $type === \App\Enums\TypePublication::Formation,
]) }}><span aria-hidden="true">{{ $icone }}</span>{{ $libelle }}</span>
