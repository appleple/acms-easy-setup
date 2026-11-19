<?php

ini_set('max_execution_time', 0);

// ----------------------
// a-blog cms 3.x 簡単アップデート
// update 2025/09/09
// ----------------------

// アップデートバージョンを指定する場合は「$ablogcmsVersion」を指定ください。
// 指定しない場合、最新バージョンにアップデートされます。

// バージョンを指定する際には以下の行頭の # を削除してください。

#$ablogcmsVersion = "3.2.0";

// ------------------------------

// これ以下は修正する必要はありません。

// テストから関数だけを読み込めるよう、定数が定義されている場合は実行しない。
// @coverage-ignore-start
if (!defined('ACMS_EASY_UPDATE_NO_RUN')) {
  acms_update_main(isset($ablogcmsVersion) ? $ablogcmsVersion : '');
  exit;
}
// @coverage-ignore-end

// --------------------------------------------------
// エントリポイント（副作用はここに集約する）
// --------------------------------------------------

// @coverage-ignore-start
/**
 * 設定とリクエストを読み込み、ページを出力する。
 * 判定・描画・ファイル操作はすべて下の関数に切り出してあり、ここは入出力の接続のみ。
 *
 * @param string $wantVersion 指定バージョン（空文字なら最新版）
 */
function acms_update_main($wantVersion)
{
  $installPath = realpath('.');
  require_once($installPath . '/config.server.php');

  $dbPort = defined('DB_PORT') ? DB_PORT : null;
  $dbh = new PDO('mysql:host=' . acms_db_host(DB_HOST, $dbPort) . ';dbname=' . DB_NAME . '', DB_USER, DB_PASS);

  echo acms_update_page(array(
    'installPath' => $installPath,
    'phpName'     => basename($_SERVER['PHP_SELF']),
    'ymdhis'      => date("YmdHis"),
    'inputPass'   => filter_input(INPUT_POST, "dbpass"),
    'dbPass'      => DB_PASS,
    'dbPrefix'    => DB_PREFIX,
    'dbh'         => $dbh,
    'wantVersion' => $wantVersion,
    'phpversion'  => phpversion(),
  ));
}
// @coverage-ignore-end

/**
 * DB_HOST と DB_PORT から PDO の DSN 用ホスト文字列を作る。
 * DB_PORT が無い場合は "host:port" 形式の DB_HOST も許容する。
 *
 * @param string          $host
 * @param string|int|null $port
 * @return string
 */
function acms_db_host($host, $port)
{
  if ($port !== null && $port !== '') {
    return $host . ";port=" . $port;
  }
  $portCheck = explode(":", $host);
  if (count($portCheck) == 2) {
    return $portCheck[0] . ";port=" . $portCheck[1];
  }
  return $host;
}

// --------------------------------------------------
// ページ組み立て
// --------------------------------------------------

/**
 * リクエスト 1 回分の処理を行い、出力する HTML を返す。
 *
 * @param array $c installPath, phpName, ymdhis, inputPass, dbPass, dbPrefix, dbh(PDO),
 *                 wantVersion, phpversion, fetchPackage(callable, 省略可・テスト用)
 * @return string
 */
function acms_update_page(array $c)
{
  $fetch = isset($c['fetchPackage']) ? $c['fetchPackage'] : 'fetch_acms_package_info';

  // 対応 PHP バージョンは a-blog cms のバージョン系列ごとに変わる。判定は
  // acms_known_php_range() の表に一本化し、update.json はバージョンと
  // ダウンロード先の解決だけに使う。
  $acmsPackage = call_user_func($fetch, $c['wantVersion'], false, $c['phpversion']);
  list($ablogcmsVersion, $errors) = acms_check_package($acmsPackage, $c['phpversion']);

  $state = acms_fetch_current_state($c['dbh'], $c['dbPrefix']);
  $themes = acms_expand_themes($state['themes']);

  $out = '';
  $lockFile = $c['installPath'] . "/update.lock";
  if (is_file($lockFile)) {
    $out .= "lockFile:" . $lockFile;
    $errors[] = "二重に実行防止のためのファイル " . $lockFile . " を発見し処理できません。";
  }

  $out .= acms_render_head();
  $out .= acms_render_intro($state['version'], $ablogcmsVersion);
  $out .= acms_render_themes($themes, acms_unused_themes($c['installPath'] . "/themes", $themes));

  $execStop = count($errors) > 0;
  $out .= acms_render_errors($errors);

  // DB パスワードが空のサイトでも、アクセスしただけで実行されないよう、フォーム送信
  // （inputPass が null でない）かつ厳密一致のときだけ実行する。
  // 緩い比較（!=）だと null や 0 が空文字と一致してしまうため使わない。
  $submitted = $c['inputPass'] !== null;
  if (!$submitted || (string) $c['inputPass'] !== (string) $c['dbPass']) {
    if (!$execStop) {
      $out .= acms_render_password_form();
    }
    if ($submitted) {
      $out .= "<p class='acms-admin-text-error'>パスワードが間違っています。</p>";
    }
    return $out . "</body></html>";
  }

  // エラーがある場合（未対応 PHP・二重実行など）は、パスワードが正しくても処理を実行しない。
  if ($execStop) {
    return $out . "</body></html>";
  }

  return $out . acms_run_update($c, $acmsPackage, $ablogcmsVersion, $state['version'], $themes);
}

