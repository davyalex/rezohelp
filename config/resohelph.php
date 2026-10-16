<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Drivers des services externes
    |--------------------------------------------------------------------------
    |
    | Chaque service externe est utilisé derrière une interface (App\Contracts).
    | En local et en test : fake / log. Les identifiants des drivers réels sont
    | dans config/services.php.
    |
    */

    'paiement' => [
        'driver' => env('PAIEMENT_DRIVER', 'fake'),
        'fake' => [
            'secret' => env('PAIEMENT_FAKE_SECRET', 'secret-paiement-fake'),
        ],
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'canal_log' => env('SMS_LOG_CHANNEL', 'sms'),
    ],

    'veille' => [
        'driver' => env('VEILLE_DRIVE_DRIVER', 'fake'),
        'fake' => [
            'dossier' => env('VEILLE_FAKE_DOSSIER', base_path('tests/Fixtures/veille')),
        ],
    ],

    'ia' => [
        'driver' => env('IA_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compte administrateur initial (seeder)
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'telephone' => env('ADMIN_TELEPHONE'),
        'pays_code' => env('ADMIN_PAYS_CODE', 'CI'),
        'mot_de_passe' => env('ADMIN_MOT_DE_PASSE'),
        'nom' => env('ADMIN_NOM', 'Administrateur'),
    ],

];
