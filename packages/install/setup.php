<?php

ini_set('max_execution_time', 0);

// ------------------------------
// a-blog cms 3.2.x 簡単セットアップ
//       last update 2025/09/09
// ------------------------------

$ablogcmsVersion = '';

# $ablogcmsVersion = '3.2.0';
# $ablogcmsVersion = '3.1.53';

// ERROR になる場合や個別に 3.0.x系のバージョンを
// 指定したい場合には、バージョンを設定してください。
//
// 2.x のバージョンについては 2.x系の簡単セットアップをご利用ください。

// --------------------------

# インストーラー の
# MySQL の設定を事前に行う場合に
# ここを設定してください。

$dbHost     = '';
$dbName     = '';
$dbCreate   = '';
$dbUser     = '';
$dbPass     = '';

// --------------------------
// CPI向け PHP設定
// --------------------------

# .htaccess で PHPのバージョン指定が必要です。
# 動作させる PHP のバージョンを指定してください。
# ACE01 2011 利用できません
# ACE01 2015 PHP 7.2 / 7.4
# ACE01 2018 PHP 7.2 / 7.3 / 7.4 / 8.0
# SV-Basic   PHP 7.2 / 7.3 / 7.4 / 8.0 / 8.1
# ビジネス スタンダード PHP 7.4 / 8.0 / 8.1

$cpi_php_version = "8.1";

// --------------------------
// 特製テーマ設定
// --------------------------


// 特製テーマのインストール元を指定

$theme_download_url = "http://www.a-blogcms.jp/_download/";

# $theme_zip_file = "square@ec.zip"; # カード決済対応 ECテーマ

// GitHub Releases で配布されているテーマを使う場合は、
// $theme_download_url もダウンロード元の URL に合わせて書き換えてください。
# $theme_download_url = "https://github.com/appleple/acms-develop/releases/latest/download/";
# $theme_zip_file = "develop.zip"; # develop テーマ
# $theme_download_url = "https://github.com/appleple/acms-utsuwa/releases/latest/download/";
# $theme_zip_file = "utsuwa.zip"; # utsuwa テーマ一式

// --------------------------
// 拡張アプリ設定
// --------------------------

// 拡張アプリのインストール元を指定

$plugins_download_url = "http://www.a-blogcms.jp/_download/";

// 拡張アプリのインストールする zip ファイル名を指定

# $plugins_zip_file = "ShoppingCart_100.zip";

// --------------------------

// テストから関数だけを読み込めるよう、定数が定義されている場合は実行しない。
// @coverage-ignore-start
if (!defined('ACMS_EASY_SETUP_NO_RUN')) {
  acms_setup_main(array(
    'ablogcmsVersion'    => $ablogcmsVersion,
    'dbHost'             => $dbHost,
    'dbName'             => $dbName,
    'dbCreate'           => $dbCreate,
    'dbUser'             => $dbUser,
    'dbPass'             => $dbPass,
    'cpiPhpVersion'      => $cpi_php_version,
    'themeDownloadUrl'   => $theme_download_url,
    'themeZipFile'       => isset($theme_zip_file) ? $theme_zip_file : null,
    'pluginsDownloadUrl' => $plugins_download_url,
    'pluginsZipFile'     => isset($plugins_zip_file) ? $plugins_zip_file : null,
  ));
  exit;
}
// @coverage-ignore-end

// --------------------------------------------------
// エントリポイント（副作用はここに集約する）
// --------------------------------------------------

// @coverage-ignore-start
/**
 * 実行環境とリクエストを読み取り、ページを出力する。
 * 判定・描画・ファイル操作はすべて下の関数に切り出してあり、ここは入出力の接続のみ。
 *
 * @param array $settings 先頭の設定値（acms_setup_page の $c にそのまま渡す）
 */
