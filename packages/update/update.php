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

$ymdhis = date("YmdHis");

$input_pass = filter_input(INPUT_POST, "dbpass");

$installPath = realpath('.');

$phpName = basename($_SERVER['PHP_SELF']);

$error_msg = array();

$wantAcmsVersion = isset($ablogcmsVersion) ? $ablogcmsVersion : '';

require_once($installPath.'/config.server.php');

$domain = DOMAIN;
$database_name = DB_NAME;
$acount_name = DB_USER;
$acount_password = DB_PASS;
$database_host = DB_HOST;
$database_prefix = DB_PREFIX;

if (defined('DB_PORT') && DB_PORT !== '' && DB_PORT !== null) {
  $database_host = $database_host.";port=".DB_PORT;
} else {
  $port_check = explode(":", $database_host);
  if (count($port_check) == 2) {
    $database_host = $port_check[0].";port=".$port_check[1];
  }
}

$phpversion = phpversion();

// --------------------------
// バージョン・ダウンロードパッケージの取得（update.json 由来）
// --------------------------

// 対応 PHP バージョンは a-blog cms のバージョン系列ごとに変わるため、手書きの
// version_compare ラダーでは 3.3 系以降など将来のリリースに追従できない。
// update.json (https://www.a-blogcms.jp/api/update.json) の packages[] から、
// 実行中 PHP に対応するアップデート用パッケージを都度解決する。
$acmsPackage = fetch_acms_package_info($wantAcmsVersion, false, $phpversion);

if ($acmsPackage === false) {

  $ablogcmsVersion = null;
  $error_msg[] = "最新版の a-blog cms のバージョンの取得に失敗しました。<br>
  手動で update.php の中の \$ablogcmsVersion = \"3.2.0\"; を書き換え指定のバージョンを設定ください。<br>
  また # が先頭についていると未設定という扱いになりますので # があれば削除ください。";

} else {

  $ablogcmsVersion = $acmsPackage['version'];
  $cmsVersionArray = explode(".", $ablogcmsVersion);

  if ($cmsVersionArray[0] != 3) {
    $error_msg[] = "この簡単アップデートは 3.x 用です。<br>Ver.".$ablogcmsVersion." のアップデートはサポートしておりません。";
  } elseif ($acmsPackage['download'] === null) {
    $error_msg[] = "現在の PHP のバージョンが ".$phpversion." です。<br>a-blog cms Ver.".$ablogcmsVersion." へのアップデートする事ができません。<br>対応する PHP バージョンをご確認ください。";
  }

}

// 現在のバージョンをチェック

$sql = "SELECT sequence_system_version FROM ".$database_prefix."sequence";
$dbh = new PDO('mysql:host='.$database_host.';dbname='.$database_name.'', $acount_name, $acount_password);
$stmt = $dbh->query($sql);
foreach ($stmt as $row) {
  $now_version = $row['sequence_system_version'];
}
$now_versionArray = explode(".", $now_version);

$sql = "SELECT config_value FROM ".$database_prefix."config WHERE config_key = 'theme'";
$stmt = $dbh->query($sql);
foreach ($stmt as $row) {
  $theme_array[] = $row['config_value'];
}

$theme_unique_array = array_unique($theme_array);

$parent_theme_array = [];
foreach ($theme_unique_array as $theme_name) {
  $out_theme = [];
  $check_theme = explode('@', $theme_name);
  foreach ($check_theme as $data){
    array_shift($check_theme);
    $out_theme[] = implode('@', $check_theme);
  }
  $parent_theme_array = array_merge($parent_theme_array, $out_theme);
}
$theme_unique_array = array_merge($theme_unique_array, $parent_theme_array);
$theme_unique_array = array_filter($theme_unique_array);
$theme_unique_array = array_unique($theme_unique_array);
$theme_unique_array = array_values($theme_unique_array);


$dbh = null;

$lockFile = realpath('.'). "/update.lock";

if (is_file($lockFile)) {
  echo "lockFile:".$lockFile;
  $error_msg[] = "二重に実行防止のためのファイル ".$lockFile." を発見し処理できません。";
}

?><!DOCTYPE html>
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
<?php

if (isset($ablogcmsVersion)) {

  echo "<p>現在の <strong>Ver.".$now_version."</strong> から <strong>Ver.".$ablogcmsVersion."</strong> に a-blog cms のバージョンをアップデートします。";

  echo "<p>アップデート実行後に、CMS の<strong>管理者権限のユーザーID</strong> と <strong>パスワード</strong>が必要になります。<br>この処理実行後にパスワード再設定機能は利用できませんので事前に準備ください。</p>";

  if ($now_versionArray[0] < 3) {
    echo "<p><strong>アップデートするとシステムのライセンスが開発版に切り替わります。<br><a href=\"https://mypage.a-blogcms.jp/\">MYPAGE</a> から 3.0 対応版の license.php をダウンロードください。</strong></p>";
  }
}

// 現在のテーマをチェック

