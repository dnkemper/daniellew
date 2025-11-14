// cypress/integration/sso-login.spec.js
describe('SSO Login Test', () => {
  it('Initiates SimpleSAML authentication', () => {
    cy.task('ssoLogin', {
      username: 'your-username',
    }).then((result) => {
      expect(result.success).to.be.true;
      // Check if the user is redirected to SAML login page
      cy.url().should('include', 'sso.example.com/login');
    });
  });
});
