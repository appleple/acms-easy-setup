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

$error_msg = array();

$versionArray = explode(".", phpversion());
$version = $versionArray[0] . "." . $versionArray[1];

$server = gethostbyaddr($_SERVER['SERVER_ADDR']);
$cpi_check_array = explode( ".", $server );
$cpi_check = "";

if (is_array($cpi_check_array) && count($cpi_check_array) > 1) {
  $cpi_check = $cpi_check_array[1];
}
if (strpos($_SERVER['HTTP_HOST'],'smartrelease') !== false) {
	$cpi_check = "secure";
}

if ($cpi_check == "secure") {
  if ($cpi_php_version) {
    $moto_version = $version;
    $version = $cpi_php_version;
  }
  $cpi_htaccess_php = str_replace('.','', $version);
}

$phpName = basename($_SERVER['PHP_SELF']);

// --------------------------
// 動作チェック
// --------------------------

if (is_file("./license.php")) {
  $error_msg[] = "インストール先に license.php が見つかりました。<br>インストールを中止します。";
}

// --------------------------
// バージョン・ダウンロードパッケージの取得（update.json 由来）
// --------------------------

// 対応 PHP バージョンは a-blog cms のバージョン系列ごとに変わるため、手書きの
// version_compare ラダーでは 3.3 系以降など将来のリリースに追従できない。
// update.json (https://www.a-blogcms.jp/api/update.json) の packages[] から、
// 実行中 PHP（CPI 環境では $cpi_php_version 側）に対応するパッケージを都度解決する。
$acmsPackage = fetch_acms_package_info($ablogcmsVersion, true, $version);

if ($acmsPackage === false) {

    $error_msg[] = "a-blog cms のバージョン情報の取得に失敗しました（update.json）。<br>手動で \$ablogcmsVersion にバージョンを指定するか、しばらく時間を置いて再度お試しください。";
    $download = "";
    $zipFile = "";

} else {

    $ablogcmsVersion = $acmsPackage['version'];
    $zipFile = sprintf("./acms%s.zip", $ablogcmsVersion);

    if ($acmsPackage['download'] === null) {
        if ($cpi_check === 'secure') {
            $error_msg[] = sprintf(
                '%s の $cpi_php_version が a-blog cms Ver.%s に対応していません。設定を見直してください。',
                $phpName,
                $ablogcmsVersion
            );
        } else {
            $error_msg[] = sprintf(
                '現在の PHP バージョン（%s）は a-blog cms Ver.%s に対応していません。PHP のバージョンをご確認ください。',
                phpversion(),
                $ablogcmsVersion
            );
        }
        $download = "";
    } else {
        $download = $acmsPackage['download'];

        $http_header = @get_headers($download);
        $httt_hedaer0_code = ($http_header !== false && isset($http_header[0])) ? explode(" ", $http_header[0]) : array();
        if (!isset($httt_hedaer0_code[1]) || $httt_hedaer0_code[1] != "200") {
            $error_msg[] = "a-blog cms のダウンロード先の確認に失敗しました。バージョン「".$ablogcmsVersion."」をご確認ください。";
        }
    }

}

$installPath = realpath('.');
$http_host = explode(":", $_SERVER['HTTP_HOST']);

if ($zipFile !== "" && is_file($installPath."/".$zipFile)) {
  $_POST['action'] = "";
}

$mamp_rewrite_module_off = "";
$mamp_check = get_cfg_var('cfg_file_path');
if (strpos($mamp_check, 'MAMP') !== false) {
  $httpdconf = '/Applications/MAMP/conf/apache/httpd.conf';
  $fileContents = file($httpdconf);
  foreach ($fileContents as $key => $line) {
    if (strpos($line, 'rewrite_module') !== false) {
      if (preg_match('/^\s*#/', $line)) {
        $mamp_rewrite_module_off = "<h2>MAMP httpd.confチェック</h2><p>rewrite_module の行がコメントアウトされており、このままでは a-blog cms が動作しません。<br>httpd.conf を修正し、バックアップとして httpd.conf.backup を生成します。";
      }
    }
  }
}

?>
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
    <h1>a-blog cms Ver. <?php echo $ablogcmsVersion; ?> 簡単セットアップ</h1>