function acms_setup_main(array $settings)
{
  $c = $settings;
  $c['phpversion']  = phpversion();
  $c['serverHost']  = gethostbyaddr($_SERVER['SERVER_ADDR']);
  $c['httpHost']    = $_SERVER['HTTP_HOST'];
  $c['phpName']     = basename($_SERVER['PHP_SELF']);
  $c['installPath'] = realpath('.');
  $c['action']      = filter_input(INPUT_POST, "action");
  $c['isDdev']      = getenv('IS_DDEV_PROJECT') == 'true';
  $c['cfgFilePath'] = (string) get_cfg_var('cfg_file_path');
  $c['mdHi']        = date("mdHi");
  $c['mampHttpdConf'] = '/Applications/MAMP/conf/apache/httpd.conf';

  echo acms_setup_page($c);
}
// @coverage-ignore-end

// --------------------------------------------------
// ページ組み立て
// --------------------------------------------------

/**
 * リクエスト 1 回分の処理を行い、出力する HTML を返す。
 *
 * @param array $c 設定値（acms_setup_main の $settings）と実行環境
 *                 （phpversion, serverHost, httpHost, phpName, installPath, action, isDdev,
 *                  cfgFilePath, mdHi, mampHttpdConf）。
 *                  テスト用に fetchPackage / httpStatus(callable) を差し替えられる。
 * @return string
 */
function acms_setup_page(array $c)
{
  $fetch = isset($c['fetchPackage']) ? $c['fetchPackage'] : 'fetch_acms_package_info';
  $httpStatus = isset($c['httpStatus']) ? $c['httpStatus'] : 'fetch_final_http_status';

  $errors = array();

  $versionArray = explode(".", $c['phpversion']);
  $phpVersion = $versionArray[0] . "." . $versionArray[1];

  $cpiCheck = acms_detect_cpi($c['serverHost'], $c['httpHost']);
  $cpiHtaccessPhp = '';
  if ($cpiCheck == "secure") {
    if ($c['cpiPhpVersion']) {
      $phpVersion = $c['cpiPhpVersion'];
    }
    $cpiHtaccessPhp = str_replace('.', '', $phpVersion);
  }

  if (is_file($c['installPath'] . "/license.php")) {
    $errors[] = "インストール先に license.php が見つかりました。<br>インストールを中止します。";
  }

  // 対応 PHP の判定は acms_known_php_range() の表に一本化し、update.json は
  // バージョンとダウンロード先の解決だけに使う（CPI 環境では $cpi_php_version 側で判定）。
  $acmsPackage = call_user_func($fetch, $c['ablogcmsVersion'], true, $phpVersion);
  $ablogcmsVersion = $c['ablogcmsVersion'];
  $zipFile = "";
  $download = "";

  if ($acmsPackage === false) {
    $errors[] = "a-blog cms のバージョン情報の取得に失敗しました（update.json）。<br>手動で \$ablogcmsVersion にバージョンを指定するか、しばらく時間を置いて再度お試しください。";
  } else {
    $ablogcmsVersion = $acmsPackage['version'];
    $zipFile = sprintf("./acms%s.zip", $ablogcmsVersion);

    if ($acmsPackage['download'] === null) {
      if ($cpiCheck === 'secure') {
        $errors[] = sprintf(
          '%s の $cpi_php_version が a-blog cms Ver.%s に対応していません。設定を見直してください。',
          $c['phpName'],
          $ablogcmsVersion
        );
      } else {
        $errors[] = sprintf(
          '現在の PHP バージョン（%s）は a-blog cms Ver.%s に対応していません。PHP のバージョンをご確認ください。',
          $c['phpversion'],
          $ablogcmsVersion
        );
      }
    } else {
      $download = $acmsPackage['download'];
      if ((string) call_user_func($httpStatus, $download) !== "200") {
        $errors[] = "a-blog cms のダウンロード先の確認に失敗しました。バージョン「" . $ablogcmsVersion . "」をご確認ください。";
      }
    }
  }

  $action = $c['action'];
  if ($zipFile !== "" && is_file($c['installPath'] . "/" . $zipFile)) {
    $action = ""; // 前回のダウンロード済み zip が残っている場合は、再実行させない
  }

  $out = acms_render_setup_head($ablogcmsVersion);

  // エラーがある場合（license.php 検出・未対応 PHP など）は、実行ボタン押下でも処理しない。
  if ($action == "セットアップ開始" && count($errors) === 0) {
    $c['ablogcmsVersion'] = $ablogcmsVersion;
    return $out . acms_setup_run($c, $acmsPackage, $download, $zipFile, $cpiCheck, $cpiHtaccessPhp);
  }

  return $out . acms_render_setup_form($c, $errors, $phpVersion, $cpiCheck, $httpStatus);
}

