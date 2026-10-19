<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Opération refusée pour une raison métier (essai déjà utilisé, remontée trop récente…) ;
 * le message est affiché tel quel au membre.
 */
class OperationImpossibleException extends RuntimeException {}