<?php

// --------------------------
// 現在の a-blog cms のバージョンをチェック
// --------------------------

$input_action = filter_input(INPUT_POST, "action");

if ($input_action == "セットアップ開始") {

// --------------------------

# 解凍後の a-blog cms のディレクトリ（update.json の root_dir をそのまま利用する。
# "acms{version}/ablogcms" のハードコード組み立てをやめ、パッケージ構成の変化に追従する）
$ablogcmsDir = $installPath . "/" . $acmsPackage['root_dir'] . "/";

# root_dir の先頭セグメント（例 "acms3.2.26"）＝解凍後にできる一時ディレクトリ名。
# 処理完了後に dir_shori("delete", ...) で丸ごと削除する対象。
$rootDirParts = explode("/", $acmsPackage['root_dir']);
$zipAfterDirName = $rootDirParts[0];

$ablogcmsVersionNum = str_replace(".", "", $ablogcmsVersion);

$mdHi = date("mdHi");

// --------------------------
// Mac & Windows & DDEV ローカルDB設定
// --------------------------

if (getenv('IS_DDEV_PROJECT') == 'true') {

  $dbHost     = 'db';
  $dbName     = 'db';
  $dbCreate   = '';
  $dbUser     = 'db';
  $dbPass     = 'db';

} elseif ($http_host[0] == 'localhost') {

  $dbHost     = '127.0.0.1';
  $dbName     = 'DBacms_' . $ablogcmsVersionNum . "_" . $mdHi;
  $dbCreate   = 'checked';
  $dbUser     = 'root';
  $dbPass     = '';

  $mamp_check = get_cfg_var('cfg_file_path');
  if (strpos($mamp_check, 'MAMP') !== false) {
    $dbPass     = 'root';
  }
}
// --------------------------
// a-blog cms ファイルをダウンロード
// --------------------------

$fp = fopen($download, "rb");
if ($fp !== FALSE) {
  $output = fopen($zipFile, "wb");
  if ($output === FALSE) {
    echo 'a-blog cms file open Error ! : ' . $zipFile;
    fclose($fp);
    exit;
  }

  while (!feof($fp)) {
    $buffer = fread($fp, 4096);
    if ($buffer !== FALSE) {
      fwrite($output, $buffer);
    }
  }

  fclose($fp);
  fclose($output);
} else {
  echo 'a-blog cms download Error ! : ' . $download;
  exit;
}

// --------------------------
// a-blog cms ファイルを解凍
// --------------------------

$zip = new ZipArchive();
$res = $zip->open($zipFile);

if ($res === true) {
  $zip->extractTo($installPath);
  $zip->close();
} else {
  echo 'a-blog cms unZip Error ! : ' . $zipFile;
  exit;
}

// --------------------------
// a-blog cms ディレクトリを移動
// --------------------------

if ($handle = opendir($ablogcmsDir)) {
  while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != "..") {
      rename($ablogcmsDir . $entry, $installPath . "/" . $entry);
    }
  }
  closedir($handle);
} else {
  echo 'a-blog cms move Error ! :' . $ablogcmsDir;
  exit;
}

// --------------------------
// .htaccess の設定
// --------------------------

$moto_htaccessFile = ".htaccess";

