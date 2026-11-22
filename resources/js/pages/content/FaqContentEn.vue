<template>
    <section id="quest-ce-que-wowplanet">
        <h2>What is WowPlanet?</h2>
        <p>
            WowPlanet is a <strong>free, unofficial fan site</strong> that tracks your World of Warcraft progress.
            It shows the progress of your characters in detail and compares your accomplishments with the complete game database.
        </p>
        <p>
            The data comes from the official Blizzard API and covers:
        </p>
        <ul class="sm:columns-3">
            <li>Mounts (1,500+)</li>
            <li>Pets (2,100+)</li>
            <li>Achievements (8,600+)</li>
            <li>Quests (21,000+)</li>
            <li>Decor</li>
            <li>Professions &amp; Reputations</li>
        </ul>
    </section>

    <section id="comment-importer-mes-personnages">
        <h2>How do I import my characters?</h2>
        <p>
            Click <strong>&ldquo;Log in with Battle.net&rdquo;</strong> at the top of the site.
            Once you are logged in, <strong>all your characters</strong> are imported and synced automatically.
            No manual setup is needed.
        </p>
        <EditorialNotice tone="info">
            <strong>Across characters:</strong>
            Achievements and reputations are tracked at account level (<em>account-wide</em>).
            For quests and profession recipes, WowPlanet tells you which other character has already completed an entry
            with the note <strong>&ldquo;available on another character&rdquo;</strong>.
        </EditorialNotice>
    </section>

    <section id="quest-ce-que-le-score">
        <h2>What is the account score?</h2>
        <p>
            The account score is an <strong>overall rating out of 100</strong> that measures your progress
            across all your characters. Since <strong>formula v2</strong>, it is computed
            over <strong>9 dimensions</strong>:
        </p>
        <ul class="sm:columns-3">
            <li>Quests</li>
            <li>Achievements</li>
            <li>Reputations</li>
            <li>Raids</li>
            <li>Mounts</li>
            <li>Appearances</li>
            <li>Pets</li>
            <li>Decor</li>
            <li>Professions</li>
        </ul>
        <p>
            <strong>Appearances</strong> and <strong>raids</strong> came with v2: if your rating went down, the bar simply went up.
            Raids are rated at the <strong>highest difficulty reached</strong>, boss by boss:
            a raid cleared in Normal is worth half of the same raid cleared in Mythic.
        </p>
        <p>
            A dimension <strong>without any data</strong> (you do not raid, you have no profession) does not count as zero:
            it leaves the calculation and its weight is shared out among the others.
            <strong>PvP is not part</strong> of the score: it only concerns some players and would unfairly penalize PvE profiles.
            You can still see it in its tab and in the leaderboards.
        </p>
        <p>
            The result is shown as a <strong>radar chart</strong> and can be <strong>shared on Discord</strong>.
            Find it after logging in under <strong>My account</strong>, in the <strong>&ldquo;Account score&rdquo;</strong> tab.
        </p>
    </section>

    <section id="peut-on-utiliser-le-site">
        <h2>Can I use the site without logging in?</h2>
        <p>
            <strong>Yes, absolutely.</strong> Several features are available without a Battle.net login:
        </p>
        <ul>
            <li>
                The <a href="/en/database">complete database</a> (mounts, achievements, quests, pets, decor, professions)
            </li>
            <li>
                The <a href="/en/pvp-leaderboards">PvP leaderboards</a> of the current season
            </li>
            <li>
                The public profile of a character, from its link or from the PvP leaderboards
            </li>
        </ul>
        <p>
            A Battle.net login is only needed to import your own characters,
            see your account score and manage your tasks.
        </p>
    </section>

    <section id="comment-fonctionnent-les-taches-quotidiennes">
        <h2>How do daily tasks work?</h2>
        <p>
            WowPlanet lets you create <strong>custom tasks</strong> for each of your characters
            to track your recurring goals (Mythic Keystone vaults, world quests, and so on).
        </p>
        <ul>
            <li>
                <strong>Daily</strong>: reset automatically every day at 4:00 UTC
            </li>
            <li>
                <strong>Weekly</strong>: reset automatically every Wednesday at 4:00 UTC
            </li>
            <li>
                <strong>Monthly</strong>: reset automatically on the 1st of each month at 4:00 UTC
            </li>
        </ul>
        <p>
            This is the time of the Blizzard reset in Europe, all year round: 5:00 in Paris in winter, 6:00 in summer.
        </p>
        <p>
            Open your tasks with the <strong>button at the bottom right</strong> of the screen.
            A counter shows how many tasks are pending.
        </p>
    </section>
</template>

<script setup>
import EditorialNotice from '../../components/EditorialNotice.vue';
</script>