/**
 * CPI（SV-Basic 等）環境かどうかを判定する。
 *
 * @param string $serverHost gethostbyaddr(SERVER_ADDR) の結果
 * @param string $httpHost   HTTP_HOST
 * @return string "secure" なら CPI。それ以外はホスト名の第 2 ラベル（無ければ空文字）
 */
function acms_detect_cpi($serverHost, $httpHost)
{
  $check = "";
  $labels = explode(".", $serverHost);
  if (count($labels) > 1) {
    $check = $labels[1];
  }
  if (strpos($httpHost, 'smartrelease') !== false) {
    $check = "secure";
  }
  return $check;
}

/**
 * ローカル開発環境（DDEV / localhost / MAMP）向けに DB 初期値を上書きする。
 *
 * @param array $c 設定値（dbHost 等）と環境（isDdev, httpHost, cfgFilePath, mdHi, ablogcmsVersion）
 * @return array{dbHost: string, dbName: string, dbCreate: string, dbUser: string, dbPass: string}
 */
function acms_resolve_db_defaults(array $c)
{
  $db = array(
    'dbHost'   => $c['dbHost'],
    'dbName'   => $c['dbName'],
    'dbCreate' => $c['dbCreate'],
    'dbUser'   => $c['dbUser'],
    'dbPass'   => $c['dbPass'],
  );

  $hostParts = explode(":", $c['httpHost']);

  if ($c['isDdev']) {
    return array('dbHost' => 'db', 'dbName' => 'db', 'dbCreate' => '', 'dbUser' => 'db', 'dbPass' => 'db');
  }

  if ($hostParts[0] == 'localhost') {
    $db['dbHost'] = '127.0.0.1';
    $db['dbName'] = 'DBacms_' . str_replace(".", "", $c['ablogcmsVersion']) . "_" . $c['mdHi'];
    $db['dbCreate'] = 'checked';
    $db['dbUser'] = 'root';
    $db['dbPass'] = (strpos($c['cfgFilePath'], 'MAMP') !== false) ? 'root' : '';
  }
  return $db;
}

// --------------------------------------------------
// 描画（HTML 文字列を返すだけ）
// --------------------------------------------------

function acms_render_setup_head($ablogcmsVersion)
{
  $html = <<<'HTML'

<!DOCTYPE html>
    <html lang="ja">
    <head>
    <meta charset="UTF-8">
    <title>a-blog cms Ver. 3.x 簡単セットアップ</title>
    <style>
      body {
        padding : 10px 30px;
        background-color : #ddd;
        font-family: Futura;
      }
      input {
        font-size: 18px;
        font-weight : bold;
        padding :5px 20px;
        margin-top : 20px;
      }
      li {
        font-weight : bold;
      }
      p.error {
        color : #A00;
        font-weight : bold;
      }
    </style>
    <script>
      var set=0;
      function double() {
        if(set==0){ set=1; } else {
          alert("ただいまセットアップ中です。\nしばらく、お待ちください。");
          return false; }}
    </script>
    </head>
    <body>

HTML;
  return $html . "    <h1>a-blog cms Ver. " . $ablogcmsVersion . " 簡単セットアップ</h1>\n";
}

/**
 * 実行前の確認画面（PHP バージョン・特製テーマ・拡張アプリの確認とエラー表示）。
 *
 * @param array    $c
 * @param string[] $errors      ここまでに見つかったエラー
 * @param string   $phpVersion  判定に使った PHP バージョン（CPI では $cpi_php_version）
 * @param string   $cpiCheck
 * @param callable $httpStatus  URL の最終 HTTP ステータスを返す関数
 */