if (is_file($moto_htaccessFile)) {

  $htaccessData = file_get_contents($moto_htaccessFile);
  $cms_htaccessData = file_get_contents("htaccess.txt");

  $file = fopen("./.htaccess", "w+");
  fwrite($file, $htaccessData);
  fwrite($file, "\n\n" . $cms_htaccessData);
  fclose($file);

} else {

  rename($installPath . "/htaccess.txt", $installPath . '/.htaccess');

  if ($cpi_check == "secure") {

    $htaccess = file_get_contents($installPath."/.htaccess");
    $cpi_htaccess = sprintf("<Files ~ \"\.ini\">
deny from all
</Files>
Options +SymLinksIfOwnerMatch
AddHandler x-httpd-php%s .php\n\n",$cpi_htaccess_php);

  $fp = fopen($installPath."/.htaccess",'w');
  fwrite($fp,$cpi_htaccess.$htaccess);
  fclose($fp);
  }
}

rename($installPath . "/editorconfig.txt", $installPath . '/.editorconfig');
rename($installPath . "/env.txt", $installPath . '/.env');
rename($installPath . "/gitignore.txt", $installPath . '/.gitignore');

// root 以外に同梱される htaccess.txt を .htaccess へ一括リネームする。
// archives/media/storage/private/cache/themes/archives_rev のようにディレクトリを
// ハードコード列挙すると cron や extension（3.2 系で追加された同梱物）が漏れるため、
// 実際に展開されたファイルを走査する方式に変更し、将来の増減にも自動追従させる。
// $zipAfterDirName は解凍後の一時ディレクトリ（後で削除される）なので対象外にする。
rename_bundled_htaccess($installPath, array($zipAfterDirName));

// --------------------------
// DB 初期設定
// --------------------------

$data = sprintf("<?php
\$dbDefaultHost     = '%s';
\$dbDefaultName     = '%s';
\$dbDefaultCreate   = '%s'; // '' or 'checked'
\$dbDefaultUser     = '%s';
\$dbDefaultPass     = '%s';
\$dbDefaultPrefix   = 'acms_';", $dbHost, $dbName, $dbCreate, $dbUser, $dbPass);
$db_default = $installPath . "/setup/lib/db_default.php";
file_put_contents($db_default, $data);

if (is_file($installPath."/db_default.php")) {
  rename("./db_default.php", "./setup/lib/db_default.php");
}

// --------------------------
// ファイルの削除
// --------------------------

unlink($zipFile);
unlink($phpName);

# index.html があった時にリネームしておく
if (is_file("./index.html")) {
  rename("./index.html", "_index.html");
}

# プログラム以外のディレクトリを削除
dir_shori("delete", $zipAfterDirName);


// --------------------------
// MAMP暫定対応
// --------------------------
$mamp_httpdconf = '/Applications/MAMP/conf/apache/httpd.conf';
$mamp_msg = "";

if (is_file($mamp_httpdconf)) {

  $update_httpdconf = false;
  $mamp_httpdconf_backup = '/Applications/MAMP/conf/apache/httpd.conf.backup';

  $fileContents = file($mamp_httpdconf);

  foreach ($fileContents as $key => $line) {
    if (strpos($line, 'rewrite_module') !== false) {
        if (preg_match('/^\s*#/', $line)) {
            $fileContents[$key] = preg_replace('/^\s*#/', '', $line);
            $update_httpdconf = true;
        }
        break;
    }
  }

  if ($update_httpdconf == true) {
    copy($mamp_httpdconf, $mamp_httpdconf_backup);
    file_put_contents($mamp_httpdconf, implode('', $fileContents));
    $mamp_msg = "<p>httpd.conf を修正し、バックアップとして httpd.conf.backup を生成しました。<br><strong>MAMPを再起動してください。</strong></p>";
  }
}

// --------------------------
// 特製テーマファイルをダウンロード
// --------------------------

if (isset($theme_zip_file)) {

  $theme_name_version = explode(".",$theme_zip_file);
  $theme_name_array = explode("_",$theme_name_version[0]);
  $theme_name = $theme_name_array[0];
  $theme_zip_url = $theme_download_url . $theme_zip_file;
  $theme_path = $installPath."/".$theme_name;

  $fp = fopen($theme_zip_url, "r");
  if ($fp !== FALSE) {
    file_put_contents($theme_zip_file, "");
    while (!feof($fp)) {
      $buffer = fread($fp, 4096);
      if ($buffer !== FALSE) {
        file_put_contents($theme_zip_file, $buffer, FILE_APPEND);
      }
    }
    fclose($fp);
  } else {
    echo 'theme ' . $theme_name . ' download Error ! : ' . $theme_zip_url;
    exit;
  }

  $zip = new ZipArchive();
  $res = $zip->open($theme_zip_file);

  if ($res === true) {
    // テーマ zip には配布形式が2種類ある。
    //  - 従来形式（例: a-blogcms.jp 配布の square@ec.zip）: テーマ名ディレクトリが
    //    最外層で、その中に bin/ themes/ 等が入っている（<name>/bin/, <name>/themes/）。
    //  - bin/themes 直下形式（例: acms-develop, acms-utsuwa の GitHub Releases 配布物）:
    //    bin/ themes/ が最外層で、その中にテーマ名ディレクトリが入っている
    //    （bin/<name>/, themes/<name>/）。a-blog cms 本体の setup/bin・themes 構造を
    //    そのまま zip 化したもの。
    // 展開前にルート直下の "bin/" エントリの有無で形式を判定する。
    $themeZipHasTopLevelBin = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
      if (preg_match('#^bin/#', $zip->getNameIndex($i))) {
        $themeZipHasTopLevelBin = true;
        break;
      }
    }
    $zip->extractTo($installPath);
    $zip->close();
  } else {
    echo 'theme unZip Error ! : ' . $theme_zip_url;
    exit;
  }

  if ($themeZipHasTopLevelBin) {

    // bin/themes 直下形式: 展開直後に installPath/bin/<name>/ ができるので、
    // これを setup/bin/ へ移動する。themes/<name>/ は展開時点で既に
    // installPath/themes/<name>/ に配置済みのため追加の move は不要。
    // plugins/・tpl/・img/ の同梱も想定しない形式のため対象外。
    dir_shori("move", $installPath . "/bin/", $installPath . "/setup/bin/");
    unlink($theme_zip_file);

  } else {

    dir_shori("move", $theme_path . "/bin/" , $installPath . "/setup/bin/" );
    dir_shori("move", $theme_path . "/themes/" , $installPath . "/themes/" );

    // テーマ選択の仕組みは a-blog cms のバージョンで異なる。
    //  - 旧インストーラ (~3.2.26 以前): setup/tpl/install.html を持ち、テーマ同梱の install.html で
    //    インストーラ画面ごと差し替え、サムネイルは setup/img/ に置いていた。
    //  - 新インストーラ (3.2.27 以降の Twig ベース): 選択肢はテーマの bin 同梱 theme.yaml の
    //    FS 走査で決まり、サムネイルも bin/<name>/ 内から解決する。bin の move だけで足りる。
    // setup/tpl の有無で新旧を判定し、旧系のときだけ install.html / サムネイルを配置する
    // （新系で存在しない setup/tpl へ rename すると警告になるため）。
    if (is_dir($installPath . "/setup/tpl") && is_file($theme_path . "/tpl/install.html")) {
      rename( $theme_path . "/tpl/install.html", $installPath . "/setup/tpl/install.html");
      if (is_file($theme_path . "/img/" . $theme_name . ".jpg")) {
        rename( $theme_path . "/img/" . $theme_name . ".jpg", $installPath . "/setup/img/" . $theme_name . ".jpg");
      }
    }

    $check_plugins = $theme_path."/plugins";
    if (is_dir($check_plugins)) {
        if ($handle = opendir($check_plugins)) {
          while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
              if (is_dir($check_plugins."/".$file)) {
                dir_shori("move", $check_plugins."/".$file, $installPath."/extension/plugins/".$file);
              }
            }
          }
          closedir($handle);
        }

        // 拡張アプリをインストール時 自動で HOOK_ENABLE を 1 にする
        $configFile = $installPath."/config.server.php";
        $config = file_get_contents($configFile);
        $rows = explode("\n", $config);
        $fp = fopen($configFile, "w");
        if( $fp !== false ) {
          foreach( $rows as $row ) {
            if ( preg_match( '/HOOK_ENABLE/', $row ) ) {
                $outdata = "define('HOOK_ENABLE', 1);\n";
            } else {
                $outdata = $row . "\n";
            }
            fwrite($fp, $outdata);
          }
        } else {
          echo "config.server.php fopen error";
        }
        fclose($fp);

    }

    dir_shori("delete", $theme_name);
    unlink($theme_zip_file);
  }
}

