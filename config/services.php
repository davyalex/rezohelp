<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Services tiers
    |--------------------------------------------------------------------------
    |
    | Identifiants des services externes. Ils ne sont lus que par les drivers
    | réels (App\Services\*) ; en local et en test, les drivers fake / log
    | n'en ont pas besoin. Ne jamais commiter de valeur réelle.
    |
    */

    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'secret' => env('CINETPAY_SECRET'),
        'url' => env('CINETPAY_URL', 'https://api-checkout.cinetpay.com/v2'),
    ],

    'orange_sms' => [
        'client_id' => env('ORANGE_SMS_CLIENT_ID'),
        'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
        'expediteur' => env('ORANGE_SMS_EXPEDITEUR'),
        'nom_expediteur' => env('ORANGE_SMS_NOM_EXPEDITEUR', 'Rezohelp'),
        'url' => env('ORANGE_SMS_URL', 'https://api.orange.com'),
        'cout_fcfa' => (int) env('ORANGE_SMS_COUT_FCFA', 25),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'expediteur' => env('TWILIO_EXPEDITEUR'),
        'url' => env('TWILIO_URL', 'https://api.twilio.com/2010-04-01'),
        'cout_fcfa' => (int) env('TWILIO_COUT_FCFA', 50),
    ],

    'google_drive' => [
        'fichier_identifiants' => env('GOOGLE_DRIVE_FICHIER_IDENTIFIANTS'),
        'dossier_id' => env('GOOGLE_DRIVE_DOSSIER_ID'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'modele' => env('CLAUDE_MODELE', 'claude-haiku-5-5'),
        'url' => env('CLAUDE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('CLAUDE_API_VERSION', '2023-06-01'),
        // Analyse courte : effort bas ; max_tokens laisse la place à la réflexion (active par défaut sur Haiku 5.5).
        'effort' => env('CLAUDE_EFFORT', 'low'),
        'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 4000),
        'tentatives' => (int) env('CLAUDE_TENTATIVES', 3),
        'attente_ms' => (int) env('CLAUDE_ATTENTE_MS', 1000),
        'delai' => (int) env('CLAUDE_DELAI', 60),
        // Estimation du coût affichée dans l'admin (FCFA par million de tokens)
        'cout_entree_fcfa_par_million' => (int) env('CLAUDE_COUT_ENTREE_FCFA', 600),
        'cout_sortie_fcfa_par_million' => (int) env('CLAUDE_COUT_SORTIE_FCFA', 3000),
    ],

];
