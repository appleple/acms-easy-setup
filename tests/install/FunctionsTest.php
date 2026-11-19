<?php
// setup.php の関数単位のテスト

function updateJsonFixture(array $versions)
{
    return put('update.json', json_encode(array('versions' => $versions)));
}
function pkg($v, $min, $max, $suffix = '_update2x')
{
    return array(
        'php_min_version' => $min,
        'php_max_version' => $max,
        'download' => "http://example.test/_package/$v/acms{$v}{$suffix}.zip",
        'root_dir' => "acms{$v}{$suffix}/ablogcms",
    );
}
function sampleJson()
{
    return updateJsonFixture(array(
        array('version' => '3.2.40', 'packages' => array(pkg('3.2.40', '8.1.0', '8.5.x'))),
        array('version' => '3.1.81', 'packages' => array(pkg('3.1.81', '7.3.0', '8.3.x'))),
        array('version' => '3.0.70', 'packages' => array(pkg('3.0.70', '7.2.5', '8.1.x'))),
        array('version' => '2.11.78', 'packages' => array(pkg('2.11.78', '7.1.0', '7.4.x', '_update2x_php7.1'))),
    ));
}

// ---------- PHP 対応表（update.php と同一であること） ----------
test('PHP 対応表は update.php と setup.php で完全に一致している', function () {
    $extract = function ($file) {
        $src = file_get_contents($file);
        preg_match('/^function acms_known_php_range\(.*?^}/ms', $src, $m);
        return $m[0];
    };
    $root = __DIR__ . '/../../packages';
    $update = $extract($root . '/update/update.php');
    $setup = $extract($root . '/install/setup.php');
    assertTrue($update !== '', 'update.php に acms_known_php_range が無い');
    assertSame($update, $setup, '対応表を変更したら update.php と setup.php の両方を更新すること');
});
test('acms_known_php_range: 3.x の各区間', function () {
    $cases = array(
        '3.0.0'  => array('7.2.5', '8.0.999'),
        '3.0.12' => array('7.2.5', '8.1.999'),
        '3.1.0'  => array('7.2.5', '8.1.999'),
        '3.1.7'  => array('7.3.0', '8.1.999'),
        '3.1.14' => array('7.3.0', '8.3.999'),
        '3.2.40' => array('8.1.0', '8.5.999'),
    );
    foreach ($cases as $version => $range) {
        assertSame($range, acms_known_php_range($version), $version);
    }
    assertSame(null, acms_known_php_range('3.3.0'));
});

// ---------- fetch_acms_package_info ----------
test('fetch: 最新版（インストール用はフルパッケージ名）', function () {
    $r = fetch_acms_package_info('', true, '8.3.5', sampleJson());
    assertSame('3.2.40', $r['version']);
    assertSame('https://example.test/_package/3.2.40/acms3.2.40.zip', $r['download']);
    assertSame('acms3.2.40/ablogcms', $r['root_dir']);
});
test('fetch: アップデート用はそのまま、PHP 7.2.34 で 3.1.0〜3.1.6 に対応', function () {
    $r = fetch_acms_package_info('3.1.6', false, '7.2.34', sampleJson());
    assertSame('3.1.6', $r['version']);
    assertSame('https://example.test/_package/3.1.6/acms3.1.6_update2x.zip', $r['download']);
    assertSame(null, fetch_acms_package_info('3.1.7', true, '7.2.34', sampleJson())['download']);
});
test('fetch: 完全一致・major.minor 表記の PHP', function () {
    assertSame('3.1.81', fetch_acms_package_info('3.1.81', true, '8.1', sampleJson())['version']);
});
test('fetch: 表に無い系列は update.json の範囲を使う（2.x）', function () {
    assertTrue(fetch_acms_package_info('2.11.78', false, '7.4.1', sampleJson())['download'] !== null);
    assertSame(null, fetch_acms_package_info('2.11.78', false, '8.0.0', sampleJson())['download']);
});
test('fetch: 見つからない・失敗は false', function () {
    assertSame(false, fetch_acms_package_info('9.9.9', true, '8.3.0', sampleJson()));
    assertSame(false, fetch_acms_package_info('3', true, '8.3.0', sampleJson()));
    assertSame(false, fetch_acms_package_info('', true, '8.3.0', testDir() . '/missing.json'));
    assertSame(false, fetch_acms_package_info('', true, '8.3.0', put('bad.json', '{')));
    assertSame(false, fetch_acms_package_info('', true, '8.3.0', updateJsonFixture(array())));
});
test('fetch: version 欠落・packages 欠落・必須キー欠落', function () {
    $json = updateJsonFixture(array(
        array('packages' => array()),
        array('version' => '3.2.40'),
        array('version' => '3.1.81', 'packages' => array(array('php_min_version' => '7.3.0'))),
        array('version' => '3.2.10', 'packages' => array(pkg('3.2.10', '8.1.0', '8.5.x'))),
    ));
    assertSame(null, fetch_acms_package_info('3.2.40', true, '8.3.0', $json)['download']);
    assertSame(null, fetch_acms_package_info('3.1.81', true, '8.3.0', $json)['download']);
    // 過去パッチ指定の系列探索では version の無い要素を読み飛ばし、最新(3.2.40)を基準にする
    assertSame('3.2.5', fetch_acms_package_info('3.2.5', true, '8.3.0', $json)['version']);
});