// --------------------------
// 拡張アプリをダウンロード
// --------------------------

if (isset($plugins_zip_file)) {

  $plugins_array = explode("|",$plugins_zip_file);

  foreach($plugins_array as $plugins_zip) {

    $plugins_name_version = explode(".",$plugins_zip);
    $plugins_name_array = explode("_",$plugins_name_version[0]);
    $plugins_name = $plugins_name_array[0];
    $plugins_zip_url = $plugins_download_url . $plugins_zip;

    $fp = fopen($plugins_zip_url, "r");
    if ($fp !== FALSE) {
      file_put_contents($plugins_zip, "");
      while (!feof($fp)) {
        $buffer = fread($fp, 4096);
        if ($buffer !== FALSE) {
          file_put_contents($plugins_zip, $buffer, FILE_APPEND);
        }
      }
      fclose($fp);
    } else {
      echo 'plugin download Error ! : ' . $plugins_zip_url;
      exit;
    }

    $zip = new ZipArchive();
    $res = $zip->open($plugins_zip);

    if ($res === true) {
      $zip->extractTo($installPath);
      $zip->close();
    } else {
      echo 'theme unZip Error ! : ' . $plugins_zip;
      exit;
    }

    dir_shori("move", $installPath ."/". $plugins_name, $installPath."/extension/plugins/" . $plugins_name);
    unlink($plugins_zip);
  }

}

