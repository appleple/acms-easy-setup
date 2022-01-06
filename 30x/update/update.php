<?php

# MYPAGE より 3.x 対応の license.php をダウンロードください。
# https://mypage.a-blogcms.jp/

// --------------------------
//
// a-blog cms 2.x -> 3.x update
//
// --------------------------

# 今後は、このアップデートを利用することなく管理ページから可能になります。

#$ablogcmsVersion = "3.0.0"; #バージョンを指定する際には行頭の # を削除してください。

// --------------------------

# 利用しているテーマを指定します。
# 複数あれば | で区切って指定してください。
# 継承しているテーマは全て含まれます。
# systemはアップデート対象になりますので指定しないでください。

#$useThemes = "blog2020"; # 複数の場合には | で区切って "site2020|blog2020";

// --------------------------
// 二重実行防止処理
// --------------------------

$lockFile = realpath('.'). "/update.lock";

if (is_file($lockFile)) {
  echo "lockFile:".$lockFile;
  exit;
} else {
  touch($lockFile);
}

// --------------------------
// 現在の a-blog cms のバージョンをチェック
// --------------------------

if (!$ablogcmsVersion) {
  $check = download_version_check ();
  if ($check) {
    $ablogcmsVersion = $check;
  } else {
    echo "web site version check error.";
    exit;
  }
}

// --------------------------

# 実行時刻
$ymdhis = date("YmdHis");

# ダウンロード元 URL
$download = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_update2x.zip",$ablogcmsVersion,$ablogcmsVersion);

# ダウンロード後のZipファイル名
$zipFile = sprintf("./acms%s_update2x.zip",$ablogcmsVersion);

# 解凍後の全体フォルダ名
$zipAfterDirName = sprintf("acms%s_update2x",$ablogcmsVersion);

# 解凍後の a-blog cms のフォルダ名
$cmsDirName = "ablogcms";

$installPath = realpath('.');
$ablogcmsDir = $installPath."/".$zipAfterDirName."/".$cmsDirName;
$phpName = basename($_SERVER['PHP_SELF']);

// --------------------------
// バージョンチェック
// --------------------------

$versionArray = explode(".", phpversion());
$version = $versionArray[0].".".$versionArray[1];

if ($version < 7.2) {
  echo "Installation error. Please use PHP 7.2 or higher.";
  exit;
} 

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

# ディレクトリを移動

dir_shori("move", "./js", $backupDir."/js");
dir_shori("move", "./lang", $backupDir."/lang");
dir_shori("move", "./php", $backupDir."/php");
dir_shori("move", "./private", $backupDir."/private");
dir_shori("move", "./themes", $backupDir."/themes");

if (is_dir("./extension")) dir_shori("move", "./extension", $backupDir."/extension");
#if (is_dir("./cache")) dir_shori("move", "./cache", $backupDir."/cache");
dir_shori ("delete", "cache");

// --------------------------
// update版 ファイル＆ディレクトリを移動
// --------------------------

dir_shori("move", $ablogcmsDir, $installPath);

# 運用中のものを利用するので新しいファイルは削除
unlink($installPath ."/htaccess.txt");

// --------------------------
// カスタマイズ部分を戻す
// --------------------------

# themes を戻す
if (isset($useThemes)) {
if ($handle = opendir($backupDir."/themes")) {
  while(false !== ($theme = readdir($handle))) {
    if ($theme != "." && $theme != "..") {
      if (preg_match("/".$useThemes."/", $theme)) {
        if (is_dir("./themes/".$theme)) {
          rename ("./themes/".$theme, "./themes/".$theme."_".$ablogcmsVersion);
        }
        dir_shori ("copy", $backupDir."/themes/".$theme, "./themes/".$theme);
      }
    }
  }
  closedir($handle);
}
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
rename("./cache/htaccess.txt", './cache/.htaccess');
rename("./editorconfig.txt", './.editorconfig');
rename("./env.txt", './.env');
rename("./gitignore.txt", './.gitignore');

// --------------------------
// php.ini があった時の処理
// --------------------------

if ( is_file( "./php.ini" )) {
    copy("./php.ini", "./setup/php.ini");
}

// --------------------------
// ファイルの削除
// --------------------------

#unlink($zipFile);
#unlink($phpName);

# プログラム以外のディレクトリを削除
if ( is_file( "./index.php" )) {
  dir_shori("delete", $zipAfterDirName);
} else {
  echo "update error!";
  exit;
}

unlink($lockFile);

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

  // Version 3.0.x のチェック用
  // 正常にチェックできない場合には 空 でかえす。

  $options['ssl']['verify_peer']=false;
  $options['ssl']['verify_peer_name']=false;
  $html=file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/3.0.(.*).html">(.*)<\/a><\/h1>/',$html,$matches);

  if (is_numeric($matches[2])) {
    return "3.0.".$matches[2];
  } else {
    return;
  }

}
