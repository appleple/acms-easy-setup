const fs = require('fs-extra');
const archiver = require('archiver');
const path = require('path');

const root = path.resolve(__dirname, '..');

const zip = (src, dist) => {
  return new Promise((resolve, reject) => {
    const archive = archiver.create('zip', {});
    const output = fs.createWriteStream(dist);

    output.on('close', () => {
      console.log(`${dist}: ${archive.pointer()} bytes`);
      resolve();
    });

    archive.on('error', reject);
    archive.pipe(output);
    archive.directory(src).finalize();
  });
};

(async () => {
  const buildDir = path.join(root, 'build');
  fs.mkdirsSync(buildDir);

  await zip(path.join(root, 'packages', 'install', 'install'), path.join(buildDir, 'install.zip'));
  await zip(path.join(root, 'packages', 'update', 'update'), path.join(buildDir, 'update.zip'));

  console.log('Build complete.');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
