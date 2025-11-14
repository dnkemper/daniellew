// cypress/plugins/login-plugin.js
module.exports = (on, config) => {
  // Custom command to log in to your Drupal site
  on('task', {
    loginToDrupal: (credentials) => {
      // Assuming credentials contain 'username' and 'password'
      // Implement your login logic here
      // Example: Use Cypress commands to interact with the login form
      cy.visit('/user/login');
      cy.get('#edit-name').type(credentials.username);
      cy.get('#edit-pass').type(credentials.password);
      cy.get('#edit-submit').click();
      // Return a result indicating success or failure
      return { success: true };
    },
  });
};
