<?php
// update.php の純粋なロジック（判定・取得・描画・ディレクトリ操作）のテスト

// ---------- acms_db_host ----------
test('acms_db_host: DB_PORT があれば ;port= を付ける', function () {
    assertSame('db;port=3307', acms_db_host('db', 3307));
});
test('acms_db_host: host:port 形式を分解する', function () {
    assertSame('127.0.0.1;port=3306', acms_db_host('127.0.0.1:3306', null));
});
test('acms_db_host: 空の DB_PORT は無視して host:port を分解する', function () {
    assertSame('db;port=1', acms_db_host('db:1', ''));
});
test('acms_db_host: ポート指定が無ければそのまま', function () {
    assertSame('localhost', acms_db_host('localhost', null));
});

// ---------- acms_known_php_range（requirements.html 準拠の対応表） ----------
test('acms_known_php_range: 3.x の各区間', function () {
    $cases = array(
        '3.0.0'  => array('7.2.5', '8.0.999'),
        '3.0.11' => array('7.2.5', '8.0.999'),
        '3.0.12' => array('7.2.5', '8.1.999'),
        '3.0.70' => array('7.2.5', '8.1.999'),
        '3.1.0'  => array('7.2.5', '8.1.999'),
        '3.1.6'  => array('7.2.5', '8.1.999'),
        '3.1.7'  => array('7.3.0', '8.1.999'),
        '3.1.13' => array('7.3.0', '8.1.999'),
        '3.1.14' => array('7.3.0', '8.3.999'),
        '3.1.81' => array('7.3.0', '8.3.999'),
        '3.2.0'  => array('8.1.0', '8.5.999'),
        '3.2.40' => array('8.1.0', '8.5.999'),
    );
    foreach ($cases as $version => $range) {
        assertSame($range, acms_known_php_range($version), $version);
    }
});
test('acms_known_php_range: 表に無い版は null（update.json にフォールバック）', function () {
    assertSame(null, acms_known_php_range('3.3.0'));
    assertSame(null, acms_known_php_range('2.10.1'));
});

// ---------- fetch_acms_package_info ----------
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
        array('version' => '2.11.78', 'packages' => array(
            pkg('2.11.78', '5.6.0', '7.0.x', '_update2x_php5.6'),
            pkg('2.11.78', '7.1.0', '7.4.x', '_update2x_php7.1'),
        )),
    ));
}

