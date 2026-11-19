<?php
// acms_update_page（画面表示〜アップデート実行までの一連）のテスト。
// 稼働中サイトと配布 zip を一時ディレクトリ上に再現して検証する。

/** 稼働中サイト（更新前）を作る */
function makeCurrentSite($major = 3)
{
    put('update.php', '<?php // self');
    put('index.php', 'OLD-INDEX');
    put('acms.js', 'old');
    put('500.html', 'old');
    put('js/old.js', 'old');
    put('lang/ja.php', 'OLD-LANG');
    put('php/ACMS/User/Custom.php', 'CUSTOM');
    put('php/AAPP/App.php', 'APP');
    put('private/config.system.yaml', 'SYSTEM-YAML');
    put('themes/system/sys.css', 'sys');
    put('themes/mytheme/t.html', 'MYTHEME');
    put('themes/unused/u.html', 'UNUSED');
    put('extension/plugins/p.txt', 'PLUGIN');
    put('cache/.htaccess', 'CACHE-HT');
    put('cache/junk.txt', 'junk');
    put('php.ini', 'ini');
    if ($major < 3) {
        put('license.php', 'OLD-LICENSE');
    }
}

/** 配布パッケージ zip を作り、fetch_acms_package_info 相当の戻り値を返す */
function makePackage($withIndex = true)
{
    $root = 'acms3.2.40_update2x';
    $entries = array(
        "$root/ablogcms/js/new.js" => 'new',
        "$root/ablogcms/lang/ja.php" => 'NEW-LANG',
        "$root/ablogcms/php/ACMS/User/.keep" => '',
        "$root/ablogcms/php/AAPP/.keep" => '',
        "$root/ablogcms/php/ACMS/Core.php" => 'CORE',
        "$root/ablogcms/private/htaccess.txt" => 'PRIV-HT',
        "$root/ablogcms/themes/system/new.css" => 'newsys',
        "$root/ablogcms/htaccess.txt" => 'ROOT-HT',
        "$root/ablogcms/env.txt" => 'ENV',
        "$root/ablogcms/editorconfig.txt" => 'EC',
        "$root/ablogcms/gitignore.txt" => 'GI',
        "$root/ablogcms/cache/htaccess.txt" => 'NEW-CACHE-HT',
        "$root/ablogcms/setup/index.php" => 'SETUP',
        "$root/omake/license.php" => 'DEV-LICENSE',
    );
    if ($withIndex) {
        $entries["$root/ablogcms/index.php"] = 'NEW-INDEX';
    }
    mkdir(testDir() . '/fixtures');
    $zip = makeZip(testDir() . '/fixtures/_dist.zip', $entries);
    return array(
        'version' => '3.2.40',
        'download' => 'file://' . $zip,
        'root_dir' => $root . '/ablogcms',
    );
}

function updateContext($dbVersion, array $themes, $package, array $over = array())
{
    return array_merge(array(
        'installPath' => testDir(),
        'phpName' => 'update.php',
        'ymdhis' => '20260101000000',
        'inputPass' => 'secret',
        'dbPass' => 'secret',
        'dbPrefix' => 'acms_',
        'dbh' => memoryDb($dbVersion, $themes),
        'wantVersion' => '',
        'phpversion' => '8.3.0',
        'fetchPackage' => function () use ($package) {
            return $package;
        },
    ), $over);
}

