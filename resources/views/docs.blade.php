<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WowPlanet — Documentation (DEV)</title>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/docsify@4/lib/themes/vue.css">
    <style>
        :root { --theme-color: #3eaf7c; }
        .app-name-link { font-weight: 700; }
        .sidebar-nav li > a[href="/docs/api"] { color: #f59e0b; }
        nav.app-nav { display: none; }
        .dev-badge {
            display: inline-block;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            vertical-align: middle;
            margin-left: 4px;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div id="app"></div>
    <script>
        window.$docsify = {
            name: 'WowPlanet <span class="dev-badge">DEV</span>',
            repo: false,
            homepage: 'index.md',
            loadSidebar: '_sidebar.md',
            subMaxLevel: 2,
            basePath: '/docs/',
            auto2top: true,
            search: {
                placeholder: 'Rechercher dans la documentation...',
                noData: 'Aucun résultat.',
                depth: 3,
            },
        }
    </script>
    <script src="//cdn.jsdelivr.net/npm/docsify@4/lib/docsify.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/docsify@4/lib/plugins/search.min.js"></script>
</body>
</html>
