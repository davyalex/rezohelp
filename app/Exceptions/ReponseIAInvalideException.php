<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Réponse de l'IA inutilisable : refus, réponse tronquée, JSON invalide ou hors du schéma attendu.
 * Le job d'enrichissement échoue et la publication reste en attente de modération.
 */
class ReponseIAInvalideException extends RuntimeException {}