test('fetch: 指定なしは semver 最大を返す（HTTP は HTTPS に寄せる）', function () {
    $r = fetch_acms_package_info('', false, '8.3.5', sampleJson());
    assertSame('3.2.40', $r['version']);
    assertSame('https://example.test/_package/3.2.40/acms3.2.40_update2x.zip', $r['download']);
    assertSame('acms3.2.40_update2x/ablogcms', $r['root_dir']);
});
test('fetch: 完全一致のバージョンを返す', function () {
    $r = fetch_acms_package_info('3.1.81', false, '7.4.0', sampleJson());
    assertSame('3.1.81', $r['version']);
    assertContains('acms3.1.81_update2x.zip', $r['download']);
});
test('fetch: 過去パッチ指定は系列最新の URL を差し替える', function () {
    $r = fetch_acms_package_info('3.1.6', false, '7.2.34', sampleJson());
    assertSame('3.1.6', $r['version']);
    assertSame('https://example.test/_package/3.1.6/acms3.1.6_update2x.zip', $r['download']);
    assertSame('acms3.1.6_update2x/ablogcms', $r['root_dir']);
});
test('fetch: PHP 7.2.34 は 3.1.0〜3.1.6 に対応、3.1.7 以降は非対応（update.json の範囲に依らず表で判定）', function () {
    assertTrue(fetch_acms_package_info('3.1.0', false, '7.2.34', sampleJson())['download'] !== null);
    assertTrue(fetch_acms_package_info('3.1.6', false, '7.2.34', sampleJson())['download'] !== null);
    assertSame(null, fetch_acms_package_info('3.1.7', false, '7.2.34', sampleJson())['download']);
});
test('fetch: 表の上限を超える PHP は非対応', function () {
    assertSame(null, fetch_acms_package_info('3.0.5', false, '8.1.2', sampleJson())['download']); // 3.0.0-3.0.11 は 8.0 まで
    assertTrue(fetch_acms_package_info('3.0.12', false, '8.1.2', sampleJson())['download'] !== null);
    assertSame(null, fetch_acms_package_info('', false, '7.4.33', sampleJson())['download']);
});
test('fetch: major.minor だけの PHP 表記も 8.1.0 として扱う', function () {
    assertTrue(fetch_acms_package_info('', false, '8.1', sampleJson())['download'] !== null);
});
test('fetch: forInstall は _updateNx を取り除いたフルパッケージ名にする', function () {
    $r = fetch_acms_package_info('', true, '8.3.5', sampleJson());
    assertSame('https://example.test/_package/3.2.40/acms3.2.40.zip', $r['download']);
    assertSame('acms3.2.40/ablogcms', $r['root_dir']);
});
test('fetch: 表に無い系列は update.json の PHP 範囲を使う（2.x）', function () {
    $ok = fetch_acms_package_info('2.11.78', false, '7.4.1', sampleJson());
    assertContains('php7.1.zip', $ok['download']);
    $ng = fetch_acms_package_info('2.11.78', false, '8.0.0', sampleJson());
    assertSame(null, $ng['download']);
});
test('fetch: 該当版が無い・系列も無い場合は false', function () {
    assertSame(false, fetch_acms_package_info('9.9.9', false, '8.3.0', sampleJson()));
    assertSame(false, fetch_acms_package_info('3', false, '8.3.0', sampleJson()));
});
test('fetch: 取得失敗・不正 JSON・versions 空は false', function () {
    assertSame(false, fetch_acms_package_info('', false, '8.3.0', testDir() . '/missing.json'));
    assertSame(false, fetch_acms_package_info('', false, '8.3.0', put('bad.json', 'not json')));
    assertSame(false, fetch_acms_package_info('', false, '8.3.0', updateJsonFixture(array())));
});
test('fetch: version キーの無い要素は読み飛ばす', function () {
    $json = updateJsonFixture(array(
        array('packages' => array()),
        array('version' => '3.2.40', 'packages' => array(pkg('3.2.40', '8.1.0', '8.5.x'))),
    ));
    assertSame('3.2.40', fetch_acms_package_info('', false, '8.3.0', $json)['version']);
    // 過去パッチ指定の系列探索でも version の無い要素は読み飛ばす
    assertSame('3.2.1', fetch_acms_package_info('3.2.1', false, '8.3.0', $json)['version']);
});
test('fetch: 同系列の複数エントリがあれば最新を基準にする', function () {
    $json = updateJsonFixture(array(
        array('version' => '3.2.10', 'packages' => array(pkg('3.2.10', '8.1.0', '8.5.x'))),
        array('version' => '3.2.40', 'packages' => array(pkg('3.2.40', '8.1.0', '8.5.x'))),
        array('version' => '3.1.81', 'packages' => array(pkg('3.1.81', '7.3.0', '8.3.x'))),
    ));
    $r = fetch_acms_package_info('3.2.5', false, '8.3.0', $json);
    assertContains('3.2.5/acms3.2.5_update2x.zip', $r['download']);
});
test('fetch: packages が無い・必須キー欠落は download null', function () {
    $json = updateJsonFixture(array(
        array('version' => '3.2.40'),
        array('version' => '3.1.81', 'packages' => array(array('php_min_version' => '7.3.0'))),
    ));
    assertSame(null, fetch_acms_package_info('3.2.40', false, '8.3.0', $json)['download']);
    assertSame(null, fetch_acms_package_info('3.1.81', false, '8.3.0', $json)['download']);
});

