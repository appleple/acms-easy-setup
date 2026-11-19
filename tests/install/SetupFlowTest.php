<?php
// acms_setup_page（確認画面〜セットアップ実行までの一連）のテスト

function makeFullPackage()
{
    if (!is_dir(testDir() . '/fixtures')) {
        mkdir(testDir() . '/fixtures');
    }
    $root = 'acms3.2.40';
    $zip = makeZip(testDir() . '/fixtures/pkg.zip', array(
        "$root/ablogcms/index.php" => 'INDEX',
        "$root/ablogcms/htaccess.txt" => 'ROOT-HT',
        "$root/ablogcms/editorconfig.txt" => 'EC',
        "$root/ablogcms/env.txt" => 'ENV',
        "$root/ablogcms/gitignore.txt" => 'GI',
        "$root/ablogcms/private/htaccess.txt" => 'PRIV-HT',
        "$root/ablogcms/setup/lib/" => '',
        "$root/ablogcms/setup/bin/" => '',
        "$root/ablogcms/extension/plugins/" => '',
        "$root/ablogcms/config.server.php" => "<?php\ndefine('HOOK_ENABLE', 0);\n",
    ));
    return array('version' => '3.2.40', 'download' => 'file://' . $zip, 'root_dir' => "$root/ablogcms");
}

function setupContext($package, array $over = array())
{
    return array_merge(array(
        'ablogcmsVersion' => '',
        'dbHost' => '', 'dbName' => '', 'dbCreate' => '', 'dbUser' => '', 'dbPass' => '',
        'cpiPhpVersion' => '8.1',
        'themeDownloadUrl' => 'file://' . testDir() . '/fixtures/',
        'themeZipFile' => null,
        'pluginsDownloadUrl' => 'file://' . testDir() . '/fixtures/',
        'pluginsZipFile' => null,
        'phpversion' => '8.3.0',
        'serverHost' => 'host.example.com',
        'httpHost' => 'example.test',
        'phpName' => 'setup.php',
        'installPath' => testDir(),
        'action' => null,
        'isDdev' => false,
        'cfgFilePath' => '',
        'mdHi' => '0101',
        'mampHttpdConf' => testDir() . '/mamp/httpd.conf',
        'fetchPackage' => function () use ($package) {
            return $package;
        },
        'httpStatus' => function () {
            return '200';
        },
    ), $over);
}

// ---------- 確認画面 ----------
test('page: 確認画面に PHP バージョンと開始ボタンが出る', function () {
    $html = acms_setup_page(setupContext(makeFullPackage()));
    assertContains('Ver. 3.2.40 簡単セットアップ', $html);
    assertContains('<li>Ver. 8.3.0</li>', $html);
    assertContains('value="セットアップ開始"', $html);
    assertNotContains('<h2>Error</h2>', $html);
});
test('page: CPI 環境では $cpi_php_version への変更を表示する', function () {
    $html = acms_setup_page(setupContext(makeFullPackage(), array('httpHost' => 'x.smartrelease.jp', 'phpversion' => '7.4.0')));
    assertContains('<del>7.4.0</del> → 8.1(変更)', $html);
});
test('page: license.php があれば中止', function () {
    put('license.php');
    $html = acms_setup_page(setupContext(makeFullPackage(), array('action' => 'セットアップ開始')));
    assertContains('インストール先に license.php が見つかりました', $html);
    assertNotContains('セットアップ完了', $html);
    assertNoFile('index.php'); // エラー時は実行ボタン押下でも処理しない
});
test('page: update.json 取得失敗', function () {
    $html = acms_setup_page(setupContext(false));
    assertContains('バージョン情報の取得に失敗しました', $html);
    assertNotContains('value="セットアップ開始"', $html);
});
test('page: 未対応 PHP（通常環境 / CPI 環境でメッセージが変わる）', function () {
    $pkg = array('version' => '3.1.7', 'download' => null, 'root_dir' => null);
    $normal = acms_setup_page(setupContext($pkg, array('phpversion' => '7.2.34')));
    assertContains('現在の PHP バージョン（7.2.34）は a-blog cms Ver.3.1.7 に対応していません', $normal);
    $cpi = acms_setup_page(setupContext($pkg, array('httpHost' => 'a.smartrelease.jp')));
    assertContains('$cpi_php_version が a-blog cms Ver.3.1.7 に対応していません', $cpi);
});
test('page: ダウンロード先が 200 でなければエラー', function () {
    $html = acms_setup_page(setupContext(makeFullPackage(), array('httpStatus' => function () {
        return '404';
    })));
    assertContains('ダウンロード先の確認に失敗しました', $html);
});
test('page: 前回の zip が残っていれば再実行させない', function () {
    put('acms3.2.40.zip', 'left over');
    $html = acms_setup_page(setupContext(makeFullPackage(), array('action' => 'セットアップ開始')));
    assertContains('value="セットアップ開始"', $html);
    assertNoFile('index.php');
});
test('page: 特製テーマ・拡張アプリのダウンロード先チェック', function () {
    $ok = acms_setup_page(setupContext(makeFullPackage(), array('themeZipFile' => 'square@ec.zip', 'pluginsZipFile' => 'Cart_100.zip|Other.zip')));
    assertContains('<ul><li>square@ec</li></ul>', $ok);
    assertContains('<li>Cart</li><li>Other</li>', $ok);

    $ng = acms_setup_page(setupContext(makeFullPackage(), array(
        'themeZipFile' => 'square@ec.zip',
        'pluginsZipFile' => 'Cart_100.zip',
        'httpStatus' => function ($url) {
            return strpos($url, 'pkg.zip') !== false ? '200' : '404';
        },
    )));
    assertContains('特製テーマ「square@ec」のダウンロード先の情報が間違っています', $ng);
    assertContains('<del>square@ec</del>', $ng);
    assertContains('拡張アプリ「Cart」のダウンロード先の情報が間違っています', $ng);
    assertContains('<li><del>Cart</del></li>', $ng);
    assertNotContains('value="セットアップ開始"', $ng);
});
test('page: MAMP で rewrite_module が無効なら警告を出す', function () {
    put('mamp/httpd.conf', "#LoadModule rewrite_module modules/mod_rewrite.so\n");
    $html = acms_setup_page(setupContext(makeFullPackage(), array('cfgFilePath' => '/Applications/MAMP/bin/php.ini')));
    assertContains('MAMP httpd.confチェック', $html);
});

