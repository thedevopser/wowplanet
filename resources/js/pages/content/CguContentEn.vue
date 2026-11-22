<template>
    <EditorialNotice tone="info">
        This is a translation provided for convenience. The French version prevails in case of any discrepancy.
    </EditorialNotice>

    <EditorialNotice>
        WowPlanet is an unofficial fan site. It is in no way linked to, affiliated with or endorsed by Blizzard Entertainment, Inc. World of Warcraft and Blizzard Entertainment are registered trademarks of Blizzard Entertainment, Inc.
    </EditorialNotice>

    <section id="objet">
        <h2>1. Purpose</h2>
        <p>
            These Terms of Use (the &ldquo;Terms&rdquo;) set out how the website <strong>wowplanet.fr</strong> (&ldquo;WowPlanet&rdquo;) may be accessed and used.
            By accessing the site, you accept these Terms without reservation.
        </p>
    </section>

    <section id="description-du-service">
        <h2>2. Description of the service</h2>
        <p>
            WowPlanet is a free progress tracker for the game World of Warcraft. The service lets users:
        </p>
        <ul>
            <li>View the progress of their characters (quests, achievements, mounts, pets, professions)</li>
            <li>Compare their accomplishments with the complete game database</li>
            <li>View the PvP leaderboards of the current season</li>
        </ul>
        <p>
            Game data is retrieved exclusively through the official Blizzard Entertainment API.
        </p>
    </section>

    <section id="acces-au-service">
        <h2>3. Access to the service</h2>
        <p>
            Access to the site is free and open to everyone. Some features require logging in with Battle.net OAuth.
            WowPlanet reserves the right to suspend or discontinue the service at any time, without notice or compensation.
        </p>
    </section>

    <section id="propriete-intellectuelle">
        <h2>4. Intellectual property</h2>
        <p>
            World of Warcraft, Blizzard Entertainment, Battle.net and all associated names, characters, images and
            elements are trademarks and/or property of Blizzard Entertainment, Inc.
        </p>
        <p>
            WowPlanet is an <strong>unofficial fan site</strong> created by enthusiasts.
            It is in no way linked to, affiliated with, endorsed or sponsored by Blizzard Entertainment, Inc.
            Game names, images and data are used under <em>fair use</em> and the terms of the Blizzard API.
        </p>
        <p>
            The source code, design and original content of WowPlanet belong to WowPlanet
            and may not be reproduced without permission.
        </p>
    </section>

    <section id="responsabilite-et-limitations">
        <h2>5. Liability and limitations</h2>
        <p>
            WowPlanet strives to provide accurate and up-to-date information, but cannot be held liable for:
        </p>
        <ul>
            <li>Errors or omissions in the data displayed</li>
            <li>Temporary or permanent unavailability of the Blizzard API</li>
            <li>Direct or indirect damage arising from the use of the site</li>
            <li>The content of third-party sites linked from WowPlanet (Wowhead, Blizzard, etc.)</li>
        </ul>
    </section>

    <section id="donnees-personnelles">
        <h2>6. Personal data</h2>
        <p>
            How personal data is handled is described in our
            <Link href="/en/privacy">Privacy policy</Link>.
            By using the site, you acknowledge that you have read this policy.
        </p>
    </section>

    <section id="comportement-des-utilisateurs">
        <h2>7. User conduct</h2>
        <p>
            Users agree not to misuse the site, and in particular:
        </p>
        <ul>
            <li>Not to attempt unauthorised access to the site&rsquo;s systems</li>
            <li>Not to overload the service with excessive automated requests</li>
            <li>Not to use the site for unlawful purposes</li>
        </ul>
    </section>

    <section id="modification-des-cgu">
        <h2>8. Changes to these terms</h2>
        <p>
            WowPlanet reserves the right to change these Terms at any time.
            Changes take effect as soon as they are published on the site.
            Continued use of the site after a change means you accept the new Terms.
        </p>
    </section>

    <section id="droit-applicable">
        <h2>9. Governing law</h2>
        <p>
            These Terms are governed by French law.
            In the event of a dispute, the parties will seek an amicable solution.
            Failing that, the French courts shall have sole jurisdiction.
        </p>
    </section>

    <section id="contact">
        <h2>10. Contact</h2>
        <p>
            For any question about these Terms, you can reach us on our
            <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">Discord server</a>.
        </p>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import EditorialNotice from '../../components/EditorialNotice.vue';
</script>