function acms_render_setup_form(array $c, array $errors, $phpVersion, $cpiCheck, $httpStatus)
{
  $isCpi = ($c['cpiPhpVersion'] && $cpiCheck == "secure");

  $out = "\n  <p>a-blog cms のパッケージのダウンロードとファイルのリネーム作業を行います。</p>\n\n  <h2>PHP バージョンチェック</h2>\n\n  <ul><li>Ver. ";
  if ($isCpi) {
    $out .= "<del>";
  }
  $out .= $c['phpversion'];
  if ($isCpi) {
    $out .= "</del> → " . $phpVersion . "(変更)";
  }
  $out .= "</li></ul>\n";

  if (isset($c['themeZipFile'])) {
    $themeName = explode("_", explode(".", $c['themeZipFile'])[0]);
    $out .= "<h2>特製テーマをインストール</h2>";

    // GitHub Releases の releases/latest/download/... のようにリダイレクトを
    // 経由する URL でも正しく判定できるよう、最終的なステータスコードで確認する。
    if ((string) call_user_func($httpStatus, $c['themeDownloadUrl'] . $c['themeZipFile']) != "200") {
      $errors[] = "特製テーマ「" . $themeName[0] . "」のダウンロード先の情報が間違っています。";
      $out .= "<ul><li><del>" . $themeName[0] . "</del></li></ul>";
    } else {
      $out .= "<ul><li>" . $themeName[0] . "</li></ul>";
    }
  }

  if (isset($c['pluginsZipFile'])) {
    $out .= "<h2>Plugins Install</h2><ul>";
    foreach (explode("|", $c['pluginsZipFile']) as $pluginsZip) {
      $pluginsName = explode("_", explode(".", $pluginsZip)[0]);
      if ((string) call_user_func($httpStatus, $c['pluginsDownloadUrl'] . $pluginsZip) != "200") {
        $errors[] = "拡張アプリ「" . $pluginsName[0] . "」のダウンロード先の情報が間違っています。";
        $out .= "<li><del>" . $pluginsName[0] . "</del></li>";
      } else {
        $out .= "<li>" . $pluginsName[0] . "</li>";
      }
    }
    $out .= "</ul>";
  }

  if (empty($errors)) {
    $out .= acms_mamp_rewrite_warning($c['cfgFilePath'], $c['mampHttpdConf']);
    $out .= "\n<form action=\"" . $c['phpName'] . "\" method=\"POST\" onSubmit=\"return double()\">\n<input type=\"submit\" name=\"action\" value=\"セットアップ開始\">\n</form>\n";
  } else {
    $out .= "<h2>Error</h2>";
    foreach ($errors as $msg) {
      $out .= sprintf("<p class='error'>%s</p>", $msg);
    }
  }
  return $out;
}

/**
 * MAMP の httpd.conf で rewrite_module が無効な場合の警告 HTML を返す。
 *
 * @return string 警告が不要なら空文字
 */
function acms_mamp_rewrite_warning($cfgFilePath, $httpdConf)
{
  if (strpos($cfgFilePath, 'MAMP') === false || !is_file($httpdConf)) {
    return '';
  }
  foreach (file($httpdConf) as $line) {
    if (strpos($line, 'rewrite_module') !== false && preg_match('/^\s*#/', $line)) {
      return "<h2>MAMP httpd.confチェック</h2><p>rewrite_module の行がコメントアウトされており、このままでは a-blog cms が動作しません。<br>httpd.conf を修正し、バックアップとして httpd.conf.backup を生成します。";
    }
  }
  return '';
}

function acms_render_setup_done($phpName, $mampMsg)
{
  return "\n  <h2>セットアップ完了</h2>\n\n  <p>a-blog cms のインストール準備が完了しました。</p>\n  <p>この " . $phpName . "ファイルについては削除済みです。</p>\n\n  " . $mampMsg . "\n\n  <form action=\"index.php\" method=\"POST\">\n  <input type=\"submit\" name=\"action\" value=\"インストーラーへ移動\">\n  </form>\n\n";
}

// --------------------------------------------------
// セットアップ処理（カレントディレクトリ = 設置ディレクトリ前提）
// --------------------------------------------------

/**
 * ダウンロード → 解凍 → 配置 → .htaccess 設定 → 特製テーマ・拡張アプリ導入 を実行する。
 *
 * @param array  $c
 * @param array  $acmsPackage      fetch_acms_package_info() の戻り値（root_dir を使う）
 * @param string $download         パッケージ URL
 * @param string $zipFile          ダウンロード先 zip のパス
 * @param string $cpiCheck
 * @param string $cpiHtaccessPhp   CPI 用 AddHandler に使う PHP バージョン（例 "81"）
 * @return string 出力する HTML（失敗時はエラーメッセージ）
 */