echo "<h3>利用中のテーマ</h3><ul>";

foreach ($theme_unique_array as $theme_name) {
  echo "<li>".$theme_name."</li>";
}

?></ul>

<h3>利用していないテーマ</h3>
<ul>
<?php

$theme_count = 0;
if ($handle = opendir($installPath."/themes")) {
  while(false !== ($theme = readdir($handle))) {
    if ($theme != "." && $theme != "..") {
      if (is_file($theme)) {
        #
      } elseif (in_array($theme,$theme_unique_array)) {
        #
      } elseif ($theme == "system") {
        #
      } else {
          echo "<li>".$theme."</li>";
          $theme_count++;
      }
    }
  }
  closedir($handle);
}

echo "</ul>";

if ($theme_count > 0) {
  echo "<p style=\"color: gray;\">※ 利用していないテーマについては、アップデート時には themes から削除され、バックアップデータ側に保存されます。</p>";
}

// エラー表示

  if (count($error_msg)) {
    echo "<h3>エラー</h3>";
    foreach ($error_msg as $text) {
      echo "<p class='acms-admin-text-error'>".$text."</p>";
    }
    $exec_stop = true;
  } else {
    $exec_stop = false;
  }

  if ($input_pass != DB_PASS) {

    if ($exec_stop != true) {
?>
    <form action="" method="POST" class="acms-admin-form">
      <input type="password" name="dbpass" id="dbpass" class="acms-admin-form-width-mini" placeholder="MySQL password">
      <input type="submit" class="acms-admin-btn" value="アップデート実行">
    </form>
    <p>処理を実行してよろしければ、データベースのパスワードを入力してください。</p>

<?php

    }

    if (isset($input_pass) && $input_pass != DB_PASS) {
      echo "<p class='acms-admin-text-error'>パスワードが間違っています。</p>";
    }

    echo "</body></html>";
    exit;

  } else {

    // アップデート処理

    touch($lockFile);

    # ダウンロード元 URL（update.json 由来。acmsX.Y.Z_updateNx.zip のようなアップデート用
    # パッケージ名をハードコード組み立てせず、API が返す URL / root_dir をそのまま利用する。
    # update.json が提供するのは常に "_update2x" パッケージだが、cache/storage のように
    # 現在稼働中サイトに既存のディレクトリと衝突しうるものは dir_shori() 側の存在チェックで
    # 上書きを避ける設計にしているため、系列に応じた URL の出し分けはしない）
    $download = $acmsPackage['download'];

    # ダウンロード後のZipファイル名
    $zipFile = "./" . basename(parse_url($download, PHP_URL_PATH));

    # 解凍後の a-blog cms のディレクトリ
    $ablogcmsDir = $installPath . "/" . $acmsPackage['root_dir'];

    # 解凍後の全体フォルダ名（root_dir の先頭セグメント）＝処理完了後に丸ごと削除する一時ディレクトリ
    $rootDirParts = explode("/", $acmsPackage['root_dir']);
    $zipAfterDirName = $rootDirParts[0];

    // --------------------------
    // a-blog cms ファイルをダウンロード
    // --------------------------

    $fp = fopen($download, "r");
    if ($fp !== FALSE) {
        file_put_contents($zipFile, "");
        while(!feof($fp)) {
            $buffer = fread($fp, 4096);
            if ($buffer !== FALSE) {
                file_put_contents($zipFile, $buffer, FILE_APPEND);
            }
        }
        fclose($fp);
    } else {
        echo "<p class='acms-admin-text-error'>a-blog cms ダウンロードエラー</p>";
        echo "</body></html>";
        unlink($lockFile);
        exit;
    }

    // --------------------------
    // a-blog cms ファイルを解凍
    // --------------------------

    $zip = new ZipArchive();
    $res = $zip->open($zipFile);

    if($res === true){
        $zip->extractTo($installPath);
        $zip->close();

    } else {
        echo "<p class='acms-admin-text-error'>a-blog cms 解凍エラー : ".$zipFile."</p>";
        echo "</body></html>";
        unlink($lockFile);
        exit;
    }

    // --------------------------
    // バックアップ
    // --------------------------

    $backupDir = "backup_". $ymdhis;

    # バックアップディレクトリを作成
    mkdir($backupDir);

    # ファイルを移動
    if (is_file("./acms.js")) rename("./acms.js", $backupDir."/acms.js");
    if (is_file("./index.js")) rename("./index.js", $backupDir."/index.js");
    if (is_file("./500.html")) rename("./500.html", $backupDir."/500.html");

    rename("./index.php", $backupDir."/index.php");

    if ($now_versionArray[0] < 3) {
      rename ("./license.php", $backupDir."/license.php");
    }
    # ディレクトリを移動

    dir_shori("move", "./js", $backupDir."/js");
    dir_shori("move", "./lang", $backupDir."/lang");
    dir_shori("move", "./php", $backupDir."/php");
    dir_shori("move", "./private", $backupDir."/private");
    dir_shori("move", "./themes", $backupDir."/themes");

    if (is_dir("./extension")) dir_shori("move", "./extension", $backupDir."/extension");

    if (is_file("./cache/.htaccess")) {
            mkdir($backupDir."/cache");
            rename("./cache/.htaccess", $backupDir."/cache/.htaccess");
    }
    if (is_dir("./cache")) dir_shori ("delete", "cache");

    // --------------------------
    // update版 ファイル＆ディレクトリを移動
    // --------------------------

    dir_shori("move", $ablogcmsDir, $installPath);

    # 3.0 以前場合にはライセンスファイルが開発ライセンスになります。
    if ($now_versionArray[0] < 3) {
      rename ($installPath."/".$zipAfterDirName."/omake/license.php", "./license.php");
    }
    // --------------------------
    // カスタマイズ部分を戻す
    // --------------------------

    # 利用しているテーマを戻す
    foreach ($theme_unique_array as $theme_name) {
      dir_shori ("copy", $backupDir."/themes/".$theme_name, "./themes/".$theme_name);
    }

    # /php/ACMS/User を戻す
    rename ("./php/ACMS/User","./php/ACMS/User_".$ablogcmsVersion);
    dir_shori ("copy", $backupDir."/php/ACMS/User", "./php/ACMS/User");

    # php/AAPP を戻す
    rename ("./php/AAPP", "./php/AAPP_".$ablogcmsVersion);
    dir_shori ("copy", $backupDir."/php/AAPP", "./php/AAPP");

    # /private/config.system.yaml を戻す
    // アップデート用パッケージに config.system.yaml が同梱されない場合もあるため存在チェックを追加。
    if (is_file("./private/config.system.yaml")) {
      rename ("./private/config.system.yaml", "./private/config.system_".$ablogcmsVersion.".yaml");
    }
    copy ($backupDir."/private/config.system.yaml", "./private/config.system.yaml");

    # /extension を戻す
    if (is_dir($backupDir."/extension")) {
      // アップデート用パッケージに extension が同梱されない場合もあるため存在チェックを追加。
      if (is_dir("./extension")) {
        rename ("./extension","./extension_".$ablogcmsVersion);
      }
      dir_shori ("copy", $backupDir."/extension", "./extension");
    }

    // --------------------------
    // .htaccess の設定
    // --------------------------

    // root の htaccess.txt はマージ処理を行わずバージョン付きファイル名へ退避する
    // （従来の挙動を維持。新パッケージに同梱されない場合もあるため存在チェックを追加）。
    if (is_file("./htaccess.txt")) {
      rename("./htaccess.txt", './htaccess_'.$ablogcmsVersion.'.txt');
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
        if (is_file($backupDir."/cache/.htaccess")) {
          rename($backupDir."/cache/.htaccess", "./cache/.htaccess");
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

    // --------------------------
    // php.ini があった時の処理
    // --------------------------

    if ( is_file( "./php.ini" )) {
        copy("./php.ini", "./setup/php.ini");
    }

    // --------------------------
    // ファイルの削除
    // --------------------------

    unlink($zipFile);
    unlink($phpName);

    # プログラム以外のディレクトリを削除
    if ( is_file( "./index.php" )) {
      dir_shori("delete", $zipAfterDirName);
    } else {
      echo "<p class='acms-admin-text-error'>正常にアップデートができませんでした。</p>";
      echo "</body></html>";
      unlink($lockFile);
      exit;
    }
?>

<p><strong>アップデート処理の実行を完了しました。</strong></p>

<h3>残作業</h3>
<ol>
  <li>メンテナンスツールでデータベースのアップデート</li>
  <?php
    if ($now_versionArray[0] < 3) {
      echo "<li>license.php ファイルのアップデート (<a href=\"https://mypage.a-blogcms.jp/\">MYPAGE</a>)</li>";
    }
  ?>
  <li>setup ディレクトリーの削除、またはリネーム</li>
</ol>

<a href="./setup/index.php" class="acms-admin-btn acms-admin-btn-large">メンテナンスツールへ</a>

</body></html>
<?php

    unlink($lockFile);
  }

exit;

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
 *
 * @return array{version: string, download: string|null, root_dir: string|null}|false
 *   失敗時は false。成功時は 'download'/'root_dir' に対応パッケージが無い場合 null が入る。
 */
function fetch_acms_package_info($wantVersion, $forInstall, $phpVersionForCheck)
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

  $json = @file_get_contents('https://www.a-blogcms.jp/api/update.json', false, stream_context_create($options));
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

  foreach ($target['packages'] as $pkg) {
    if (!isset($pkg['php_min_version'], $pkg['php_max_version'], $pkg['download'], $pkg['root_dir'])) {
      continue;
    }

    // "8.4.x" のような表記を上限比較できる値に正規化する
    $max = preg_replace('/\.x$/', '.999', $pkg['php_max_version']);

    if (
      version_compare($phpVersionForCheck, $pkg['php_min_version'], '>=') &&
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
