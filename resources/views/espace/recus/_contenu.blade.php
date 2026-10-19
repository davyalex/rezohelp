{{-- Contenu du reçu, partagé par la page imprimable et le PDF (styles en ligne pour dompdf). --}}
<div style="font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 13px; line-height: 1.5;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top;">
                <p style="margin: 0; font-size: 20px; font-weight: bold; color: #0E5E6F;">{{ config('app.name') }}</p>
                <div style="margin-top: 4px; font-size: 11px; color: #475569;">{!! $mentions !!}</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <p style="margin: 0; font-size: 16px; font-weight: bold;">Reçu n° {{ $commande->numero_recu }}</p>
                <p style="margin: 0;">Commande {{ $commande->numero }}</p>
                <p style="margin: 0;">Payée le {{ $commande->payee_le?->translatedFormat('j F Y \à H\hi') }}</p>
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 4px;"><strong>Client :</strong> {{ $nomTitulaire }}</p>
    @if ($commande->utilisateur?->telephone)
        <p style="margin: 0;"><strong>Téléphone :</strong> {{ app(\App\Services\TelephoneService::class)->afficher($commande->utilisateur->telephone, $commande->utilisateur->pays_code) }}</p>
    @endif
    @if ($commande->moyen_paiement)
        <p style="margin: 0;"><strong>Moyen de paiement :</strong> {{ ucfirst($commande->moyen_paiement) }} ({{ $commande->passerelle->libelle() }})</p>
    @endif

    <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="text-align: left; border-bottom: 2px solid #0f172a; padding: 6px 0;">Désignation</th>
                <th style="text-align: right; border-bottom: 2px solid #0f172a; padding: 6px 0;">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #cbd5e1;">
                    {{ $article->nom ?? 'Commande' }}
                    @if ($article?->duree_jours)
                        — {{ $article->duree_jours }} jours
                    @endif
                    @if ($commande->publication)
                        <br><span style="font-size: 11px; color: #475569;">Publication : {{ $commande->publication->titre }}</span>
                    @endif
                </td>
                <td style="padding: 8px 0; border-bottom: 1px solid #cbd5e1; text-align: right;">{{ \App\Support\Montant::fcfa($commande->montant_ttc_fcfa) }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; text-align: right;">Montant HT</td>
                <td style="padding: 4px 0; text-align: right;">{{ \App\Support\Montant::fcfa($commande->montant_ht_fcfa) }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; text-align: right;">TVA ({{ rtrim(rtrim((string) $commande->taux_tva, '0'), '.') }} %)</td>
                <td style="padding: 4px 0; text-align: right;">{{ \App\Support\Montant::fcfa($commande->montant_ttc_fcfa - $commande->montant_ht_fcfa) }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; text-align: right; font-weight: bold;">Total TTC</td>
                <td style="padding: 6px 0; text-align: right; font-weight: bold;">{{ \App\Support\Montant::fcfa($commande->montant_ttc_fcfa) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($commande->statut === \App\Enums\StatutCommande::Remboursee)
        <p style="margin-top: 16px; font-weight: bold; color: #b91c1c;">Cette commande a été remboursée.</p>
    @endif
</div>
