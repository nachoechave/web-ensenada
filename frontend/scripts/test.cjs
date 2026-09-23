const { spawnSync } = require('node:child_process');
const args = process.argv.slice(2).map((arg) => (arg === '--run' ? '--watch=false' : arg));
const result = spawnSync(
  process.execPath,
  [require.resolve('@angular/cli/bin/ng.js'), 'test', ...args],
  { stdio: 'inherit' },
);
if (result.error) console.error(result.error);
process.exit(result.status ?? 1);
