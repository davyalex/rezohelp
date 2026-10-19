<?php

namespace App\Support;

/**
 * Jetons du gabarit HTML d'une carte. Le gabarit est partagé en cache par tous
 * les visiteurs ; ces jetons sont remplacés à chaque requête par les valeurs
 * fraîches (compteurs, date relative) et propres au visiteur (J'aime, enregistré, CSRF).
 */
final class JetonsCarte
{
    public const string NB_JAIME = '⟦nb_jaime⟧';

    public const string NB_COMMENTAIRES = '⟦nb_commentaires⟧';

    public const string AIME = '⟦aime⟧';

    public const string ENREGISTRE = '⟦enregistre⟧';

    public const string DEPUIS = '⟦depuis⟧';

    public const string CSRF = '⟦csrf⟧';

    public const string PREVENTION = '⟦prevention⟧';

    /** Étiquette « Sponsorisé » / « À la une » d'une carte mise en avant (vide sinon). */
    public const string ETIQUETTE = '⟦etiquette⟧';

    /**
     * Retire les crochets des jetons d'un texte saisi par un membre,
     * pour qu'il ne puisse jamais être confondu avec un jeton.
     */
    public static function neutraliser(?string $texte): string
    {
        return str_replace(['⟦', '⟧'], '', (string) $texte);
    }
}
