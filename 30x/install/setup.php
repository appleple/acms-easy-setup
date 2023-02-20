<?php

// ------------------------------
// a-blog cms 3.x 簡単セットアップ
//       last update 2021/08/21
// ------------------------------

# $ablogcmsVersion = '3.0.0';

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
# SV-Baisc   PHP 7.2 / 7.3 / 7.4 / 8.0

$cpi_php_version = "8.0";

// --------------------------
// UTSUWA GitHub版
// --------------------------

# $github_utsuwa = "https://github.com/appleple/acms-utsuwa/archive/refs/heads/main.zip";

// --------------------------
// 特製テーマ設定
// --------------------------

$theme_download_url = "http://www.a-blogcms.jp/_download/";

# $theme_zip_file = "square.zip";
# $theme_zip_file = "smartblock@blog.zip";

// --------------------------
// 拡張アプリ設定
// --------------------------

# $plugins_zip_file = "ShoppingCart_100.zip";
# $plugins_download_url = "http://www.a-blogcms.jp/_download/";

// --------------------------

$error_msg = array();

if (!isset($ablogcmsVersion)) {
  $check = download_version_check();
  if ($check) {
    $ablogcmsVersion = $check;
  } else {
    $error_msg[] = "web site version check error.";
  }
}

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
// バージョンのチェック
// --------------------------

if ($version < 7.2 || $version >= 8.2) {

  if ($cpi_check == "secure") {
    $error_msg[] = $phpName." の \$cpi_php_version で PHP のバージョンを指定ください。";
  } else {
    $error_msg[] = "PHP 7.2.x - 8.1.x をご利用ください。";
  }
} 

# ダウンロード元 URL
$download = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s.zip", $ablogcmsVersion, $ablogcmsVersion);
$zipFile = sprintf("./acms%s.zip", $ablogcmsVersion);

$http_header = get_headers($download);
$httt_hedaer0_code = explode(" ",$http_header[0]);
if ( $httt_hedaer0_code[1] != "200" ) {
  $error_msg[] = "a-blog cms のバーンジョン設定「".$ablogcmsVersion."」が間違っています。";
}

$installPath = realpath('.');
$http_host = explode(":", $_SERVER['HTTP_HOST']);

