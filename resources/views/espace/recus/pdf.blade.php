<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $commande->numero_recu }}</title>
    <style>
        @page { margin: 28px; }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('espace.recus._contenu')
</body>
</html>