// ---------- 環境判定 ----------
test('acms_detect_cpi', function () {
    assertSame('secure', acms_detect_cpi('x.secure.ne.jp.', 'example.test'));
    assertSame('secure', acms_detect_cpi('localhost', 'foo.smartrelease.jp'));
    assertSame('', acms_detect_cpi('localhost', 'example.test'));
    assertSame('example', acms_detect_cpi('host.example.com', 'example.test'));
});

test('acms_resolve_db_defaults: DDEV / localhost / MAMP / それ以外', function () {
    $base = array('dbHost' => 'h', 'dbName' => 'n', 'dbCreate' => 'c', 'dbUser' => 'u', 'dbPass' => 'p',
        'isDdev' => false, 'httpHost' => 'example.test', 'cfgFilePath' => '', 'mdHi' => '0101', 'ablogcmsVersion' => '3.2.40');
    assertSame(array('dbHost' => 'h', 'dbName' => 'n', 'dbCreate' => 'c', 'dbUser' => 'u', 'dbPass' => 'p'), acms_resolve_db_defaults($base));

    $ddev = acms_resolve_db_defaults(array_merge($base, array('isDdev' => true)));
    assertSame(array('dbHost' => 'db', 'dbName' => 'db', 'dbCreate' => '', 'dbUser' => 'db', 'dbPass' => 'db'), $ddev);

    $local = acms_resolve_db_defaults(array_merge($base, array('httpHost' => 'localhost:8888')));
    assertSame('127.0.0.1', $local['dbHost']);
    assertSame('DBacms_3240_0101', $local['dbName']);
    assertSame('checked', $local['dbCreate']);
    assertSame('root', $local['dbUser']);
    assertSame('', $local['dbPass']);

    $mamp = acms_resolve_db_defaults(array_merge($base, array('httpHost' => 'localhost', 'cfgFilePath' => '/Applications/MAMP/bin/php.ini')));
    assertSame('root', $mamp['dbPass']);
});

// ---------- MAMP ----------
test('MAMP: 警告表示と httpd.conf の修正', function () {
    $conf = put('mamp/httpd.conf', "LoadModule a_module x\n#LoadModule rewrite_module modules/mod_rewrite.so\n");
    assertSame('', acms_mamp_rewrite_warning('/usr/php.ini', $conf));
    assertSame('', acms_mamp_rewrite_warning('/MAMP/php.ini', testDir() . '/none.conf'));
    assertContains('MAMP httpd.confチェック', acms_mamp_rewrite_warning('/MAMP/php.ini', $conf));

    $msg = acms_fix_mamp_httpdconf($conf, $conf . '.backup');
    assertContains('MAMPを再起動してください', $msg);
    assertContains("\nLoadModule rewrite_module", file_get_contents($conf));
    assertFile($conf . '.backup');
    // 修正済みなら以降は何もしない
    assertSame('', acms_mamp_rewrite_warning('/MAMP/php.ini', $conf));
    assertSame('', acms_fix_mamp_httpdconf($conf, $conf . '.backup2'));
    assertSame('', acms_fix_mamp_httpdconf(testDir() . '/none.conf', 'x'));
});

