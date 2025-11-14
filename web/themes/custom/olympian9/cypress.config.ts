import { defineConfig } from 'cypress'

export default defineConfig({
  projectId: "iqm68d",
  e2e: {
    baseUrl: 'https://default.ddev.site',
  },
  setupNodeEvents(on, config) {
    // Load your custom login plugin
    require('./cypress/plugins/login-plugin')(on, config);

    // Load your custom SSO login plugin
    require('./cypress/plugins/sso-login-plugin')(on, config);
  },
})
