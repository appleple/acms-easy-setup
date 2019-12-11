<?php

// --------------------------
//
// a-blog cms 2.x -> 2.8.x update
//
// --------------------------

# 今後は、このアップデートを利用することなく管理ページから
# 可能になります。

$ablogcmsVersion = ""; #サイトからバージョンを自動チェック

# ERROR になる場合や 2.8系のバージョンを
# 指定したい場合には、バージョンを設定してください。

#$ablogcmsVersion = "2.8.0";

// --------------------------

# 利用しているテーマを指定します。
# 複数あれば | で区切って指定してください。
# 継承しているテーマは全て含まれます。
# systemはアップデート対象になりますので指定しないでください。

#$useThemes = "blog2016"; # "site2015|blog2015";


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
$download55 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_update2x_php5.3.zip",$ablogcmsVersion,$ablogcmsVersion);
$download56 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_update2x_php5.6.zip",$ablogcmsVersion,$ablogcmsVersion);
$download71 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_update2x_php7.1.zip",$ablogcmsVersion,$ablogcmsVersion);

# ダウンロード後のZipファイル名
$zipFile = sprintf("./acms_%s.zip",$ymdhis);

# 解凍後の全体フォルダ名
$zipAfterDirName55 = sprintf("acms%s_update2x_php5.3",$ablogcmsVersion);
$zipAfterDirName56 = sprintf("acms%s_update2x_php5.6",$ablogcmsVersion);
$zipAfterDirName71 = sprintf("acms%s_update2x_php7.1",$ablogcmsVersion);

# 解凍後の a-blog cms のフォルダ名
$cmsDirName = "ablogcms";

// --------------------------
// バージョンチェック
// --------------------------

$versionArray = explode(".", phpversion());
$version = $versionArray[0].".".$versionArray[1];


if ($versionArray[0]==7 && $versionArray[1] > 0) {
   $download = $download71;
   $zipAfterDirName = $zipAfterDirName71;
} elseif ($versionArray[0] == 7 && $versionArray[1] == 0) {
   $download = $download56;
   $zipAfterDirName = $zipAfterDirName56;
} elseif ($versionArray[1] >= 6) {
    $download = $download56;
    $zipAfterDirName = $zipAfterDirName56;
} else {
    $download = $download55;
    $zipAfterDirName = $zipAfterDirName55;
}

$installPath = realpath('.');
$ablogcmsDir = $installPath."/".$zipAfterDirName."/".$cmsDirName;
$phpName = basename($_SERVER['PHP_SELF']);

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

if (is_dir("./cache")) dir_shori("move", "./cache", $backupDir."/cache");
if (is_dir("./extension")) dir_shori("move", "./extension", $backupDir."/extension");


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
rename("./private/htaccess.txt", './private/.htaccess');
rename("./themes/htaccess.txt", './themes/.htaccess');
rename("./cache/htaccess.txt", './cache/.htaccess');

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

  // Version 2.8.x のチェック用
  // 正常にチェックできない場合には 空 でかえす。

  $options['ssl']['verify_peer']=false;
  $options['ssl']['verify_peer_name']=false;
  $html=file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/2.8.(.*).html">(.*)<\/a><\/h1>/',$html,$matches);

  if (is_numeric($matches[2])) {
    return "2.8.".$matches[2];
  } else {
    return;
  }

}