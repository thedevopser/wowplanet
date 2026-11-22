<template>
    <EditorialNotice tone="info">
        This is a translation provided for convenience. The French version prevails in case of any discrepancy.
    </EditorialNotice>

    <EditorialNotice>
        WowPlanet is an unofficial fan site. It is in no way linked to, affiliated with or endorsed by Blizzard Entertainment, Inc. World of Warcraft and Blizzard Entertainment are registered trademarks of Blizzard Entertainment, Inc.
    </EditorialNotice>

    <section id="responsable-du-traitement">
        <h2>1. Data controller</h2>
        <p>
            The website <strong>wowplanet.fr</strong> (&ldquo;WowPlanet&rdquo;) is published by WowPlanet.
            For any question about the protection of your data, you can reach us on our
            <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">Discord server</a>.
        </p>
    </section>

    <section id="donnees-collectees">
        <h2>2. Data collected</h2>
        <p>
            WowPlanet collects the following data when you log in with Battle.net:
        </p>
        <ul>
            <li>Your Battle.net identifier (BattleTag)</li>
            <li>Your numeric Battle.net identifier</li>
            <li>The list of your World of Warcraft characters and their progress data (quests, achievements, mounts, pets, professions)</li>
        </ul>
        <p>
            This data is retrieved exclusively through the official Blizzard Entertainment API.
            WowPlanet collects no other data (email address, password, payment details).
        </p>
    </section>

    <section id="base-legale-et-finalites">
        <h2>3. Legal basis and purposes</h2>
        <p>
            Your data is processed on the basis of your <strong>consent</strong>, given when you choose to log in with Battle.net OAuth.
            The data is used only to:
        </p>
        <ul>
            <li>Display your progress in World of Warcraft</li>
            <li>Compare your accomplishments with the complete game database</li>
            <li>Allow characters to be looked up</li>
        </ul>
    </section>

    <section id="duree-de-conservation">
        <h2>4. Retention period</h2>
        <p>
            Your session data is kept for the duration of your browsing session.
            Character data is cached temporarily to improve the performance of the service.
            You can ask for your data to be deleted at any time.
        </p>
    </section>

    <section id="vos-droits-rgpd">
        <h2>5. Your rights (GDPR)</h2>
        <p>
            Under the General Data Protection Regulation (GDPR), you have the following rights:
        </p>
        <ul>
            <li><strong>Right of access</strong>: obtain a copy of your personal data</li>
            <li><strong>Right to rectification</strong>: correct inaccurate data</li>
            <li><strong>Right to erasure</strong>: ask for your data to be deleted</li>
            <li><strong>Right to data portability</strong>: receive your data in a structured format</li>
            <li><strong>Right to object</strong>: object to the processing of your data</li>
            <li><strong>Right to withdraw consent</strong>: withdraw your consent at any time</li>
        </ul>
        <p>
            To exercise these rights, contact us on our
            <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer">Discord server</a>.
            You also have the right to lodge a complaint with the
            <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">CNIL</a>, the French data protection authority.
        </p>
    </section>

    <section id="cookies">
        <h2>6. Cookies</h2>
        <p>
            WowPlanet only uses cookies that are strictly necessary for the service to work
            (session cookie, CSRF token), a theme preference cookie, <code>wowplanet-theme</code>,
            which remembers for one year your choice between the light, dark or system theme so that each page is displayed in that theme straight away,
            and a language cookie, <code>wowplanet-locale</code>, which remembers in the same way whether you chose French or English.
            No advertising or tracking cookie is used.
            Under the applicable law, these cookies do not require your prior consent.
        </p>
    </section>

    <section id="mesure-daudience">
        <h2>7. Audience measurement</h2>
        <p>
            WowPlanet measures its traffic with <strong>Umami</strong>, an analytics tool hosted
            by WowPlanet itself (<code>umami.wowplanet.fr</code>). It sets no cookie and does not follow you
            from one site to another: it counts page views in aggregate (page, referring site,
            browser, country). Your IP address is only used to infer the country and an anonymous visit identifier,
            and is not stored. These statistics are neither sold nor shared.
        </p>
    </section>

    <section id="infobulles-wowhead">
        <h2>8. Wowhead tooltips</h2>
        <p>
            To show the details of an item, mount or achievement when you hover over a link, WowPlanet loads
            the tooltip script of <strong>Wowhead</strong> from <code>wow.zamimg.com</code>. As with any content
            loaded from another site, your browser then sends Wowhead your <strong>IP address</strong> and
            technical information (browser, page viewed). Wowhead processes this data under its own
            privacy policy, over which WowPlanet has no control.
        </p>
    </section>

    <section id="transfert-de-donnees">
        <h2>9. Data transfers</h2>
        <p>
            Progress data is retrieved from Blizzard Entertainment&rsquo;s servers
            through their official API. Apart from this exchange and the loading of the Wowhead tooltips described above,
            WowPlanet sends no personal data to third parties.
        </p>
    </section>

    <section id="securite">
        <h2>10. Security</h2>
        <p>
            WowPlanet applies technical and organisational measures to protect your personal data
            against unauthorised access, loss or alteration. The site uses HTTPS to encrypt all exchanges.
        </p>
    </section>
</template>

<script setup>
import EditorialNotice from '../../components/EditorialNotice.vue';
</script>
