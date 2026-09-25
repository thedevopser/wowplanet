<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Maintenance en cours - WowPlanet</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    {{-- The application stylesheet may be the very thing being deployed: this page carries its own,
         with the values of the site tokens (kept equal by resources/js/tests/maintenancePage.test.js). --}}
    <style>
        :root {
            --wp-background: #f7f4ec;
            --wp-surface: #ffffff;
            --wp-border: #e2dbcb;
            --wp-text: #1b1e27;
            --wp-text-muted: #4f5566;
            --wp-text-subtle: #5f6475;
            --wp-accent: #7a5d0e;
            color-scheme: light;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --wp-background: #0b0f1a;
                --wp-surface: #121826;
                --wp-border: #263047;
                --wp-text: #e8e4d8;
                --wp-text-muted: #a7adbb;
                --wp-text-subtle: #8a92a5;
                --wp-accent: #d4a844;
                color-scheme: dark;
            }
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--wp-background);
            color: var(--wp-text);
            font-family: 'Inter Variable', ui-sans-serif, system-ui, sans-serif;
        }

        main {
            display: flex;
            width: 100%;
            max-width: 32rem;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
        }

        .logo {
            width: 5rem;
            height: 5rem;
            border: 1px solid var(--wp-border);
            border-radius: 10px;
        }

        .card {
            width: 100%;
            padding: 2.5rem 2rem;
            border: 1px solid var(--wp-border);
            border-left: 4px solid var(--wp-accent);
            border-radius: 10px;
            background: var(--wp-surface);
            text-align: center;
        }

        .icon {
            width: 2.5rem;
            height: 2.5rem;
            margin-bottom: 1.25rem;
            color: var(--wp-accent);
        }

        h1 {
            margin-bottom: 0.75rem;
            font-family: 'Cinzel', 'Times New Roman', serif;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .message {
            margin-bottom: 2rem;
            color: var(--wp-text-muted);
            line-height: 1.6;
        }

        .progress {
            height: 4px;
            overflow: hidden;
            border-radius: 9999px;
            background: var(--wp-border);
        }

        .progress span {
            display: block;
            width: 40%;
            height: 100%;
            border-radius: 9999px;
            background: var(--wp-accent);
            animation: progress 2s ease-in-out infinite;
        }

        footer {
            color: var(--wp-text-subtle);
            font-size: 0.8125rem;
            line-height: 1.6;
            text-align: center;
        }

        @keyframes progress {
            0% { transform: translateX(-100%); }
            50%, 100% { transform: translateX(250%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .progress span { width: 100%; animation: none; opacity: 0.5; }
        }
    </style>
</head>
<body>
    <main>
        <img src="/images/logo.png" alt="WowPlanet" width="80" height="80" class="logo">

        <div class="card">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.049.58.025 1.193-.14 1.743" />
            </svg>

            <h1>Maintenance en cours</h1>

            <p class="message">
                Nous effectuons une mise à jour pour améliorer votre expérience.
                Le site sera de retour très bientôt&nbsp;!
            </p>

            <div class="progress" role="progressbar" aria-label="Mise à jour en cours"><span></span></div>
        </div>

        <footer>
            <p>&copy; {{ date('Y') }} WowPlanet. Tous droits réservés.</p>
            <p>Site fan non officiel, sans lien ni affiliation avec Blizzard Entertainment.</p>
        </footer>
    </main>
</body>
</html>