// ---------- .htaccess ----------
test('acms_setup_htaccess: 無ければ htaccess.txt をリネーム', function () {
    put('htaccess.txt', 'CMS');
    acms_setup_htaccess(testDir(), '', '');
    assertSame('CMS', file_get_contents('.htaccess'));
    assertNoFile('htaccess.txt');
});
test('acms_setup_htaccess: 既存があれば追記してマージ', function () {
    put('.htaccess', 'MINE');
    put('htaccess.txt', 'CMS');
    acms_setup_htaccess(testDir(), '', '');
    assertSame("MINE\n\nCMS", file_get_contents('.htaccess'));
});
test('acms_setup_htaccess: CPI では AddHandler を先頭に付ける', function () {
    put('htaccess.txt', 'CMS');
    acms_setup_htaccess(testDir(), 'secure', '81');
    $s = file_get_contents('.htaccess');
    assertContains('AddHandler x-httpd-php81 .php', $s);
    assertSame('CMS', substr($s, -3));
});

test('acms_write_db_default', function () {
    mkdir('setup/lib', 0777, true);
    put('db_default.php', 'USER-SUPPLIED');
    acms_write_db_default(testDir(), array('dbHost' => 'h', 'dbName' => 'n', 'dbCreate' => 'checked', 'dbUser' => 'u', 'dbPass' => 'p'));
    // ルート直下に db_default.php があればそちらが優先される
    assertSame('USER-SUPPLIED', file_get_contents('setup/lib/db_default.php'));
    unlink('setup/lib/db_default.php');
    acms_write_db_default(testDir(), array('dbHost' => 'h', 'dbName' => 'n', 'dbCreate' => 'checked', 'dbUser' => 'u', 'dbPass' => 'p'));
    assertContains("\$dbDefaultName     = 'n';", file_get_contents('setup/lib/db_default.php'));
});

// ---------- ダウンロード・展開・移動 ----------
test('acms_download_file / acms_extract_zip / acms_move_package_files', function () {
    mkdir('fixtures');
    $zip = makeZip(testDir() . '/fixtures/p.zip', array('root/ablogcms/a.txt' => 'A', 'root/ablogcms/d/b.txt' => 'B'));
    assertTrue(acms_download_file('file://' . $zip, 'got.zip'));
    assertTrue(acms_extract_zip('got.zip', testDir()));
    assertTrue(acms_move_package_files(testDir() . '/root/ablogcms/', testDir()));
    assertSame('A', file_get_contents('a.txt'));
    assertSame('B', file_get_contents('d/b.txt'));
});
test('ダウンロード・展開・移動の失敗は false', function () {
    assertFalse(acms_download_file('file://' . testDir() . '/missing.zip', 'x.zip'));
    mkdir('fixtures');
    put('fixtures/ok.txt', 'x');
    mkdir('blocked'); // 書き込み先がディレクトリ → 開けない
    assertFalse(acms_download_file('file://' . testDir() . '/fixtures/ok.txt', 'blocked'));
    put('broken.zip', 'nope');
    assertFalse(acms_extract_zip('broken.zip', testDir()));
    assertFalse(acms_move_package_files(testDir() . '/none/', testDir()));
});

