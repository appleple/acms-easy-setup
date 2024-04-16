<?php

ini_set('max_execution_time', 0);

// ----------------------
// a-blog cms 3.x 簡単アップデート 
// update 2024/01/22
// ----------------------

// アップデートバージョンを指定する場合は「$ablogcmsVersion」を指定ください。
// 指定しない場合、最新バージョンにアップデートされます。

// バージョンを指定する際には以下の行頭の # を削除してください。

# $ablogcmsVersion = "3.1.0";

// ------------------------------

// これ以下は修正する必要はありません。

$ymdhis = date("YmdHis");

$input_pass = filter_input(INPUT_POST, "dbpass");

$installPath = realpath('.');

$phpName = basename($_SERVER['PHP_SELF']);

$error_msg = array();

if (!isset($ablogcmsVersion)) {
    $check = download_version_check();
  if ($check) {
    $ablogcmsVersion = $check;
  } else {
    $error_msg[] = "最新版の a-blog cms のバージョンの取得に失敗しました。<br>
    手動で update.php の中の \$ablogcmsVersion = \"3.1.0\"; を書き換え指定のバージョンを設定ください。<br>
    また # が先頭についていると未設定という扱いになりますので # があれば削除ください。";
  }
}

require_once($installPath.'/config.server.php');

$domain = DOMAIN;
$database_name = DB_NAME;
$acount_name = DB_USER;
$acount_password = DB_PASS;
$database_host = DB_HOST;
$database_prefix = DB_PREFIX;

$port_check = explode( ":", $database_host );
if ( count( $port_check ) == 2 ) {
  $database_host = $database_host.";port=".$port_check[1];
} 

// PHP バージョンチェック

$phpversion = phpversion();
$versionArray = explode(".", $phpversion);
$version = $versionArray[0].".".$versionArray[1];

if ($version < 7.2) {
  $error_msg[] = "現在のサーバーの PHP のバージョンが ".$phpversion. " では、a-blog cms Ver.".$ablogcmsVersion." を実行することができません。PHP 7.2.5 以上をご利用ください。";
} elseif ($version > 8.1) {
  $error_msg[] = "現在のサーバーの PHP のバージョンが ".$phpversion. " では、a-blog cms Ver.".$ablogcmsVersion." を実行することができません。PHP 8.1.x 以下をご利用ください。";
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

    # ダウンロード元 URL
    $download = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_update%sx.zip",$ablogcmsVersion,$ablogcmsVersion,$now_versionArray[0]);

    # ダウンロード後のZipファイル名
    $zipFile = sprintf("./acms%s_update%sx.zip",$ablogcmsVersion,$now_versionArray[0]);

    # 解凍後の全体フォルダ名
    $zipAfterDirName = sprintf("acms%s_update%sx",$ablogcmsVersion,$now_versionArray[0]);

    # 解凍後の a-blog cms のフォルダ名
    $cmsDirName = "ablogcms";

    $ablogcmsDir = $installPath."/".$zipAfterDirName."/".$cmsDirName;

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
    rename ("./private/config.system.yaml", "./private/config.system_".$ablogcmsVersion.".yaml");
    copy ($backupDir."/private/config.system.yaml", "./private/config.system.yaml");

    # /extension を戻す
    if (is_dir($backupDir."/extension")) {
      rename ("./extension","./extension_".$ablogcmsVersion);
      dir_shori ("copy", $backupDir."/extension", "./extension");
    }

    // --------------------------
    // .htaccess の設定
    // --------------------------

    rename("./htaccess.txt", './htaccess_'.$ablogcmsVersion.'.txt');

    rename("./private/htaccess.txt", './private/.htaccess');
    rename("./themes/htaccess.txt", './themes/.htaccess');   
    rename("./editorconfig.txt", './.editorconfig');
    rename("./env.txt", './.env');
    rename("./gitignore.txt", './.gitignore');

    if (!is_dir("./cache")) {
      mkdir("./cache");
        if (is_file($backupDir."/cache/.htaccess")) {
          rename($backupDir."/cache/.htaccess", "./cache/.htaccess");
        }
    } elseif (is_file("./cache/htaccess.txt")) {
      rename("./cache/htaccess.txt", './cache/.htaccess');
    }

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
            rename($nowDir."/".$file, $newDir."/".$file);
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
    rmdir($nowDir);
  }

  return true;
}

function download_version_check()
{
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $html = file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/3.1.(.*).html">(.*)<\/a><\/h1>/', $html, $matches);

  if (count($matches) ){
    if (is_numeric($matches[2])) {
      return "3.1." . $matches[2];
    } else {
      return;
    }
  } else {
    return;
  }
}