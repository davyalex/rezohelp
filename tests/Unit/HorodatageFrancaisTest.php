<?php

use App\Models\Concerns\HorodatageFrancais;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

it('nomme les horodatages en français', function () {
    $modele = new class extends Model
    {
        use HorodatageFrancais, SoftDeletes;

        protected $table = 'essais';
    };

    expect($modele->getCreatedAtColumn())->toBe('cree_le')
        ->and($modele->getUpdatedAtColumn())->toBe('modifie_le')
        ->and($modele->getDeletedAtColumn())->toBe('supprime_le')
        ->and($modele->getQualifiedDeletedAtColumn())->toBe('essais.supprime_le');
});

it('renseigne cree_le et modifie_le à l’enregistrement', function () {
    $modele = new class extends Model
    {
        use HorodatageFrancais;

        protected $table = 'essais';
    };

    $modele->updateTimestamps();

    expect($modele->getAttributes())->toHaveKeys(['cree_le', 'modifie_le'])
        ->not->toHaveKeys(['created_at', 'updated_at']);
});