// ---------- 表示のみ（実行しない） ----------
test('page: パスワード未入力ならフォームと現在の状態を表示', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(), array('inputPass' => null)));
    assertContains('Ver.3.0.5', $html);
    assertContains('Ver.3.2.40', $html);
    assertContains('<li>mytheme</li>', $html);
    assertContains('<li>unused</li>', $html);
    assertContains('name="dbpass"', $html);
    assertNotContains('パスワードが間違っています', $html);
    assertFile('index.php'); // 何も変更されていない
    assertNoFile('update.lock');
});
test('page: パスワードが違えばエラー表示・実行しない', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(), array('inputPass' => 'wrong')));
    assertContains('パスワードが間違っています', $html);
    assertContains('name="dbpass"', $html);
    assertSame('OLD-INDEX', file_get_contents('index.php'));
});
test('page: 未対応 PHP ではフォームを出さず、パスワードが正しくても実行しない', function () {
    makeCurrentSite();
    $pkg = array('version' => '3.1.7', 'download' => null, 'root_dir' => null);
    $wrong = acms_update_page(updateContext('3.0.5', array(), $pkg, array('inputPass' => 'x', 'phpversion' => '7.2.34')));
    assertContains('現在の PHP のバージョンが 7.2.34 です', $wrong);
    assertNotContains('name="dbpass"', $wrong);
    $right = acms_update_page(updateContext('3.0.5', array(), $pkg, array('phpversion' => '7.2.34')));
    assertContains('現在の PHP のバージョンが 7.2.34 です', $right);
    assertSame('OLD-INDEX', file_get_contents('index.php'));
    assertNoFile('update.lock');
});
test('page: update.json 取得失敗時はエラーを表示（導入文は出さない）', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array(), false, array('inputPass' => null)));
    assertContains('バージョンの取得に失敗しました', $html);
    assertNotContains('をアップデートします', $html);
});
test('page: 二重実行防止ロックがあれば実行しない', function () {
    makeCurrentSite();
    put('update.lock');
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage()));
    assertContains('二重に実行防止のためのファイル', $html);
    assertSame('OLD-INDEX', file_get_contents('index.php'));
});
test('page: fetchPackage 省略時は fetch_acms_package_info を使う', function () {
    makeCurrentSite();
    $ctx = updateContext('3.0.5', array(), false, array('inputPass' => null, 'wantVersion' => '9.9.9'));
    unset($ctx['fetchPackage']);
    // 実ネットワークには出ず、失敗（false）か結果のいずれでも画面が組み立てられることだけ確認する
    assertContains('簡単アップデート', acms_update_page($ctx));
})
;

// ---------- 実行 ----------
test('page: 3.x → 3.x のアップデートが完了し、カスタマイズが戻る', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage()));

    assertContains('アップデート処理の実行を完了しました', $html);
    assertNotContains('license.php', $html);

    // 新パッケージが配置されている
    assertSame('NEW-INDEX', file_get_contents('index.php'));
    assertSame('new', file_get_contents('js/new.js'));
    assertSame('NEW-LANG', file_get_contents('lang/ja.php'));
    assertSame('CORE', file_get_contents('php/ACMS/Core.php'));
    // バックアップ
    $b = 'backup_20260101000000';
    assertSame('OLD-INDEX', file_get_contents("$b/index.php"));
    assertSame('old', file_get_contents("$b/acms.js"));
    assertSame('OLD-LANG', file_get_contents("$b/lang/ja.php"));
    assertSame('UNUSED', file_get_contents("$b/themes/unused/u.html"));
    assertFile("$b/cache/.htaccess");
    assertFile("$b/extension/plugins/p.txt");
    // カスタマイズの復元
    assertSame('MYTHEME', file_get_contents('themes/mytheme/t.html'));
    assertNoFile('themes/unused');
    assertSame('CUSTOM', file_get_contents('php/ACMS/User/Custom.php'));
    assertFile('php/ACMS/User_3.2.40');
    assertSame('APP', file_get_contents('php/AAPP/App.php'));
    assertFile('php/AAPP_3.2.40');
    assertSame('SYSTEM-YAML', file_get_contents('private/config.system.yaml'));
    assertSame('PLUGIN', file_get_contents('extension/plugins/p.txt'));
    // 同梱ファイルのリネーム
    assertSame('ROOT-HT', file_get_contents('htaccess_3.2.40.txt'));
    assertSame('EC', file_get_contents('.editorconfig'));
    assertSame('ENV', file_get_contents('.env'));
    assertSame('GI', file_get_contents('.gitignore'));
    assertSame('PRIV-HT', file_get_contents('private/.htaccess'));
    assertSame('NEW-CACHE-HT', file_get_contents('cache/.htaccess')); // 新パッケージ同梱が優先
    assertNoFile('cache/junk.txt');
    // php.ini のコピー・後始末
    assertSame('ini', file_get_contents('setup/php.ini'));
    assertNoFile('update.php');
    assertNoFile('_dist.zip');
    assertNoFile('acms3.2.40_update2x');
    assertNoFile('update.lock');
});

