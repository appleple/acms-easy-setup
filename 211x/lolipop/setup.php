<!DOCTYPE HTML>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>a-blog cms インストーラー (ロリポップ版)</title>
</head>
<body style="text-align:center; margin-top:100px">
<?php

// --------------------------
//
// ロリポップ用 a-blog cms 2.11.x 簡単セットアップ
//
// --------------------------

$ablogcmsVersion = ""; #サイトからバージョンを自動チェック

# ERROR になる場合や 2.11系のバージョンを
# 指定したい場合には、バージョンを設定してください。

#$ablogcmsVersion = "2.11.0";

// --------------------------

# インストーラー の
# MySQL の設定を事前に行う場合に
# ここを設定してください。

$dbHost     = 'mysql@@@.phy.lolipop.lan';
$dbName     = '';
$dbUser     = '';
$dbPass     = '';

// --------------------------
// 特製テーマ設定
// --------------------------

// 標準テーマ以外でインストールする際には、以下の # を外して設定ください。

// ダウンロードファイル

# $theme_download = "https://www.a-blogcms.jp/_download/utsuwa.zip";

// テーマ名

# $theme_name = "utsuwa";

// --------------------------
// 現在の a-blog cms のバージョンをチェック
// --------------------------

if (!$ablogcmsVersion) {
  $check = download_version_check ();
  if ($check) {
    $ablogcmsVersion = $check;
  } else {
    echo '<p>最新版の a-blog cms のバージョンを取得できませんでした。</p>';
    exit;
  }
}


// --------------------------

# ダウンロード元 URL
$download55 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_php5.3.zip",$ablogcmsVersion,$ablogcmsVersion);
$download56 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_php5.6.zip",$ablogcmsVersion,$ablogcmsVersion);
$download71 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_php7.1.zip",$ablogcmsVersion,$ablogcmsVersion);

# ダウンロード後のZipファイル名
$zipFile = "./acms_install.zip";

# 解凍後の全体フォルダ名
$zipAfterDirName55 = sprintf("acms%s_php5.3",$ablogcmsVersion);
$zipAfterDirName56 = sprintf("acms%s_php5.6",$ablogcmsVersion);
$zipAfterDirName71 = sprintf("acms%s_php7.1",$ablogcmsVersion);

# 解凍後の a-blog cms のフォルダ名
$cmsDirName = "ablogcms";

$installPath = realpath('.');

$phpName = basename($_SERVER['PHP_SELF']);

// --------------------------
// 動作チェック
// --------------------------

if (is_file("./license.php")) {
  echo "Installation error. Please use the updated version.";
  exit;
}

// --------------------------
// バージョンのチェック
// --------------------------

$versionArray = explode(".", phpversion());
$version = $versionArray[0].".".$versionArray[1];

if ($versionArray[0] == 7) {
  if ($versionArray[1] == 0) {
    $download = $download56;
    $zipAfterDirName = $zipAfterDirName56;
  } else {
    $download = $download71;
    $zipAfterDirName = $zipAfterDirName71;
  }
} elseif ($versionArray[0] == 5 && $versionArray[1] == 6) {
    $download = $download56;
    $zipAfterDirName = $zipAfterDirName56;
} else {
  echo "Please change the version of PHP to CGI version 5.6.";
  exit;
}

$ablogcmsDir = $installPath."/".$zipAfterDirName."/".$cmsDirName."/";

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
    echo 'a-blog cms download Error ! : '.$download;
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
    echo 'a-blog cms unZip Error ! : '. $zipFile;
    exit;
}

// --------------------------
// a-blog cms ディレクトリを移動
// --------------------------

if ($handle = opendir($ablogcmsDir)) {
    while(false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
             rename($ablogcmsDir.$entry, $installPath ."/". $entry);
        }
    }
    closedir($handle);
} else {
    echo 'a-blog cms move Error ! :'.$ablogcmsDir;
    exit;
}

// --------------------------
// .htaccess の設定
// --------------------------

rename($installPath."/htaccess.txt", $installPath.'/.htaccess');
rename($installPath."/archives/htaccess.txt", $installPath.'/archives/.htaccess');
rename($installPath."/archives_rev/htaccess.txt", $installPath.'/archives_rev/.htaccess');
rename($installPath."/media/htaccess.txt", $installPath.'/media/.htaccess');
rename($installPath."/private/htaccess.txt", $installPath.'/private/.htaccess');
rename($installPath."/themes/htaccess.txt", $installPath.'/themes/.htaccess');

// --------------------------
// DB 初期設定
// --------------------------

$data = sprintf("<?php
\$dbDefaultHost     = '%s';
\$dbDefaultName     = '%s';
\$dbDefaultCreate   = ''; // '' or 'checked'
\$dbDefaultUser     = '%s';
\$dbDefaultPass     = '%s';
\$dbDefaultPrefix   = 'acms_';",$dbHost,$dbName,$dbUser,$dbPass);

$db_default = $installPath."/setup/lib/db_default.php";
file_put_contents($db_default, $data);

// --------------------------
// lolipop で image_magick を使わない設定に変更
// --------------------------

  $yamlFile = "./private/config.system.yaml";

  $file = file_get_contents($yamlFile);
  if (preg_match("/image_magick/i", $file)) {
  #設定済み
  } else {
    $image_magick ="\n\nimage_magick : off";
  file_put_contents($yamlFile, $file.$image_magick);
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
// 特製テーマファイルをダウンロード 
// --------------------------

if ($theme_name) {

  $zipThemeFile = $theme_name . ".zip";

  $fp = fopen($theme_download, "r");
  if ($fp !== FALSE) {
      file_put_contents($zipThemeFile, "");
      while(!feof($fp)) {
          $buffer = fread($fp, 4096);
          if ($buffer !== FALSE) {
              file_put_contents($zipThemeFile, $buffer, FILE_APPEND);
          }
      }
      fclose($fp);

  } else {
      echo 'theme '.$theme_name.' download Error ! : '.$theme_download;
      exit;
  }

  $zip = new ZipArchive();
  $res = $zip->open($zipThemeFile);

  if($res === true){
      $zip->extractTo($installPath);
      $zip->close();

  } else {
      echo 'theme unZip Error ! : '. $zipThemeFile;
      exit;
  }

  dir_shori("move", "./".$theme_name."/bin/".$theme_name, "./setup/bin/".$theme_name);
  dir_shori("move", "./".$theme_name."/themes/".$theme_name, "./themes/".$theme_name);

  rename("./".$theme_name."/tpl/install.html", "./setup/tpl/install.html");
  rename("./".$theme_name."/img/".$theme_name.".jpg", "./setup/img/".$theme_name.".jpg");

  dir_shori ("delete", $theme_name);
  unlink($zipThemeFile);

}

// --------------------------
// インストーラーに飛ぶ
// --------------------------

$jump = str_replace($phpName, "", $_SERVER['SCRIPT_NAME']);
header("Location: " . $jump);

// --------------------------
// ディレクトリを操作 function ( move / copy / delete )
// --------------------------
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

function download_version_check () {

  // Version 2.11.x のチェック用
  // 正常にチェックできない場合には 空 でかえす。

  $options['ssl']['verify_peer']=false;
  $options['ssl']['verify_peer_name']=false;
  $html=file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/2.11.(.*).html">(.*)<\/a><\/h1>/',$html,$matches);

  if (is_numeric($matches[2])) {
    return "2.11.".$matches[2];
  } else {
    return;
  }

}

?>
</body>
</html>