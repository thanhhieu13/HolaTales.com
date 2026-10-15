const {defineConfig} = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/browser', workers: 1, timeout: 90000,
  use: {baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000', trace:'retain-on-failure', screenshot:'only-on-failure'},
  projects: [320,375,768,1280].map(width=>({name:`width-${width}`,use:{viewport:{width,height:800}}})),
});
