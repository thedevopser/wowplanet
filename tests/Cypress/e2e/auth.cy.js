describe('Authentification OAuth', () => {
    beforeEach(() => {
        cy.mockLogin('google'); // Simule le login Google
    });

  it('Affiche le menu utilisateur après connexion', () => {
        cy.get('[data-cy=user-avatar]').should('be.visible');
        cy.get('[data-cy=user-avatar]').click();
        cy.get('[data-cy=user-menu]').should('be.visible');
    });

  it('Se déconnecte correctement', () => {
        cy.get('[data-cy=user-avatar]').click();
        cy.get('[data-cy=user-menu]').should('be.visible');
        cy.contains('Se déconnecter').click();
        cy.get('[data-cy=user-avatar]').should('not.exist'); // Vérifie que l'utilisateur disparaît après logout
    });
});