// ---------- acms_check_package ----------
test('acms_check_package: 取得失敗', function () {
    list($v, $errors) = acms_check_package(false, '8.3.0');
    assertSame(null, $v);
    assertContains('バージョンの取得に失敗しました', $errors[0]);
});
test('acms_check_package: 3.x 以外はサポート外', function () {
    list($v, $errors) = acms_check_package(array('version' => '2.11.78', 'download' => 'x', 'root_dir' => 'y'), '7.4.0');
    assertSame('2.11.78', $v);
    assertContains('3.x 用です', $errors[0]);
});
test('acms_check_package: 対応 PHP が無ければ PHP バージョンをエラーに含める', function () {
    list($v, $errors) = acms_check_package(array('version' => '3.1.7', 'download' => null, 'root_dir' => null), '7.2.34');
    assertSame('3.1.7', $v);
    assertContains('現在の PHP のバージョンが 7.2.34 です', $errors[0]);
});
test('acms_check_package: 問題なし', function () {
    list($v, $errors) = acms_check_package(array('version' => '3.2.40', 'download' => 'x', 'root_dir' => 'y'), '8.3.0');
    assertSame('3.2.40', $v);
    assertSame(array(), $errors);
});

// ---------- DB 由来の状態 ----------
function memoryDb($version, array $themes)
{
    $dbh = new PDO('sqlite::memory:');
    $dbh->exec('CREATE TABLE acms_sequence (sequence_system_version TEXT)');
    $dbh->exec('CREATE TABLE acms_config (config_key TEXT, config_value TEXT)');
    if ($version !== null) {
        $dbh->exec("INSERT INTO acms_sequence VALUES ('" . $version . "')");
    }
    foreach ($themes as $t) {
        $dbh->exec("INSERT INTO acms_config VALUES ('theme', '" . $t . "')");
    }
    $dbh->exec("INSERT INTO acms_config VALUES ('other', 'ignored')");
    return $dbh;
}
test('acms_fetch_current_state: バージョンとテーマを取る', function () {
    $s = acms_fetch_current_state(memoryDb('2.10.65', array('a', 'b@a')), 'acms_');
    assertSame(array('version' => '2.10.65', 'themes' => array('a', 'b@a')), $s);
});
test('acms_fetch_current_state: 行が無くても落ちない', function () {
    $s = acms_fetch_current_state(memoryDb(null, array()), 'acms_');
    assertSame(array('version' => '', 'themes' => array()), $s);
});

test('acms_expand_themes: 親テーマを含め重複なく返す', function () {
    assertSame(array('c@b@a', 'x', 'b@a', 'a'), acms_expand_themes(array('c@b@a', 'x', 'x')));
    assertSame(array(), acms_expand_themes(array()));
});

test('acms_unused_themes: 利用中・system・ファイルを除く', function () {
    mkdir('themes/system', 0777, true);
    mkdir('themes/used');
    mkdir('themes/old');
    put('themes/.DS_Store');
    assertSame(array('old'), acms_unused_themes(testDir() . '/themes', array('used')));
});
test('acms_unused_themes: themes が無ければ空', function () {
    assertSame(array(), acms_unused_themes(testDir() . '/nothing', array()));
});

// ---------- 描画 ----------
test('acms_major_version', function () {
    assertSame(2, acms_major_version('2.10.65'));
    assertSame(0, acms_major_version(''));
});
test('acms_render_intro: 2.x からはライセンス案内を出す', function () {
    $html = acms_render_intro('2.10.65', '3.1.0');
    assertContains('Ver.2.10.65', $html);
    assertContains('Ver.3.1.0', $html);
    assertContains('license.php', $html);
});
test('acms_render_intro: 3.x からはライセンス案内なし、版不明なら空', function () {
    assertNotContains('license.php', acms_render_intro('3.0.1', '3.1.0'));
    assertSame('', acms_render_intro('3.0.1', null));
});
test('acms_render_themes: 未使用があれば注意書きを出す', function () {
    $html = acms_render_themes(array('a'), array('old'));
    assertContains('<li>a</li>', $html);
    assertContains('<li>old</li>', $html);
    assertContains('バックアップデータ側に保存されます', $html);
    assertNotContains('バックアップデータ側', acms_render_themes(array('a'), array()));
});
test('acms_render_errors', function () {
    assertSame('', acms_render_errors(array()));
    assertContains("<p class='acms-admin-text-error'>boom</p>", acms_render_errors(array('boom')));
});
test('acms_render_head / password_form / done', function () {
    assertContains('<h1>a-blog cms 簡単アップデート</h1>', acms_render_head());
    assertContains('name="dbpass"', acms_render_password_form());
    assertContains('license.php', acms_render_done('2.10.65'));
    assertNotContains('license.php', acms_render_done('3.0.1'));
    assertContains('アップデート処理の実行を完了しました', acms_render_done('3.0.1'));
});