// ---------- dir_shori / htaccess（setup.php 版） ----------
test('dir_shori copy / move（既存ディレクトリは置き換え）/ delete', function () {
    put('src/a.txt', 'A');
    put('src/sub/b.txt', 'B');
    dir_shori('copy', 'src', 'copied');
    assertSame('B', file_get_contents('copied/sub/b.txt'));

    put('dst/sub/old.txt', 'OLD');
    dir_shori('move', 'src', 'dst');
    assertSame('A', file_get_contents('dst/a.txt'));
    assertSame('B', file_get_contents('dst/sub/b.txt'));
    assertNoFile('dst/sub/old.txt');
    assertNoFile('src');

    dir_shori('delete', 'dst');
    assertNoFile('dst');
});
test('rename_bundled_htaccess', function () {
    put('private/htaccess.txt', 'P');
    put('exists/htaccess.txt', 'NEW');
    put('exists/.htaccess', 'OLD');
    put('tmp/htaccess.txt', 'TMP');
    rename_bundled_htaccess(testDir(), array('tmp'));
    assertSame('P', file_get_contents('private/.htaccess'));
    assertSame('OLD', file_get_contents('exists/.htaccess'));
    assertNoFile('exists/htaccess.txt');
    assertFile('tmp/htaccess.txt');
    rename_bundled_htaccess(testDir() . '/nope');
    assertTrue(true);
});