// --------------------------------------------------
// 判定・取得
// --------------------------------------------------

/**
 * update.json から解決したパッケージ情報を検査し、アップデート先バージョンとエラーを返す。
 *
 * @param array|false $acmsPackage fetch_acms_package_info() の戻り値
 * @param string      $phpversion  実行中の PHP バージョン
 * @return array{0: string|null, 1: string[]} [アップデート先バージョン, エラーメッセージ一覧]
 */
function acms_check_package($acmsPackage, $phpversion)
{
  if ($acmsPackage === false) {
    return array(null, array("最新版の a-blog cms のバージョンの取得に失敗しました。<br>
  手動で update.php の中の \$ablogcmsVersion = \"3.2.0\"; を書き換え指定のバージョンを設定ください。<br>
  また # が先頭についていると未設定という扱いになりますので # があれば削除ください。"));
  }

  $version = $acmsPackage['version'];
  $errors = array();
  $versionArray = explode(".", $version);

  if ($versionArray[0] != 3) {
    $errors[] = "この簡単アップデートは 3.x 用です。<br>Ver." . $version . " のアップデートはサポートしておりません。";
  } elseif ($acmsPackage['download'] === null) {
    $errors[] = "現在の PHP のバージョンが " . $phpversion . " です。<br>a-blog cms Ver." . $version . " へのアップデートする事ができません。<br>対応する PHP バージョンをご確認ください。";
  }

  return array($version, $errors);
}

/**
 * DB から現在の a-blog cms バージョンと、設定されているテーマ名を取得する。
 *
 * @param PDO    $dbh
 * @param string $prefix テーブル接頭辞
 * @return array{version: string, themes: string[]}
 */
function acms_fetch_current_state(PDO $dbh, $prefix)
{
  $version = '';
  foreach ($dbh->query("SELECT sequence_system_version FROM " . $prefix . "sequence") as $row) {
    $version = $row['sequence_system_version'];
  }

  $themes = array();
  foreach ($dbh->query("SELECT config_value FROM " . $prefix . "config WHERE config_key = 'theme'") as $row) {
    $themes[] = $row['config_value'];
  }

  return array('version' => $version, 'themes' => $themes);
}

/**
 * 利用中テーマの一覧を、親テーマ（"child@parent" 形式の parent 側）も含めて重複なく返す。
 *
 * @param string[] $configThemes config テーブル上のテーマ名
 * @return string[]
 */
function acms_expand_themes(array $configThemes)
{
  $unique = array_unique($configThemes);

  $parents = array();
  foreach ($unique as $themeName) {
    $outTheme = array();
    $checkTheme = explode('@', $themeName);
    foreach ($checkTheme as $data) {
      array_shift($checkTheme);
      $outTheme[] = implode('@', $checkTheme);
    }
    $parents = array_merge($parents, $outTheme);
  }

  $all = array_merge($unique, $parents);
  $all = array_filter($all);
  $all = array_unique($all);
  return array_values($all);
}

/**
 * themes ディレクトリのうち、利用中でも system でもないテーマ名を返す。
 *
 * @param string   $themesDir
 * @param string[] $usedThemes
 * @return string[]
 */
function acms_unused_themes($themesDir, array $usedThemes)
{
  $unused = array();
  $handle = @opendir($themesDir);
  if ($handle === false) {
    return $unused;
  }
  while (($theme = readdir($handle)) !== false) {
    if ($theme == "." || $theme == "..") {
      continue;
    }
    if (is_file($themesDir . "/" . $theme) || $theme == "system" || in_array($theme, $usedThemes)) {
      continue;
    }
    $unused[] = $theme;
  }
  closedir($handle);
  return $unused;
}

// --------------------------------------------------
// 描画（HTML 文字列を返すだけ）
// --------------------------------------------------

function acms_render_head()
{
  return <<<'HTML'
<!DOCTYPE html>
    <html lang="ja">
    <head>
    <meta charset="UTF-8">
    <title>a-blog cms 簡単アップデート</title>
    <link rel="stylesheet" href="/themes/system/css/acms-admin.min.css">
    <style>
      body {
        padding : 10px 30px;
        background-color : #ddd;
        font-family: Courier;
      }
    </style>
    </head>
    <body>
    <h1>a-blog cms 簡単アップデート</h1>

HTML;
}

/**
 * @param string      $nowVersion  現在の a-blog cms バージョン
 * @param string|null $newVersion  アップデート先（解決できなかった場合は null）
 */
function acms_render_intro($nowVersion, $newVersion)
{
  if ($newVersion === null) {
    return '';
  }

  $out = "<p>現在の <strong>Ver." . $nowVersion . "</strong> から <strong>Ver." . $newVersion . "</strong> に a-blog cms のバージョンをアップデートします。";
  $out .= "<p>アップデート実行後に、CMS の<strong>管理者権限のユーザーID</strong> と <strong>パスワード</strong>が必要になります。<br>この処理実行後にパスワード再設定機能は利用できませんので事前に準備ください。</p>";

  if (acms_major_version($nowVersion) < 3) {
    $out .= "<p><strong>アップデートするとシステムのライセンスが開発版に切り替わります。<br><a href=\"https://mypage.a-blogcms.jp/\">MYPAGE</a> から 3.0 対応版の license.php をダウンロードください。</strong></p>";
  }
  return $out;
}

/**
 * @param string[] $used   利用中のテーマ
 * @param string[] $unused 利用していないテーマ
 */
function acms_render_themes(array $used, array $unused)
{
  $out = "<h3>利用中のテーマ</h3><ul>";
  foreach ($used as $themeName) {
    $out .= "<li>" . $themeName . "</li>";
  }
  $out .= "</ul>\n\n<h3>利用していないテーマ</h3>\n<ul>\n";
  foreach ($unused as $themeName) {
    $out .= "<li>" . $themeName . "</li>";
  }
  $out .= "</ul>";

  if (count($unused) > 0) {
    $out .= "<p style=\"color: gray;\">※ 利用していないテーマについては、アップデート時には themes から削除され、バックアップデータ側に保存されます。</p>";
  }
  return $out;
}

/**
 * @param string[] $errors
 */
function acms_render_errors(array $errors)
{
  if (count($errors) === 0) {
    return '';
  }
  $out = "<h3>エラー</h3>";
  foreach ($errors as $text) {
    $out .= "<p class='acms-admin-text-error'>" . $text . "</p>";
  }
  return $out;
}

function acms_render_password_form()
{
  return <<<'HTML'

    <form action="" method="POST" class="acms-admin-form">
      <input type="password" name="dbpass" id="dbpass" class="acms-admin-form-width-mini" placeholder="MySQL password">
      <input type="submit" class="acms-admin-btn" value="アップデート実行">
    </form>
    <p>処理を実行してよろしければ、データベースのパスワードを入力してください。</p>


HTML;
}

/**
 * @param string $nowVersion 更新前の a-blog cms バージョン
 */
function acms_render_done($nowVersion)
{
  $out = "\n<p><strong>アップデート処理の実行を完了しました。</strong></p>\n\n<h3>残作業</h3>\n<ol>\n  <li>メンテナンスツールでデータベースのアップデート</li>\n  ";
  if (acms_major_version($nowVersion) < 3) {
    $out .= "<li>license.php ファイルのアップデート (<a href=\"https://mypage.a-blogcms.jp/\">MYPAGE</a>)</li>";
  }
  $out .= "\n  <li>setup ディレクトリーの削除、またはリネーム</li>\n</ol>\n\n";
  $out .= "<a href=\"./setup/index.php\" class=\"acms-admin-btn acms-admin-btn-large\">メンテナンスツールへ</a>\n\n</body></html>\n";
  return $out;
}

/**
 * "2.10.65" のようなバージョン文字列からメジャーバージョンを取り出す。
 *
 * @param string $version
 * @return int
 */
function acms_major_version($version)
{
  $parts = explode(".", $version);
  return (int) $parts[0];
}

// --------------------------------------------------
// アップデート処理（カレントディレクトリ = 設置ディレクトリ前提）
// --------------------------------------------------

/**
 * ダウンロード → 解凍 → バックアップ → 入れ替え → 復元 → 後始末 を実行する。
 * 失敗した場合はロックを外してエラー HTML を返す。
 *
 * @param array       $c
 * @param array       $acmsPackage fetch_acms_package_info() の戻り値（download / root_dir を使う）
 * @param string      $ablogcmsVersion アップデート先バージョン
 * @param string      $nowVersion      更新前バージョン
 * @param string[]    $themes          利用中のテーマ
 * @return string 出力する HTML
 */
function acms_run_update(array $c, array $acmsPackage, $ablogcmsVersion, $nowVersion, array $themes)
{
  $installPath = $c['installPath'];
  $lockFile = $installPath . "/update.lock";
  $nowMajor = acms_major_version($nowVersion);

  touch($lockFile);

  // ダウンロード元 URL は update.json 由来。acmsX.Y.Z_updateNx.zip のようなアップデート用
  // パッケージ名をハードコード組み立てせず、API が返す URL / root_dir をそのまま利用する。
  // cache/storage のように現在稼働中サイトに既存のディレクトリと衝突しうるものは
  // dir_shori() 側の存在チェックで上書きを避ける設計にしているため、系列に応じた
  // URL の出し分けはしない。
  $download = $acmsPackage['download'];
  $zipFile = "./" . basename(parse_url($download, PHP_URL_PATH));
  $ablogcmsDir = $installPath . "/" . $acmsPackage['root_dir'];

  // 解凍後の全体フォルダ名（root_dir の先頭セグメント）＝処理完了後に丸ごと削除する一時ディレクトリ
  $rootDirParts = explode("/", $acmsPackage['root_dir']);
  $zipAfterDirName = $rootDirParts[0];

  if (!acms_download_file($download, $zipFile)) {
    unlink($lockFile);
    return "<p class='acms-admin-text-error'>a-blog cms ダウンロードエラー</p></body></html>";
  }

  if (!acms_extract_zip($zipFile, $installPath)) {
    unlink($lockFile);
    return "<p class='acms-admin-text-error'>a-blog cms 解凍エラー : " . $zipFile . "</p></body></html>";
  }

  $backupDir = "backup_" . $c['ymdhis'];
  acms_backup_current($backupDir, $nowMajor);

  // update版 ファイル＆ディレクトリを移動
  dir_shori("move", $ablogcmsDir, $installPath);

  // 3.0 以前の場合にはライセンスファイルが開発ライセンスになる。
  if ($nowMajor < 3) {
    rename($installPath . "/" . $zipAfterDirName . "/omake/license.php", "./license.php");
  }

  acms_restore_customizations($backupDir, $ablogcmsVersion, $themes);
  acms_rename_bundled_files($backupDir, $ablogcmsVersion, $installPath, $zipAfterDirName);

  // php.ini があった時の処理
  if (is_file("./php.ini")) {
    copy("./php.ini", "./setup/php.ini");
  }

  unlink($zipFile);
  unlink($c['phpName']);

  // プログラム以外のディレクトリを削除
  if (!is_file("./index.php")) {
    unlink($lockFile);
    return "<p class='acms-admin-text-error'>正常にアップデートができませんでした。</p></body></html>";
  }
  dir_shori("delete", $zipAfterDirName);

  unlink($lockFile);
  return acms_render_done($nowVersion);
}

/**
 * URL からファイルをダウンロードして保存する。
 *
 * @return bool 成功したら true
 */
function acms_download_file($url, $dest)
{
  $fp = @fopen($url, "r");
  if ($fp === false) {
    return false;
  }
  file_put_contents($dest, "");
  while (!feof($fp)) {
    $buffer = fread($fp, 4096);
    if ($buffer !== false) {
      file_put_contents($dest, $buffer, FILE_APPEND);
    }
  }
  fclose($fp);
  return true;
}

/**
 * zip を $destDir へ展開する。
 *
 * @return bool 成功したら true
 */
function acms_extract_zip($zipFile, $destDir)
{
  $zip = new ZipArchive();
  if ($zip->open($zipFile) !== true) {
    return false;
  }
  $zip->extractTo($destDir);
  $zip->close();
  return true;
}

/**
 * 現行ファイルをバックアップディレクトリへ退避する。
 *
 * @param string $backupDir 退避先
 * @param int    $nowMajor  更新前のメジャーバージョン
 */
function acms_backup_current($backupDir, $nowMajor)
{
  mkdir($backupDir);

  foreach (array("acms.js", "index.js", "500.html") as $file) {
    if (is_file("./" . $file)) {
      rename("./" . $file, $backupDir . "/" . $file);
    }
  }

  rename("./index.php", $backupDir . "/index.php");

  if ($nowMajor < 3) {
    rename("./license.php", $backupDir . "/license.php");
  }

  foreach (array("js", "lang", "php", "private", "themes") as $dir) {
    dir_shori("move", "./" . $dir, $backupDir . "/" . $dir);
  }

  if (is_dir("./extension")) {
    dir_shori("move", "./extension", $backupDir . "/extension");
  }

  if (is_file("./cache/.htaccess")) {
    mkdir($backupDir . "/cache");
    rename("./cache/.htaccess", $backupDir . "/cache/.htaccess");
  }
  if (is_dir("./cache")) {
    dir_shori("delete", "cache");
  }
}

/**
 * バックアップからカスタマイズ部分（テーマ・User・AAPP・設定・extension）を戻す。
 *
 * @param string   $backupDir
 * @param string   $ablogcmsVersion 新パッケージ側の退避名に付けるバージョン
 * @param string[] $themes          利用中のテーマ
 */
function acms_restore_customizations($backupDir, $ablogcmsVersion, array $themes)
{
  // 利用しているテーマを戻す
  foreach ($themes as $themeName) {
    dir_shori("copy", $backupDir . "/themes/" . $themeName, "./themes/" . $themeName);
  }

  // /php/ACMS/User を戻す
  rename("./php/ACMS/User", "./php/ACMS/User_" . $ablogcmsVersion);
  dir_shori("copy", $backupDir . "/php/ACMS/User", "./php/ACMS/User");

  // php/AAPP を戻す
  rename("./php/AAPP", "./php/AAPP_" . $ablogcmsVersion);
  dir_shori("copy", $backupDir . "/php/AAPP", "./php/AAPP");

  // /private/config.system.yaml を戻す
  // アップデート用パッケージに config.system.yaml が同梱されない場合もあるため存在チェックを追加。
  if (is_file("./private/config.system.yaml")) {
    rename("./private/config.system.yaml", "./private/config.system_" . $ablogcmsVersion . ".yaml");
  }
  copy($backupDir . "/private/config.system.yaml", "./private/config.system.yaml");

  // /extension を戻す
  if (is_dir($backupDir . "/extension")) {
    // アップデート用パッケージに extension が同梱されない場合もあるため存在チェックを追加。
    if (is_dir("./extension")) {
      rename("./extension", "./extension_" . $ablogcmsVersion);
    }
    dir_shori("copy", $backupDir . "/extension", "./extension");
  }
}

/**
 * 同梱の htaccess.txt / editorconfig.txt / env.txt / gitignore.txt を実ファイル名へリネームする。
 *
 * @param string $backupDir
 * @param string $ablogcmsVersion
 * @param string $installPath
 * @param string $zipAfterDirName 展開後の一時ディレクトリ名（走査対象から除外する）
 */
function acms_rename_bundled_files($backupDir, $ablogcmsVersion, $installPath, $zipAfterDirName)
{
  // root の htaccess.txt はマージ処理を行わずバージョン付きファイル名へ退避する
  // （従来の挙動を維持。新パッケージに同梱されない場合もあるため存在チェックを追加）。
  if (is_file("./htaccess.txt")) {
    rename("./htaccess.txt", './htaccess_' . $ablogcmsVersion . '.txt');
  }

  // editorconfig.txt/gitignore.txt もアップデート用パッケージに同梱されない場合があるため
  // 存在チェックを追加（無ければ既存の .editorconfig/.gitignore をそのまま維持する）。
  if (is_file("./editorconfig.txt")) {
    rename("./editorconfig.txt", './.editorconfig');
  }
  rename("./env.txt", './.env');
  if (is_file("./gitignore.txt")) {
    rename("./gitignore.txt", './.gitignore');
  }

  // cache はバックアップからの復元 or 新規リネームという専用ロジックがあるため、
  // 下記の汎用走査からは除外し、ここで個別に処理する。
  if (!is_dir("./cache")) {
    mkdir("./cache");
    if (is_file($backupDir . "/cache/.htaccess")) {
      rename($backupDir . "/cache/.htaccess", "./cache/.htaccess");
    }
  } elseif (is_file("./cache/htaccess.txt")) {
    rename("./cache/htaccess.txt", './cache/.htaccess');
  }

  // root・cache 以外に同梱される htaccess.txt を .htaccess へ一括リネームする。
  // private/themes のようにディレクトリをハードコード列挙すると cron や extension
  // （3.2 系で追加された同梱物）が漏れるため、実際に展開されたファイルを走査する方式に
  // 変更し、将来の増減にも自動追従させる。バックアップ・一時展開ディレクトリは
  // 触れるべきでないため除外する。
  rename_bundled_htaccess($installPath, array("cache", $backupDir, $zipAfterDirName));
}

// --------------------------------------------------
// ディレクトリを操作 function ( move / copy / delete )
// --------------------------------------------------

function dir_shori ($shori, $nowDir , $newDir="") {

  if ($shori != "delete") {
    if (!is_dir($newDir)) {
      mkdir($newDir);
    }
  }

  if (is_dir($nowDir)) {
    if ($handle = opendir($nowDir)) {
      while (($file = readdir($handle)) !== false) {
        if ($file != "." && $file != "..") {
          if ($shori == "copy") {
            if (is_dir($nowDir."/".$file)) {
              dir_shori("copy", $nowDir."/".$file, $newDir."/".$file);
            } else {
              copy($nowDir."/".$file, $newDir."/".$file);
            }
          } elseif ($shori == "move") {
            if (is_dir($nowDir."/".$file)) {
              if (is_dir($newDir."/".$file)) {
                // 移動先に既に同名ディレクトリがある場合（運用中サイトの
                // cache/storage 等）は上書きせず、中身だけを再帰的にマージする
                // （既存ファイルは残し、無いファイルだけ新パッケージ側から補う）。
                dir_shori("move", $nowDir."/".$file, $newDir."/".$file);
              } else {
                rename($nowDir."/".$file, $newDir."/".$file);
              }
            } elseif (file_exists($newDir."/".$file)) {
              // 移動先に既存の同名ファイルがある場合は上書きしない。
              continue;
            } else {
              rename($nowDir."/".$file, $newDir."/".$file);
            }
          } elseif ($shori == "delete") {
            if (filetype($nowDir."/".$file) == "dir") {
              dir_shori("delete", $nowDir."/".$file, "");
            } else {
              unlink($nowDir."/".$file);
            }
          }
        }
      }
      closedir($handle);
    }
  }

  if ($shori == "move" || $shori == "delete") {
    // マージによりスキップしたファイルが残っている場合 rmdir は失敗しうるが、
    // この一時ディレクトリは後続処理で親ごと削除されるため実害はない。
    @rmdir($nowDir);
  }

  return true;
}

// --------------------------
// htaccess.txt を .htaccess へ一括リネーム
// --------------------------
/**
 * $baseDir 配下（$baseDir 自身の直下ファイルは除く）を再帰的に走査し、
 * 同梱されている htaccess.txt をすべて .htaccess へリネームする。
 * root の htaccess.txt は別途処理があるため、この関数は $baseDir の
 * サブディレクトリのみを対象にする（$baseDir 直下は呼び出し側で処理済みの前提）。
 *
 * @param string $baseDir     走査を開始するディレクトリ（通常は installPath）
 * @param string[] $excludeDirs $baseDir 直下で走査から除外するディレクトリ名（cache・バックアップ等）
 */
function rename_bundled_htaccess($baseDir, array $excludeDirs = array())
{
  if (!is_dir($baseDir) || !($handle = opendir($baseDir))) {
    return;
  }
  while (($entry = readdir($handle)) !== false) {
    if ($entry === "." || $entry === "..") {
      continue;
    }
    $path = $baseDir . "/" . $entry;
    if (is_dir($path) && !in_array($entry, $excludeDirs, true)) {
      rename_bundled_htaccess_dir($path);
    }
  }
  closedir($handle);
}

/**
 * $dir 自身に htaccess.txt があれば .htaccess へリネームし、サブディレクトリも再帰的に処理する。
 */
function rename_bundled_htaccess_dir($dir)
{
  $htaccessTxt = $dir . "/htaccess.txt";
  if (is_file($htaccessTxt)) {
    if (is_file($dir . "/.htaccess")) {
      // 既に .htaccess がある場合、新パッケージ由来の htaccess.txt は不要な残骸なので削除する
      unlink($htaccessTxt);
    } else {
      rename($htaccessTxt, $dir . "/.htaccess");
    }
  }
  if (!($handle = opendir($dir))) {
    return;
  }
  while (($entry = readdir($handle)) !== false) {
    if ($entry === "." || $entry === "..") {
      continue;
    }
    $path = $dir . "/" . $entry;
    if (is_dir($path)) {
      rename_bundled_htaccess_dir($path);
    }
  }
  closedir($handle);
}

/**
 * a-blog cms 3.x の「バージョン別 対応 PHP レンジ」を返す。
 *
 * 対応 PHP の管理はこの表に一本化している（update.json の php_*_version は使わない）。
 * update.json は系列の最新パッチ 1 件分の範囲しか持たず、過去パッチ指定
 * （例: PHP 7.2 で 3.1.0）を正しく判定できないため。
 * PHP 対応を変更するときはこの表だけを更新すること（requirements.html と揃える）。
 *
 * @param string $version a-blog cms のバージョン（例 "3.1.6"）
 * @return array{0: string, 1: string}|null [PHP 下限, PHP 上限] 。表に無いバージョンは null
 */
function acms_known_php_range($version)
{
  // [CMS 下限, CMS 上限(null=無制限), PHP 下限, PHP 上限]  ※上から順に最初に一致した行を採用
  $table = array(
    array('3.0.0',  '3.0.11', '7.2.5', '8.0.999'),
    array('3.0.12', '3.0.999', '7.2.5', '8.1.999'),
    array('3.1.0',  '3.1.6',  '7.2.5', '8.1.999'),
    array('3.1.7',  '3.1.13', '7.3.0', '8.1.999'),
    array('3.1.14', '3.1.999', '7.3.0', '8.3.999'),
    array('3.2.0',  '3.2.999', '8.1.0', '8.5.999'),
  );

  foreach ($table as $row) {
    if (version_compare($version, $row[0], '>=') && version_compare($version, $row[1], '<=')) {
      return array($row[2], $row[3]);
    }
  }
  return null;
}

/**
 * update.json (https://www.a-blogcms.jp/api/update.json) から
 * 対象バージョンとダウンロードパッケージ情報を取得する。
 *
 * @param string $wantVersion        指定バージョン（空文字なら最新版 = semver 最大を採用）
 * @param bool   $forInstall         true: 新規インストール用フルパッケージを解決する。
 *                                   update.json の packages[].download / root_dir は
 *                                   アップデート用（"acmsX.Y.Z_updateNx.zip" 命名）のみを
 *                                   提供しているため、インストール用途では "_updateNx" を
 *                                   取り除いた URL / ディレクトリ名に変換する
 *                                   （実サーバー上に同名のフルパッケージが存在することを確認済み）。
 *                                   update.php では false を渡し、API が返す
 *                                   アップデート用パッケージをそのまま利用する。
 * @param string $phpVersionForCheck PHP バージョン適合チェックに使う値
 * @param string $apiUrl             update.json の URL（テストで差し替える）
 *
 * @return array{version: string, download: string|null, root_dir: string|null}|false
 *   失敗時は false。成功時は 'download'/'root_dir' に対応パッケージが無い場合 null が入る。
 */
function fetch_acms_package_info($wantVersion, $forInstall, $phpVersionForCheck, $apiUrl = 'https://www.a-blogcms.jp/api/update.json')
{
  // "8.1" のような major.minor のみの表記は、桁数の異なる "8.1.0" との
  // version_compare で意図せず「未満」と判定されてしまう（PHP の既知の挙動）ため、
  // patch 部分を 0 で補って正規化してから比較する。
  $phpVersionParts = explode('.', $phpVersionForCheck);
  while (count($phpVersionParts) < 3) {
    $phpVersionParts[] = '0';
  }
  $phpVersionForCheck = implode('.', $phpVersionParts);

  $options = array();
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;

  $json = @file_get_contents($apiUrl, false, stream_context_create($options));
  if ($json === false) {
    return false;
  }

  $data = json_decode($json, true);
  if (!is_array($data) || empty($data['versions']) || !is_array($data['versions'])) {
    return false;
  }

  $target = null;
  foreach ($data['versions'] as $v) {
    if (!isset($v['version'])) {
      continue;
    }
    if ($wantVersion !== '') {
      if ($v['version'] === $wantVersion) {
        $target = $v;
        break;
      }
    } elseif ($target === null || version_compare($v['version'], $target['version'], '>')) {
      $target = $v;
    }
  }

  // update.json は各メジャー.マイナー系列の最新パッチのみを収録しており、
  // 過去パッチ（例: 3.2.26 系列で 3.2.25 を指定した場合）は一覧に無い。
  // 完全一致が見つからない場合は、同じ系列の最新パッケージ情報
  // （PHP 対応レンジ・URL 命名パターン）を流用し、バージョン文字列部分だけ
  // 指定値に差し替えて推測する（実在するとは限らないため、呼び出し側で
  // 実 URL の存在確認を行うこと）。
  $fallbackFromVersion = null;
  if ($wantVersion !== '' && $target === null) {
    $wantParts = explode('.', $wantVersion);
    if (count($wantParts) >= 2) {
      $wantMajorMinor = $wantParts[0] . '.' . $wantParts[1];
      foreach ($data['versions'] as $v) {
        if (!isset($v['version'])) {
          continue;
        }
        $vParts = explode('.', $v['version']);
        if (count($vParts) < 2 || ($vParts[0] . '.' . $vParts[1]) !== $wantMajorMinor) {
          continue;
        }
        if ($target === null || version_compare($v['version'], $target['version'], '>')) {
          $target = $v;
        }
      }
      if ($target !== null) {
        $fallbackFromVersion = $target['version'];
      }
    }
  }

  if ($target === null) {
    return false;
  }

  $result = array(
    'version'  => ($fallbackFromVersion !== null) ? $wantVersion : $target['version'],
    'download' => null,
    'root_dir' => null,
  );

  if (empty($target['packages']) || !is_array($target['packages'])) {
    return $result;
  }

  // 対応 PHP は update.php 内の表を優先し、表に無い系列だけ update.json の範囲を使う
  $knownRange = acms_known_php_range($result['version']);

  foreach ($target['packages'] as $pkg) {
    if (!isset($pkg['php_min_version'], $pkg['php_max_version'], $pkg['download'], $pkg['root_dir'])) {
      continue;
    }

    if ($knownRange !== null) {
      $min = $knownRange[0];
      $max = $knownRange[1];
    } else {
      $min = $pkg['php_min_version'];
      // "8.4.x" のような表記を上限比較できる値に正規化する
      $max = preg_replace('/\.x$/', '.999', $pkg['php_max_version']);
    }

    if (
      version_compare($phpVersionForCheck, $min, '>=') &&
      version_compare($phpVersionForCheck, $max, '<=')
    ) {
      $download = $pkg['download'];
      $rootDir  = $pkg['root_dir'];

      if ($fallbackFromVersion !== null) {
        // 同系列の最新バージョン文字列（例 "3.2.26"）を指定バージョン（例 "3.2.25"）へ
        // 差し替える。実在するとは限らないため、呼び出し側で実 URL の存在確認を行うこと。
        $download = str_replace($fallbackFromVersion, $wantVersion, $download);
        $rootDir  = str_replace($fallbackFromVersion, $wantVersion, $rootDir);
      }

      if ($forInstall) {
        // update.json はアップデート用パッケージ（acmsX.Y.Z_updateNx.zip）のみを提供するため、
        // 新規インストール用フルパッケージ（acmsX.Y.Z.zip、setup/ 一式を含む）のパスを
        // 命名規則から導出する（"_updateNx" 部分を除去）。
        $download = preg_replace('/_update\d+x(?=\.zip$)/', '', $download);
        $rootDir  = preg_replace('#_update\d+x(?=/|$)#', '', $rootDir);
      }

      // HTTP は HTTPS に寄せる
      $download = preg_replace('#^http://#', 'https://', $download);

      $result['download'] = $download;
      $result['root_dir'] = $rootDir;
      break;
    }
  }

  return $result;
}