function acms_setup_run(array $c, array $acmsPackage, $download, $zipFile, $cpiCheck, $cpiHtaccessPhp)
{
  $installPath = $c['installPath'];

  // 解凍後の a-blog cms のディレクトリ（update.json の root_dir をそのまま利用する。
  // "acms{version}/ablogcms" のハードコード組み立てをやめ、パッケージ構成の変化に追従する）
  $ablogcmsDir = $installPath . "/" . $acmsPackage['root_dir'] . "/";

  // root_dir の先頭セグメント（例 "acms3.2.26"）＝解凍後にできる一時ディレクトリ名。
  $rootDirParts = explode("/", $acmsPackage['root_dir']);
  $zipAfterDirName = $rootDirParts[0];

  $db = acms_resolve_db_defaults($c);

  // 失敗時は zip を残さない。残っていると次回アクセス時に「前回の zip あり」と
  // 判定され、再実行が無言でブロックされるため。
  if (!acms_download_file($download, $zipFile)) {
    @unlink($zipFile);
    return 'a-blog cms download Error ! : ' . $download;
  }

  if (!acms_extract_zip($zipFile, $installPath)) {
    unlink($zipFile);
    return 'a-blog cms unZip Error ! : ' . $zipFile;
  }

  if (!acms_move_package_files($ablogcmsDir, $installPath)) {
    unlink($zipFile);
    return 'a-blog cms move Error ! :' . $ablogcmsDir;
  }

  acms_setup_htaccess($installPath, $cpiCheck, $cpiHtaccessPhp);

  rename($installPath . "/editorconfig.txt", $installPath . '/.editorconfig');
  rename($installPath . "/env.txt", $installPath . '/.env');
  rename($installPath . "/gitignore.txt", $installPath . '/.gitignore');

  // root 以外に同梱される htaccess.txt を .htaccess へ一括リネームする。
  // archives/media/storage/private/cache/themes/archives_rev のようにディレクトリを
  // ハードコード列挙すると cron や extension（3.2 系で追加された同梱物）が漏れるため、
  // 実際に展開されたファイルを走査する方式にして、将来の増減にも自動追従させる。
  // $zipAfterDirName は解凍後の一時ディレクトリ（後で削除される）なので対象外にする。
  rename_bundled_htaccess($installPath, array($zipAfterDirName));

  acms_write_db_default($installPath, $db);

  unlink($zipFile);
  unlink($c['phpName']);

  // index.html があった時にリネームしておく
  if (is_file("./index.html")) {
    rename("./index.html", "_index.html");
  }

  // プログラム以外のディレクトリを削除
  dir_shori("delete", $zipAfterDirName);

  $mampMsg = acms_fix_mamp_httpdconf($c['mampHttpdConf'], $c['mampHttpdConf'] . '.backup');

  if (isset($c['themeZipFile'])) {
    $error = acms_install_theme($installPath, $c['themeDownloadUrl'], $c['themeZipFile']);
    if ($error !== null) {
      return $error;
    }
  }

  if (isset($c['pluginsZipFile'])) {
    $error = acms_install_plugins($installPath, $c['pluginsDownloadUrl'], $c['pluginsZipFile']);
    if ($error !== null) {
      return $error;
    }
  }

  return acms_render_setup_done($c['phpName'], $mampMsg);
}

/**
 * URL からファイルをダウンロードして保存する。
 *
 * @return bool 成功したら true
 */
