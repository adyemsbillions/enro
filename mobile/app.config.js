// Extends app.json. Kept small on purpose: anything static belongs in app.json.
const fs = require('fs');
const path = require('path');

module.exports = ({ config }) => {
  // Firebase: download google-services.json from the Firebase console (Android app, package app.enro.mobile)
  // and put it next to this file. Without it the app still works, but only local reminders are shown.
  const firebase = fs.existsSync(path.join(__dirname, 'google-services.json'));
  return {
    ...config,
    android: { ...config.android, ...(firebase ? { googleServicesFile: './google-services.json' } : {}) },
    plugins: [...(config.plugins ?? []), './plugins/withReleaseSigning'],
  };
};
