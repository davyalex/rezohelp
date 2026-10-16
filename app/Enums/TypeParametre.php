<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelles;

/**
 * Type de la valeur d'un paramètre (§13.G).
 */
enum TypeParametre: string
{
    use AvecLibelles;

    case Entier = 'int';
    case Booleen = 'bool';
    case Texte = 'texte';
    case Html = 'html';
    case Json = 'json';

    public function libelle(): string
    {
        return match ($this) {
            self::Entier => 'Nombre entier',
            self::Booleen => 'Oui / non',
            self::Texte => 'Texte',
            self::Html => 'Texte mis en forme',
            self::Json => 'Liste ou tableau (JSON)',
        };
    }
}
