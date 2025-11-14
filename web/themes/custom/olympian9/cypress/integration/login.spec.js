// cypress/integration/login.spec.js
describe('Login Test', () => {
  it('Logs in to Drupal and visits a protected page', () => {
    cy.task('loginToDrupal', {
      username: 'your-username',
      password: 'your-password',
    }).then((result) => {
      expect(result.success).to.be.true;
      // Visit a protected page and assert logged-in state
      cy.visit('/protected-page');
      cy.contains('Welcome');
    });
  });
});