// ---------- fetch_final_http_status（ローカル HTTP サーバで検証） ----------
test('fetch_final_http_status: リダイレクトを辿った最終ステータスを返す', function () {
    $router = put('router.php', '<?php
$p = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($p === "/redir") { header("Location: /ok", true, 302); return; }
if ($p === "/ok") { http_response_code(200); echo "ok"; return; }
http_response_code(404);');
    $port = 20000 + mt_rand(0, 9999);
    $command = escapeshellarg(PHP_BINARY) . " -S 127.0.0.1:$port " . escapeshellarg($router); // 配列形式は PHP 7.4+ のため文字列で渡す
    $proc = proc_open($command, array(1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes, testDir());
    try {
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
            usleep(100000);
        }
        assertSame('200', fetch_final_http_status("http://127.0.0.1:$port/ok"));
        assertSame('200', fetch_final_http_status("http://127.0.0.1:$port/redir"));
        assertSame('404', fetch_final_http_status("http://127.0.0.1:$port/nothing"));
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }
    assertSame(null, fetch_final_http_status('http://127.0.0.1:1/unreachable'));
});

// ---------- 拡張アプリ・フック ----------
test('acms_enable_hook: HOOK_ENABLE を 1 にする', function () {
    put('config.server.php', "<?php\ndefine('DB_HOST', 'x');\ndefine('HOOK_ENABLE', 0);\n");
    acms_enable_hook(testDir() . '/config.server.php');
    $s = file_get_contents('config.server.php');
    assertContains("define('HOOK_ENABLE', 1);", $s);
    assertNotContains("'HOOK_ENABLE', 0", $s);
    assertContains("define('DB_HOST', 'x');", $s);
});
test('acms_install_plugins: 複数 zip を extension/plugins へ配置', function () {
    mkdir('fixtures');
    mkdir('extension/plugins', 0777, true);
    makeZip(testDir() . '/fixtures/Cart_100.zip', array('Cart/a.php' => 'A'));
    makeZip(testDir() . '/fixtures/Other.zip', array('Other/b.php' => 'B'));
    $err = acms_install_plugins(testDir(), 'file://' . testDir() . '/fixtures/', 'Cart_100.zip|Other.zip');
    assertSame(null, $err);
    assertSame('A', file_get_contents('extension/plugins/Cart/a.php'));
    assertSame('B', file_get_contents('extension/plugins/Other/b.php'));
    assertNoFile('Cart_100.zip');
});
test('acms_install_plugins: ダウンロード失敗・解凍失敗はメッセージを返す', function () {
    mkdir('fixtures');
    assertContains('plugin download Error', acms_install_plugins(testDir(), 'file://' . testDir() . '/fixtures/', 'Missing.zip'));
    put('fixtures/Bad.zip', 'not a zip');
    assertContains('unZip Error', acms_install_plugins(testDir(), 'file://' . testDir() . '/fixtures/', 'Bad.zip'));
});

// ---------- 特製テーマ ----------
function themeSite()
{
    mkdir('setup/bin', 0777, true);
    mkdir('setup/tpl', 0777, true);
    mkdir('setup/img', 0777, true);
    mkdir('themes', 0777, true);
    mkdir('extension/plugins', 0777, true); // a-blog cms 3.2 のパッケージには同梱されている
    put('config.server.php', "<?php\ndefine('HOOK_ENABLE', 0);\n");
    mkdir('fixtures');
}
test('acms_install_theme: 従来形式（テーマ名ディレクトリが最外層）', function () {
    themeSite();
    makeZip(testDir() . '/fixtures/sq.zip', array(
        'sq/bin/sq/theme.yaml' => 'Y',
        'sq/themes/sq/t.html' => 'T',
        'sq/tpl/install.html' => 'INSTALL',
        'sq/img/sq.jpg' => 'JPG',
        'sq/plugins/PlugA/p.php' => 'P',
    ));
    assertSame(null, acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'sq.zip'));
    assertSame('Y', file_get_contents('setup/bin/sq/theme.yaml'));
    assertSame('T', file_get_contents('themes/sq/t.html'));
    assertSame('INSTALL', file_get_contents('setup/tpl/install.html'));
    assertSame('JPG', file_get_contents('setup/img/sq.jpg'));
    assertSame('P', file_get_contents('extension/plugins/PlugA/p.php'));
    assertContains("define('HOOK_ENABLE', 1);", file_get_contents('config.server.php'));
    assertNoFile('sq'); // 一時ディレクトリは削除
    assertNoFile('sq.zip');
});
test('acms_install_theme: 従来形式で旧インストーラ（setup/tpl）が無ければ install.html は配置しない', function () {
    themeSite();
    rmdir('setup/tpl');
    makeZip(testDir() . '/fixtures/sq.zip', array(
        'sq/bin/sq/theme.yaml' => 'Y',
        'sq/themes/sq/t.html' => 'T',
        'sq/tpl/install.html' => 'INSTALL',
    ));
    assertSame(null, acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'sq.zip'));
    assertNoFile('setup/tpl');
    assertNoFile('extension/plugins/PlugA');
});
test('acms_install_theme: 旧インストーラでサムネイルが無くても動く', function () {
    themeSite();
    makeZip(testDir() . '/fixtures/sq.zip', array('sq/bin/sq/y' => 'Y', 'sq/themes/sq/t' => 'T', 'sq/tpl/install.html' => 'I'));
    assertSame(null, acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'sq.zip'));
    assertNoFile('setup/img/sq.jpg');
});
test('acms_install_theme: bin/themes 直下形式', function () {
    themeSite();
    makeZip(testDir() . '/fixtures/utsuwa.zip', array(
        'bin/utsuwa/theme.yaml' => 'Y',
        'themes/utsuwa/t.html' => 'T',
        'plugins/PlugB/p.php' => 'P',
    ));
    assertSame(null, acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'utsuwa.zip'));
    assertSame('Y', file_get_contents('setup/bin/utsuwa/theme.yaml'));
    assertSame('T', file_get_contents('themes/utsuwa/t.html'));
    assertSame('P', file_get_contents('extension/plugins/PlugB/p.php'));
    assertContains("define('HOOK_ENABLE', 1);", file_get_contents('config.server.php'));
});
test('acms_install_theme: ダウンロード失敗・解凍失敗はメッセージを返す', function () {
    themeSite();
    assertContains('download Error', acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'none.zip'));
    put('fixtures/bad.zip', 'not a zip');
    assertContains('unZip Error', acms_install_theme(testDir(), 'file://' . testDir() . '/fixtures/', 'bad.zip'));
});

// ---------- 描画 ----------
test('描画: head / done', function () {
    assertContains('<h1>a-blog cms Ver. 3.2.40 簡単セットアップ</h1>', acms_render_setup_head('3.2.40'));
    $done = acms_render_setup_done('setup.php', '<p>MAMP</p>');
    assertContains('セットアップ完了', $done);
    assertContains('<p>MAMP</p>', $done);
});
