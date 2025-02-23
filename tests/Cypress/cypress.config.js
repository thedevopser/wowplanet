const { defineConfig } = require('cypress')

module.exports = defineConfig({
    e2e: {
        specPattern: 'tests/Cypress/e2e/**/*.cy.js',
        supportFile: 'tests/Cypress/support/index.js',
        fixturesFolder: 'tests/Cypress/fixtures',
        screenshotsFolder: 'tests/Cypress/rapports/screenshots',
        baseUrl: 'https://wowplanet-test.dev.local',
        videosFolder: 'tests/Cypress/rapports/videos',
        viewportWidth: 1920,
        viewportHeight: 1080,
        setupNodeEvents(on, config) {

        },
    },
})