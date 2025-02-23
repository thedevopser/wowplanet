Cypress.Commands.add('mockLogin', (provider = 'google') => {
    cy.fixture('user').then((user) => {
      // Interception de la redirection vers OAuth
        cy.intercept('GET', ` / connect / ${provider}`, (req) => {
            console.log(`[Cypress] Redirection OAuth interceptée : ${req.url}`);
            req.reply({ statusCode: 302, headers: { location: ` / connect / ${provider} / check` } });
        }).as('mockOAuthRedirect');

    // Interception du retour du provider (callback en GET)
    cy.intercept('GET', ` / connect / ${provider} / check`, (req) => {
        console.log(`[Cypress] Callback OAuth intercepté : ${req.url}`);
        req.reply({ statusCode: 200, body: user });
      }).as('mockOAuthCallback');

    // Simulation de session Symfony
    cy.setCookie('PHPSESSID', 'fake-session-id');

    // Visiter la page de connexion et cliquer sur le bouton
    cy.visit('/login');
    cy.get(`[data - cy = login - ${provider}]`).click();

    // Vérifier que la redirection et le callback ont bien été interceptés
    cy.wait('@mockOAuthRedirect');
    cy.wait('@mockOAuthCallback');
    });
});