// ---------- dir_shori ----------
test('dir_shori copy: 再帰コピー', function () {
    put('src/a.txt', 'A');
    put('src/sub/b.txt', 'B');
    dir_shori('copy', 'src', 'dst');
    assertSame('A', file_get_contents('dst/a.txt'));
    assertSame('B', file_get_contents('dst/sub/b.txt'));
    assertFile('src/a.txt');
});
test('dir_shori move: 既存ファイルは上書きせず、既存ディレクトリはマージ', function () {
    put('src/new.txt', 'N');
    put('src/same.txt', 'SRC');
    put('src/dir/x.txt', 'X');
    put('src/dir/y.txt', 'Y');
    put('dst/same.txt', 'DST');
    put('dst/dir/x.txt', 'KEEP');
    dir_shori('move', 'src', 'dst');
    assertSame('N', file_get_contents('dst/new.txt'));
    assertSame('DST', file_get_contents('dst/same.txt'));
    assertSame('KEEP', file_get_contents('dst/dir/x.txt'));
    assertSame('Y', file_get_contents('dst/dir/y.txt'));
});
test('dir_shori move: 未存在ディレクトリは丸ごと移動', function () {
    put('src/dir/x.txt', 'X');
    dir_shori('move', 'src', 'dst');
    assertSame('X', file_get_contents('dst/dir/x.txt'));
    assertNoFile('src');
});
test('dir_shori delete: 再帰削除', function () {
    put('t/a.txt');
    put('t/sub/b.txt');
    dir_shori('delete', 't');
    assertNoFile('t');
});
test('dir_shori: 元が無くても落ちない', function () {
    assertTrue(dir_shori('delete', 'none'));
});

// ---------- rename_bundled_htaccess ----------
test('rename_bundled_htaccess: 再帰リネーム・既存あれば削除・除外ディレクトリは触らない', function () {
    put('private/htaccess.txt', 'P');
    put('themes/system/htaccess.txt', 'T');
    put('exists/htaccess.txt', 'NEW');
    put('exists/.htaccess', 'OLD');
    put('cache/htaccess.txt', 'C');
    put('tmp/htaccess.txt', 'TMP');
    put('root.txt');
    rename_bundled_htaccess(testDir(), array('cache', 'tmp'));
    assertSame('P', file_get_contents('private/.htaccess'));
    assertSame('T', file_get_contents('themes/system/.htaccess'));
    assertSame('OLD', file_get_contents('exists/.htaccess'));
    assertNoFile('exists/htaccess.txt');
    assertFile('cache/htaccess.txt');
    assertFile('tmp/htaccess.txt');
});
test('rename_bundled_htaccess: 存在しないディレクトリは何もしない', function () {
    rename_bundled_htaccess(testDir() . '/nope');
    assertTrue(true);
});

// ---------- ダウンロード・展開 ----------
test('acms_download_file / acms_extract_zip', function () {
    $zip = makeZip(testDir() . '/src.zip', array('a/b.txt' => 'hello'));
    assertTrue(acms_download_file('file://' . $zip, 'got.zip'));
    assertTrue(acms_extract_zip('got.zip', testDir() . '/out'));
    assertSame('hello', file_get_contents('out/a/b.txt'));
});
test('acms_download_file / acms_extract_zip: 失敗時は false', function () {
    assertFalse(acms_download_file('file://' . testDir() . '/missing.zip', 'x.zip'));
    put('broken.zip', 'not a zip');
    assertFalse(acms_extract_zip('broken.zip', testDir() . '/out'));
});