function acms_download_file($url, $dest)
{
  $fp = @fopen($url, "rb");
  if ($fp === false) {
    return false;
  }
  $output = @fopen($dest, "wb");
  if ($output === false) {
    fclose($fp);
    return false;
  }
  while (!feof($fp)) {
    $buffer = fread($fp, 4096);
    if ($buffer !== false) {
      fwrite($output, $buffer);
    }
  }
  fclose($fp);
  fclose($output);
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
 * 展開した a-blog cms の中身を設置ディレクトリ直下へ移動する。
 *
 * @return bool ディレクトリを開けなければ false
 */
function acms_move_package_files($ablogcmsDir, $installPath)
{
  $handle = @opendir($ablogcmsDir);
  if ($handle === false) {
    return false;
  }
  while (($entry = readdir($handle)) !== false) {
    if ($entry != "." && $entry != "..") {
      rename($ablogcmsDir . $entry, $installPath . "/" . $entry);
    }
  }
  closedir($handle);
  return true;
}

/**
 * .htaccess を設定する。既存の .htaccess があれば同梱の htaccess.txt を追記してマージし、
 * 無ければリネームする。CPI 環境では PHP のバージョン指定を先頭に付ける。
 *
 * @param string $installPath
 * @param string $cpiCheck
 * @param string $cpiHtaccessPhp 例 "81"
 */
function acms_setup_htaccess($installPath, $cpiCheck, $cpiHtaccessPhp)
{
  $htaccessFile = $installPath . "/.htaccess";

  if (is_file($htaccessFile)) {
    $merged = file_get_contents($htaccessFile) . "\n\n" . file_get_contents($installPath . "/htaccess.txt");
    file_put_contents($htaccessFile, $merged);
    return;
  }

  rename($installPath . "/htaccess.txt", $htaccessFile);

  if ($cpiCheck == "secure") {
    $cpiHtaccess = sprintf("<Files ~ \"\.ini\">
deny from all
</Files>
Options +SymLinksIfOwnerMatch
AddHandler x-httpd-php%s .php\n\n", $cpiHtaccessPhp);
    file_put_contents($htaccessFile, $cpiHtaccess . file_get_contents($htaccessFile));
  }
}

/**
 * インストーラー（setup/）が使う DB 初期値ファイルを書き出す。
 *
 * @param string $installPath
 * @param array  $db acms_resolve_db_defaults() の戻り値
 */
function acms_write_db_default($installPath, array $db)
{
  $data = sprintf("<?php
\$dbDefaultHost     = '%s';
\$dbDefaultName     = '%s';
\$dbDefaultCreate   = '%s'; // '' or 'checked'
\$dbDefaultUser     = '%s';
\$dbDefaultPass     = '%s';
\$dbDefaultPrefix   = 'acms_';", $db['dbHost'], $db['dbName'], $db['dbCreate'], $db['dbUser'], $db['dbPass']);
  file_put_contents($installPath . "/setup/lib/db_default.php", $data);

  if (is_file($installPath . "/db_default.php")) {
    rename($installPath . "/db_default.php", $installPath . "/setup/lib/db_default.php");
  }
}

/**
 * MAMP の httpd.conf で無効化されている rewrite_module を有効化する（バックアップ付き）。
 *
 * @return string 画面に出すメッセージ（修正しなかった場合は空文字）
 */
function acms_fix_mamp_httpdconf($httpdConf, $backup)
{
  if (!is_file($httpdConf)) {
    return "";
  }

  $lines = file($httpdConf);
  $updated = false;
  foreach ($lines as $key => $line) {
    if (strpos($line, 'rewrite_module') !== false) {
      if (preg_match('/^\s*#/', $line)) {
        $lines[$key] = preg_replace('/^\s*#/', '', $line);
        $updated = true;
      }
      break;
    }
  }

  if (!$updated) {
    return "";
  }
  copy($httpdConf, $backup);
  file_put_contents($httpdConf, implode('', $lines));
  return "<p>httpd.conf を修正し、バックアップとして httpd.conf.backup を生成しました。<br><strong>MAMPを再起動してください。</strong></p>";
}

/**
 * 特製テーマの zip をダウンロードして配置する。
 *
 * @return string|null 失敗時はエラーメッセージ
 */
function acms_install_theme($installPath, $downloadUrl, $themeZipFile)
{
  $themeNameVersion = explode(".", $themeZipFile);
  $themeNameArray = explode("_", $themeNameVersion[0]);
  $themeName = $themeNameArray[0];
  $themeZipUrl = $downloadUrl . $themeZipFile;
  $themePath = $installPath . "/" . $themeName;

  if (!acms_download_file($themeZipUrl, $themeZipFile)) {
    return 'theme ' . $themeName . ' download Error ! : ' . $themeZipUrl;
  }

  // テーマ zip には配布形式が2種類ある。
  //  - 従来形式（例: a-blogcms.jp 配布の square@ec.zip）: テーマ名ディレクトリが
  //    最外層で、その中に bin/ themes/ 等が入っている（<name>/bin/, <name>/themes/）。
  //  - bin/themes 直下形式（例: acms-develop, acms-utsuwa の GitHub Releases 配布物）:
  //    bin/ themes/ が最外層で、その中にテーマ名ディレクトリが入っている
  //    （bin/<name>/, themes/<name>/）。a-blog cms 本体の setup/bin・themes 構造を
  //    そのまま zip 化したもの。
  // 展開前にルート直下の "bin/" エントリの有無で形式を判定する。
  $zip = new ZipArchive();
  if ($zip->open($themeZipFile) !== true) {
    return 'theme unZip Error ! : ' . $themeZipUrl;
  }
  $hasTopLevelBin = false;
  for ($i = 0; $i < $zip->numFiles; $i++) {
    if (preg_match('#^bin/#', $zip->getNameIndex($i))) {
      $hasTopLevelBin = true;
      break;
    }
  }
  $zip->extractTo($installPath);
  $zip->close();

  if ($hasTopLevelBin) {
    // bin/themes 直下形式: 展開直後に installPath/bin/<name>/ ができるので、
    // これを setup/bin/ へ移動する。themes/<name>/ は展開時点で既に
    // installPath/themes/<name>/ に配置済みのため追加の move は不要。
    // plugins/ が同梱される場合はルート直下（installPath/plugins/）に来る。
    dir_shori("move", $installPath . "/bin/", $installPath . "/setup/bin/");
    $checkPlugins = $installPath . "/plugins";
  } else {
    dir_shori("move", $themePath . "/bin/", $installPath . "/setup/bin/");
    dir_shori("move", $themePath . "/themes/", $installPath . "/themes/");

    // テーマ選択の仕組みは a-blog cms のバージョンで異なる。
    //  - 旧インストーラ (~3.2.26 以前): setup/tpl/install.html を持ち、テーマ同梱の install.html で
    //    インストーラ画面ごと差し替え、サムネイルは setup/img/ に置いていた。
    //  - 新インストーラ (3.2.27 以降の Twig ベース): 選択肢はテーマの bin 同梱 theme.yaml の
    //    FS 走査で決まり、サムネイルも bin/<name>/ 内から解決する。bin の move だけで足りる。
    // setup/tpl の有無で新旧を判定し、旧系のときだけ install.html / サムネイルを配置する
    // （新系で存在しない setup/tpl へ rename すると警告になるため）。
    if (is_dir($installPath . "/setup/tpl") && is_file($themePath . "/tpl/install.html")) {
      rename($themePath . "/tpl/install.html", $installPath . "/setup/tpl/install.html");
      if (is_file($themePath . "/img/" . $themeName . ".jpg")) {
        rename($themePath . "/img/" . $themeName . ".jpg", $installPath . "/setup/img/" . $themeName . ".jpg");
      }
    }
    $checkPlugins = $themePath . "/plugins";
  }

  // 拡張アプリ(plugins/)の同梱はテーマ zip の配布形式に関わらず起こりうるため、
  // 形式ごとに解決した $checkPlugins のパスを使って共通処理する。
  if (is_dir($checkPlugins)) {
    acms_move_bundled_plugins($checkPlugins, $installPath);
    acms_enable_hook($installPath . "/config.server.php");
  }

  if (!$hasTopLevelBin) {
    dir_shori("delete", $themeName);
  }
  unlink($themeZipFile);
  return null;
}

/**
 * 同梱の拡張アプリを extension/plugins/ へ移動する。
 */
function acms_move_bundled_plugins($pluginsDir, $installPath)
{
  $handle = opendir($pluginsDir);
  while (($file = readdir($handle)) !== false) {
    if ($file != "." && $file != ".." && is_dir($pluginsDir . "/" . $file)) {
      dir_shori("move", $pluginsDir . "/" . $file, $installPath . "/extension/plugins/" . $file);
    }
  }
  closedir($handle);
}

/**
 * config.server.php の HOOK_ENABLE を 1 にする（拡張アプリをインストール時に自動で有効化）。
 */
function acms_enable_hook($configFile)
{
  $rows = explode("\n", file_get_contents($configFile));
  $out = '';
  foreach ($rows as $row) {
    $out .= preg_match('/HOOK_ENABLE/', $row) ? "define('HOOK_ENABLE', 1);\n" : $row . "\n";
  }
  file_put_contents($configFile, $out);
}

/**
 * 拡張アプリ（"a.zip|b.zip" 形式で複数指定可）をダウンロードして配置する。
 *
 * @return string|null 失敗時はエラーメッセージ
 */
function acms_install_plugins($installPath, $downloadUrl, $pluginsZipFile)
{
  foreach (explode("|", $pluginsZipFile) as $pluginsZip) {
    $pluginsName = explode("_", explode(".", $pluginsZip)[0])[0];
    $pluginsZipUrl = $downloadUrl . $pluginsZip;

    if (!acms_download_file($pluginsZipUrl, $pluginsZip)) {
      return 'plugin download Error ! : ' . $pluginsZipUrl;
    }
    if (!acms_extract_zip($pluginsZip, $installPath)) {
      return 'theme unZip Error ! : ' . $pluginsZip;
    }

    dir_shori("move", $installPath . "/" . $pluginsName, $installPath . "/extension/plugins/" . $pluginsName);
    unlink($pluginsZip);
  }
  return null;
}

// --------------------------
// ディレクトリを操作 function ( move / copy / delete )
// --------------------------
function dir_shori($shori, $nowDir, $newDir = "")
{
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
            if (is_dir($nowDir . "/" . $file)) {
              dir_shori("copy", $nowDir . "/" . $file, $newDir . "/" . $file);
            } else {
              copy($nowDir . "/" . $file, $newDir . "/" . $file);
            }
          } elseif ($shori == "move") {
            if (is_dir($newDir . "/" . $file)) {
              dir_shori("delete", $newDir . "/" . $file, "");
            }
            rename($nowDir . "/" . $file, $newDir . "/" . $file);
          } elseif ($shori == "delete") {
            if (filetype($nowDir . "/" . $file) == "dir") {
              dir_shori("delete", $nowDir . "/" . $file, "");
            } else {
              unlink($nowDir . "/" . $file);
            }
          }
        }
      }
      closedir($handle);
    }
  }
  if ($shori == "move" || $shori == "delete") {
    rmdir($nowDir);
  }
  return true;
}