// ---------- 実行 ----------
function prepareInstallSite()
{
    put('setup.php', '<?php // self');
    put('index.html', 'placeholder');
}
test('page: セットアップ開始で一式が配置される', function () {
    prepareInstallSite();
    $html = acms_setup_page(setupContext(makeFullPackage(), array(
        'action' => 'セットアップ開始',
        'httpHost' => 'localhost:8888',
        'dbHost' => 'ignored',
    )));
    assertContains('セットアップ完了', $html);
    assertSame('INDEX', file_get_contents('index.php'));
    assertSame('ROOT-HT', file_get_contents('.htaccess'));
    assertSame('EC', file_get_contents('.editorconfig'));
    assertSame('ENV', file_get_contents('.env'));
    assertSame('GI', file_get_contents('.gitignore'));
    assertSame('PRIV-HT', file_get_contents('private/.htaccess'));
    assertContains("\$dbDefaultHost     = '127.0.0.1';", file_get_contents('setup/lib/db_default.php'));
    assertContains("'DBacms_3240_0101'", file_get_contents('setup/lib/db_default.php'));
    assertSame('placeholder', file_get_contents('_index.html'));
    assertNoFile('setup.php');
    assertNoFile('acms3.2.40.zip');
    assertNoFile('acms3.2.40');
});
test('page: テーマ・拡張アプリ・MAMP 修正まで含めて実行', function () {
    prepareInstallSite();
    unlink('index.html');
    put('mamp/httpd.conf', "#LoadModule rewrite_module modules/mod_rewrite.so\n");
    $pkg = makeFullPackage();
    makeZip(testDir() . '/fixtures/utsuwa.zip', array('bin/utsuwa/theme.yaml' => 'Y', 'themes/utsuwa/t.html' => 'T'));
    makeZip(testDir() . '/fixtures/Cart_100.zip', array('Cart/a.php' => 'A'));
    $html = acms_setup_page(setupContext($pkg, array(
        'action' => 'セットアップ開始',
        'themeZipFile' => 'utsuwa.zip',
        'pluginsZipFile' => 'Cart_100.zip',
        'isDdev' => true,
        'cfgFilePath' => '/Applications/MAMP/bin/php.ini',
    )));
    assertContains('セットアップ完了', $html);
    assertContains('MAMPを再起動してください', $html);
    assertSame('T', file_get_contents('themes/utsuwa/t.html'));
    assertSame('A', file_get_contents('extension/plugins/Cart/a.php'));
    assertContains("\$dbDefaultHost     = 'db';", file_get_contents('setup/lib/db_default.php'));
    assertNoFile('_index.html');
});
test('page: 実行中の失敗はメッセージを返す（ダウンロード・解凍・テーマ・拡張アプリ）', function () {
    prepareInstallSite();
    $pkg = makeFullPackage();

    $broken = $pkg;
    $broken['download'] = 'file://' . testDir() . '/fixtures/missing.zip';
    assertContains('a-blog cms download Error', acms_setup_page(setupContext($broken, array('action' => 'セットアップ開始'))));

    put('fixtures/bad.zip', 'not a zip');
    $bad = $pkg;
    $bad['download'] = 'file://' . testDir() . '/fixtures/bad.zip';
    assertContains('a-blog cms unZip Error', acms_setup_page(setupContext($bad, array('action' => 'セットアップ開始'))));
    assertNoFile('acms3.2.40.zip'); // 失敗時にゴミの zip を残さない（残ると再実行が無言でブロックされる）

    // root_dir が実在しない → 移動エラー
    $noroot = $pkg;
    $noroot['root_dir'] = 'acms3.2.40/missing';
    assertContains('a-blog cms move Error', acms_setup_page(setupContext($noroot, array('action' => 'セットアップ開始'))));
    assertNoFile('acms3.2.40.zip');
});
test('page: 実行中の特製テーマ失敗', function () {
    prepareInstallSite();
    $r = acms_setup_page(setupContext(makeFullPackage(), array('action' => 'セットアップ開始', 'themeZipFile' => 'none.zip')));
    assertContains('theme none download Error', $r);
});
test('page: 実行中の拡張アプリ失敗', function () {
    prepareInstallSite();
    $r = acms_setup_page(setupContext(makeFullPackage(), array('action' => 'セットアップ開始', 'pluginsZipFile' => 'none.zip')));
    assertContains('plugin download Error', $r);
});
