const cmd = require('node-cmd');
const fs = require('fs-extra');
const co = require('co');
const archiver = require('archiver');
const pkg = fs.readJsonSync('./package.json');

/**
 * Run system command
 *
 * @param cmdString
 * @returns {Promise}
 */
const systemCmd = cmdString => {
  return new Promise((resolve) => {
    cmd.get(
      cmdString,
      (data, err, stderr) => {
        console.log(cmdString);
        console.log(data);
        if (err) {
          console.log(err);
        }
        if (stderr) {
          console.log(stderr);
        }
        resolve(data);
      }
    );
  });
}

const zipPromise = (src, dist) => {
  return new Promise((resolve, reject) => {
    const archive = archiver.create('zip', {});
    const output = fs.createWriteStream(dist);

    // listen for all archive data to be written
    output.on('close', () => {
      console.log(archive.pointer() + ' total bytes');
      console.log('Archiver has been finalized and the output file descriptor has closed.');
      resolve();
    });

    // good practice to catch this error explicitly
    archive.on('error', (err) => {
      reject(err);
    });

    archive.pipe(output);
    archive.directory(src).finalize();
  });
}

function *zipPromiseVersion (version) {
  yield zipPromise(`${version}/cpi`, `./build/${version}/cpi.zip`);
  yield zipPromise(`${version}/heteml`, `./build/${version}/heteml.zip`);
  yield zipPromise(`${version}/lolipop`, `./build/${version}/lolipop.zip`);
  yield zipPromise(`${version}/mamp`, `./build/${version}/mamp.zip`);
  yield zipPromise(`${version}/sakura`, `./build/${version}/sakura.zip`);
  yield zipPromise(`${version}/xampp`, `./build/${version}/xampp.zip`);
  yield zipPromise(`${version}/xserver`, `./build/${version}/xserver.zip`);
  yield zipPromise(`${version}/zenlogic`, `./build/${version}/zenlogic.zip`);
  yield zipPromise(`${version}/update`, `./build/${version}/update.zip`);
}

co(function* () {
  try {
    fs.mkdirsSync(`build`);
    fs.mkdirsSync(`build/28x`);
    fs.mkdirsSync(`build/29x`);
    fs.mkdirsSync(`build/210x`);
    yield zipPromiseVersion('28x');
    yield zipPromiseVersion('29x');
    yield zipPromiseVersion('210x');
    yield systemCmd('git add -A');
    yield systemCmd(`git commit -m "v${pkg.version}"`);
    yield systemCmd('git push');
  } catch (err) {
    console.log(err);
  }
});