// --------------------------
// htaccess.txt を .htaccess へ一括リネーム
// --------------------------
/**
 * $baseDir 配下（$baseDir 自身の直下ファイルは除く）を再帰的に走査し、
 * 同梱されている htaccess.txt をすべて .htaccess へリネームする。
 * root の htaccess.txt は別途マージ処理があるため、この関数は $baseDir の
 * サブディレクトリのみを対象にする（$baseDir 直下は呼び出し側で処理済みの前提）。
 *
 * @param string $baseDir     走査を開始するディレクトリ（通常は installPath）
 * @param string[] $excludeDirs $baseDir 直下で走査から除外するディレクトリ名（一時ディレクトリ等）
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
      // 既に .htaccess がある場合、htaccess.txt は不要な残骸なので削除する
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
 * URL の最終的な HTTP ステータスコードを取得する。
 * get_headers() はリダイレクトを辿った場合、各ホップのレスポンスヘッダーを
 * すべて配列にフラットに含めて返す（例: GitHub Releases の
 * releases/latest/download/... は 302 を 2 回経由する）ため、先頭要素だけを
 * 見るとリダイレクト元の 302 等を誤って参照してしまう。ここでは配列中に
 * 最後に出現したステータス行を最終ステータスとして扱う。
 *
 * @param string $url
 * @return string|null 取得できない場合は null
 */
function fetch_final_http_status($url)
{
  $headers = @get_headers($url);
  if ($headers === false) {
    return null;
  }
  $status = null;
  foreach ($headers as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
      $status = $m[1];
    }
  }
  return $status;
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
 * @param string $phpVersionForCheck PHP バージョン適合チェックに使う値
 *                                   （CPI 環境では $cpi_php_version 側の値を渡す）
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