if (is_file($installPath."/".$zipFile) || is_file($installPath."/".$zipFile)) {
  $_POST['action'] = "";
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

# 解凍後の全体フォルダ名
$zipAfterDirName = sprintf("acms%s", $ablogcmsVersion);

$ablogcmsVersionNum = str_replace(".", "", $ablogcmsVersion);

# 解凍後の a-blog cms のフォルダ名
$cmsDirName = "ablogcms";

$ablogcmsDir = $installPath . "/" . $zipAfterDirName . "/" . $cmsDirName . "/";



$mdHi = date("mdHi");

// --------------------------
// Mac & Windows ローカルDB設定
// --------------------------

if ($http_host[0] == 'localhost') {

  $dbHost     = 'localhost';
  $dbName     = 'DBacms_' . $ablogcmsVersionNum . "_" . $mdHi;
  $dbCreate   = 'checked';
  $dbUser     = 'root';
  $dbPass     = '';

  $mamp_check = explode("/", $installPath);
  if ($mamp_check[2] == 'MAMP') {
    $dbPass     = 'root';
  }
}

// --------------------------
// a-blog cms ファイルをダウンロード
// --------------------------

$fp = fopen($download, "r");
if ($fp !== FALSE) {
  file_put_contents($zipFile, "");
  while (!feof($fp)) {
    $buffer = fread($fp, 4096);
    if ($buffer !== FALSE) {
      file_put_contents($zipFile, $buffer, FILE_APPEND);
    }
  }
  fclose($fp);
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

rename($installPath . "/archives/htaccess.txt", $installPath . '/archives/.htaccess');
rename($installPath . "/archives_rev/htaccess.txt", $installPath . '/archives_rev/.htaccess');
rename($installPath . "/media/htaccess.txt", $installPath . '/media/.htaccess');
rename($installPath . "/private/htaccess.txt", $installPath . '/private/.htaccess');
rename($installPath . "/cache/htaccess.txt", $installPath . '/cache/.htaccess');
rename($installPath . "/themes/htaccess.txt", $installPath . '/themes/.htaccess');

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
    $zip->extractTo($installPath);
    $zip->close();
  } else {
    echo 'theme unZip Error ! : ' . $theme_zip_url;
    exit;
  }

  dir_shori("move", $theme_path . "/bin/" . $theme_name, $installPath . "/setup/bin/" . $theme_name);
  dir_shori("move", $theme_path . "/themes/" , $installPath . "/themes/" );

  rename( $theme_path . "/tpl/install.html", $installPath . "/setup/tpl/install.html");
  rename( $theme_path . "/img/" . $theme_name . ".jpg", $installPath . "/setup/img/" . $theme_name . ".jpg");

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
// GitHub版 utsuwa インポート
// --------------------------

if (isset($github_utsuwa)) {

  $github_utsuwa_zip ="acms-utsuwa-main.zip";

  $fp = fopen($github_utsuwa, "r");
  if ($fp !== FALSE) {
    file_put_contents($github_utsuwa_zip, "");
    while (!feof($fp)) {
      $buffer = fread($fp, 4096);
      if ($buffer !== FALSE) {
        file_put_contents($github_utsuwa_zip, $buffer, FILE_APPEND);
      }
    }
    fclose($fp);
  } else {
    echo 'github utsuwa download Error ! : ' . $download;
    exit;
  }

  $zip = new ZipArchive();
  $res = $zip->open($github_utsuwa_zip);

  if ($res === true) {
    $zip->extractTo($installPath);
    $zip->close();
  } else {
    echo 'github utsuwa unZip Error ! : ' . $github_utsuwa_zip;
    exit;
  }

  dir_shori("delete", $installPath."/setup/bin/utsuwa");
  dir_shori("move", $installPath."/acms-utsuwa-main/_bin/utsuwa",$installPath."/setup/bin/utsuwa");
  dir_shori("delete", $installPath."/acms-utsuwa-main/_bin");
  dir_shori("delete", $installPath."/themes/utsuwa");
  dir_shori("move", $installPath."/acms-utsuwa-main",$installPath."/themes/utsuwa");

  unlink($github_utsuwa_zip);
} 

// --------------------------
// インストーラーに飛ぶ
// --------------------------

?>
  
  <h2>セットアップ完了</h2>

  <p>a-blog cms のインストール準備が完了しました。</p>
  <p>この <?php echo $phpName; ?>ファイルについては削除済みです。</p>

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

  $check = $theme_download_url.$theme_zip_file;
  $http_header = get_headers($check);
  $httt_hedaer0_code = explode(" ",$http_header[0]);
  if ( $httt_hedaer0_code[1] != "200" ) {
    $error_msg[] = "特製テーマ「".$theme_name[0]."」のダウンロード先の情報が間違っています。";
    echo "<ul><li><del>".$theme_name[0]."</del></li></ul>";
  } else {
    echo "<ul><li>".$theme_name[0]."</li></ul>";
  }
}

if (isset($github_utsuwa)) {

  $html = file_get_contents('https://github.com/appleple/acms-utsuwa');
  $pattern_ver = '@<span class="css-truncate css-truncate-target text-bold mr-2" style="max-width: none;">(.*?)</span>@';
  if( preg_match_all($pattern_ver, $html, $result) ){
      $utsuwa_version = $result[0][0];
  }

  $pattern_date = '@<relative-time datetime="(.*?)" class="no-wrap">(.*?)</relative-time>@';
  if( preg_match_all($pattern_date, $html, $result) ){
    $utsuwa_update = $result[2][0];
  }


  echo "<h2>GitHub版 UTSUWA インポート</h2>";
  echo "<ul><li>utsuwa ".$utsuwa_version." / ".$utsuwa_update."</li></ul>";
}


if (isset($plugins_zip_file)) {

$plugins_array = explode("|",$plugins_zip_file);
echo "<h2>Plugins Install</h2>";
echo "<ul>";

foreach($plugins_array as $plugins_zip) {

  $plugins_name_version = explode(".",$plugins_zip);
  $plugins_name = explode("_",$plugins_name_version[0]);

  $check = $plugins_download_url.$plugins_zip;
  $http_header = get_headers($check);
  $httt_hedaer0_code = explode(" ",$http_header[0]);

  if ( $httt_hedaer0_code[1] != "200" ) {
    $error_msg[] = "拡張アプリ「".$plugins_name[0]."」のダウンロード先の情報が間違っています。";
    echo "<li><del>".$plugins_name[0]."</del></li>";  
  } else {
    echo "<li>".$plugins_name[0]."</li>";  
  }     
}
echo "</ul>";
}

  if (empty($error_msg)){
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

/**
 * Version 3.0.x のチェック用
 * 正常にチェックできない場合には 空 でかえす。
 */
function download_version_check()
{
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $html = file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/3.0.(.*).html">(.*)<\/a><\/h1>/', $html, $matches);

  if (is_numeric($matches[2])) {
    return "3.0." . $matches[2];
  } else {
    return;
  }
}