test('page: 2.x からはライセンスを開発版へ差し替え、案内を出す', function () {
    makeCurrentSite(2);
    $html = acms_update_page(updateContext('2.10.65', array('mytheme'), makePackage()));
    assertContains('license.php ファイルのアップデート', $html);
    assertSame('DEV-LICENSE', file_get_contents('license.php'));
    assertSame('OLD-LICENSE', file_get_contents('backup_20260101000000/license.php'));
});

test('page: 新パッケージに cache/ や extension/ が無い場合も復元できる', function () {
    makeCurrentSite();
    unlink('php.ini');
    $pkg = makePackage();
    // cache/htaccess.txt を含まない zip に差し替える
    $zip = new ZipArchive();
    $zip->open(testDir() . '/fixtures/_dist.zip');
    $zip->deleteName('acms3.2.40_update2x/ablogcms/cache/htaccess.txt');
    $zip->deleteName('acms3.2.40_update2x/ablogcms/editorconfig.txt');
    $zip->deleteName('acms3.2.40_update2x/ablogcms/gitignore.txt');
    $zip->deleteName('acms3.2.40_update2x/ablogcms/htaccess.txt');
    $zip->close();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), $pkg));
    assertContains('アップデート処理の実行を完了しました', $html);
    assertSame('CACHE-HT', file_get_contents('cache/.htaccess'));
    assertNoFile('setup/php.ini');
    assertNoFile('.editorconfig');
});

test('page: 旧 cache/.htaccess が無くても新 cache/htaccess.txt を使う', function () {
    makeCurrentSite();
    removeTree('cache'); // バックアップ対象の cache/.htaccess が存在しない
    acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage()));
    assertSame('NEW-CACHE-HT', file_get_contents('cache/.htaccess'));
});

test('page: ダウンロード失敗時はロックを外してエラー', function () {
    makeCurrentSite();
    $pkg = makePackage();
    $pkg['download'] = 'file://' . testDir() . '/missing.zip';
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), $pkg));
    assertContains('a-blog cms ダウンロードエラー', $html);
    assertNoFile('update.lock');
    assertSame('OLD-INDEX', file_get_contents('index.php'));
});

test('page: 解凍失敗時はロックを外してエラー', function () {
    makeCurrentSite();
    $pkg = makePackage();
    put('fixtures/broken.zip', 'not a zip');
    $pkg['download'] = 'file://' . testDir() . '/fixtures/broken.zip';
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), $pkg));
    assertContains('a-blog cms 解凍エラー', $html);
    assertNoFile('update.lock');
});

test('page: 新パッケージに index.php が無ければ失敗として扱う', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(false)));
    assertContains('正常にアップデートができませんでした', $html);
    assertNoFile('update.lock');
});

test('page: DB パスワードが空でも、フォーム送信（POST）が無ければ実行しない', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(), array('inputPass' => null, 'dbPass' => '')));
    assertContains('name="dbpass"', $html);
    assertNotContains('パスワードが間違っています', $html);
    assertSame('OLD-INDEX', file_get_contents('index.php'));
});
test('page: DB パスワードが空なら、空欄のまま送信すれば実行できる', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(), array('inputPass' => '', 'dbPass' => '')));
    assertContains('アップデート処理の実行を完了しました', $html);
});
test('page: 型の緩い一致では通らない（0 と "" など）', function () {
    makeCurrentSite();
    $html = acms_update_page(updateContext('3.0.5', array('mytheme'), makePackage(), array('inputPass' => '0', 'dbPass' => '')));
    assertContains('パスワードが間違っています', $html);
    assertSame('OLD-INDEX', file_get_contents('index.php'));
});
