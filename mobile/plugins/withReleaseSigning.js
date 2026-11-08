// Signs release builds with Enro's own key instead of the shared debug key.
// The key lives in mobile/credentials/ (git-ignored). Back it up: every future APK update must be signed with
// the same key, or phones will refuse to install it over the old version.
//
// credentials/keystore.properties:
//   storeFile=enro-release.keystore
//   storePassword=...
//   keyAlias=enro
//   keyPassword=...
const { withAppBuildGradle } = require('expo/config-plugins');

const MARKER = '// enro-release-signing';

module.exports = function withReleaseSigning(config) {
  return withAppBuildGradle(config, (cfg) => {
    let gradle = cfg.modResults.contents;
    if (gradle.includes(MARKER)) return cfg;

    const loader = `${MARKER}
def enroSigningFile = rootProject.file("../credentials/keystore.properties")
def enroSigning = new Properties()
if (enroSigningFile.exists()) { enroSigningFile.withInputStream { enroSigning.load(it) } }
`;
    gradle = gradle.replace(/\nandroid \{/, `\n${loader}\nandroid {`);

    gradle = gradle.replace(
      /signingConfigs \{\n(\s+)debug \{/,
      (m, indent) => `signingConfigs {
${indent}release {
${indent}    if (enroSigning['storeFile']) {
${indent}        storeFile rootProject.file("../credentials/" + enroSigning['storeFile'])
${indent}        storePassword enroSigning['storePassword']
${indent}        keyAlias enroSigning['keyAlias']
${indent}        keyPassword enroSigning['keyPassword']
${indent}    }
${indent}}
${indent}debug {`
    );

    gradle = gradle.replace(
      /(release \{[^}]*?)signingConfig signingConfigs\.debug/,
      '$1signingConfig enroSigning[\'storeFile\'] ? signingConfigs.release : signingConfigs.debug'
    );

    cfg.modResults.contents = gradle;
    return cfg;
  });
};
