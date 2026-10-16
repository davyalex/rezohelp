<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Le compte ne peut pas publier (restreint, suspendu…) ; le message est affiché au membre.
 */
class PublicationImpossibleException extends RuntimeException {}