// --------------------------
// インストーラーに飛ぶ
// --------------------------

?>

  <h2>セットアップ完了</h2>

  <p>a-blog cms のインストール準備が完了しました。</p>
  <p>この <?php echo $phpName; ?>ファイルについては削除済みです。</p>

  <?php echo $mamp_msg; ?>

  <form action="index.php" method="POST">
  <input type="submit" name="action" value="インストーラーへ移動">
  </form>

<?php

} else {

  ?>

  <p>a-blog cms のパッケージのダウンロードとファイルのリネーム作業を行います。</p>

  <h2>PHP バージョンチェック</h2>

  <ul><li>Ver. <?php
  if ($cpi_php_version && $cpi_check == "secure") {
    echo "<del>";
  }

  echo phpversion();

  if ($cpi_php_version && $cpi_check == "secure") {
    echo "</del> → ". $version . "(変更)";
  }
  ?></li></ul>

<?php

if (isset($theme_zip_file)) {

  $theme_name_version = explode(".",$theme_zip_file);
  $theme_name = explode("_",$theme_name_version[0]);
  echo "<h2>特製テーマをインストール</h2>";

  // GitHub Releases の releases/latest/download/... のようにリダイレクトを
  // 経由する URL でも正しく判定できるよう、最終的なステータスコードで確認する。
  $check = $theme_download_url.$theme_zip_file;
  if ( fetch_final_http_status($check) != "200" ) {
    $error_msg[] = "特製テーマ「".$theme_name[0]."」のダウンロード先の情報が間違っています。";
    echo "<ul><li><del>".$theme_name[0]."</del></li></ul>";
  } else {
    echo "<ul><li>".$theme_name[0]."</li></ul>";
  }
}

  if (isset($plugins_zip_file)) {

$plugins_array = explode("|",$plugins_zip_file);
echo "<h2>Plugins Install</h2>";
echo "<ul>";

foreach($plugins_array as $plugins_zip) {

  $plugins_name_version = explode(".",$plugins_zip);
  $plugins_name = explode("_",$plugins_name_version[0]);

  // テーマ同様、リダイレクトを経由する URL でも正しく判定できるよう、
  // 最終的なステータスコードで確認する。
  $check = $plugins_download_url.$plugins_zip;
  if ( fetch_final_http_status($check) != "200" ) {
    $error_msg[] = "拡張アプリ「".$plugins_name[0]."」のダウンロード先の情報が間違っています。";
    echo "<li><del>".$plugins_name[0]."</del></li>";
  } else {
    echo "<li>".$plugins_name[0]."</li>";
  }
}
echo "</ul>";
}

  if (empty($error_msg)){


    if ($mamp_rewrite_module_off) {
      echo $mamp_rewrite_module_off;
    }


    ?>

<form action="<?php echo $phpName; ?>" method="POST" onSubmit="return double()">
<input type="submit" name="action" value="セットアップ開始">
</form>

    <?php
  } else {
    echo "<h2>Error</h2>";
    foreach($error_msg as $msg) {
      echo sprintf("<p class='error'>%s</p>",$msg);
    }
  }



}

exit;
?>
</body>
</html>
<?php
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
