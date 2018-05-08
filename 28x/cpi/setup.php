<?php

// --------------------------
//
// CPI ACE01用 a-blog cms 2.8.0 簡単セットアップ
//
// --------------------------

$ablogcmsVersion = ""; #サイトからバージョンを自動チェック

# ERROR になる場合や 2.8系のバージョンを
# 指定したい場合には、バージョンを設定してください。

#$ablogcmsVersion = "2.8.0";

// --------------------------

# PHP のバージョンを指定してください。

$php_version = "7.1"; // or "5.5" or "5.6" or "7.0"

# インストーラー の
# MySQL の設定を事前に行う場合に
# ここを設定してください。

# ACE01 2011 MySQL 5.5
# ACE01 2015 MySQL 5.5 / 5.6
# ACE01 2018 MySQL 5.6

$mysql_version = "5.6"; // or "5.5"

# ACE01 のサーバー種類を取得できない場合に設定ください

$server = ""; // "2011" or "2015" or "2018"


// --------------------------

# データベースの指定

$dbHost     = '127.0.0.1';
$dbName     = '';
$dbUser     = '';
$dbPass     = '';

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

# ioncube Loader ダウンロード元 URL
$downloadIoncube = "http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_fre_9_x86-64.zip";

# ioncube Loader ダウンロード後のZipファイル名
$zipFileIoncube ="ioncube.zip";

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


if (!$server) {
  $server = substr(gethostbyaddr($_SERVER['SERVER_ADDR']),-18,2);
}

  switch ($server) {

    case "2018":
    case "ah":
    case "ai":

      switch ($php_version) {
        case "7.1":
            $download = $download71;
            $zipAfterDirName = $zipAfterDirName71;
            $phpVersion = "71";
            $ioncubePhpVersion = "7.1";
            break;
        case "7.0":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "70";
            $ioncubePhpVersion = "7.0";
            break;
        case "5.6":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "5630";
            $ioncubePhpVersion = "5.6";
            break;
        default:
            echo "php version error : ".$php_version;
            exit;
      }
      break;

    case "2015":
    case "ad":
    case "ae":

    # ACE01 2015

    switch ($php_version) {
        case "7.1":
            $download = $download71;
            $zipAfterDirName = $zipAfterDirName71;
            $phpVersion = "71";
            $ioncubePhpVersion = "7.1";
            break;
        case "7.0":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "70";
            $ioncubePhpVersion = "7.0";
            break;
        case "5.6":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "5630";
            $ioncubePhpVersion = "5.6";
            break;
        case "5.5":
            $download = $download55;
            $zipAfterDirName = $zipAfterDirName55;
            $phpVersion = "5527";
            $ioncubePhpVersion = "5.5";
            break;
        default:
            echo "php version error : ".$php_version;
            exit;
    }
    break;

    case "2011":
    case "aa":

    # ACE01 2011
    switch ($php_version) {
        case "7.1":
            $download = $download71;
            $zipAfterDirName = $zipAfterDirName71;
            $phpVersion = "71";
            $ioncubePhpVersion = "7.1";
            break;
        case "7.0":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "70";
            $ioncubePhpVersion = "7.0";
            break;
        case "5.6":
            $download = $download56;
            $zipAfterDirName = $zipAfterDirName56;
            $phpVersion = "5630";
            $ioncubePhpVersion = "5.6";
            break;
        case "5.5":
            $download = $download55;
            $zipAfterDirName = $zipAfterDirName55;
            $phpVersion = "5516";
            $ioncubePhpVersion = "5.5";
            break;
        case "5.4":
            $download = $download55;
            $zipAfterDirName = $zipAfterDirName55;
            $phpVersion = "5425";
            $ioncubePhpVersion = "5.4";
            break;
        case "5.3":
            $download = $download55;
            $zipAfterDirName = $zipAfterDirName55;
            $phpVersion = "5329";
            $ioncubePhpVersion = "5.3";
            break;
        default:
            echo "php version error : ".$php_version;
            exit;
    }
    break;
    default:
        echo "server check error : ".gethostbyaddr($_SERVER['SERVER_ADDR']);
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
// ioncube ファイルをダウンロード
// --------------------------

$fp = fopen($downloadIoncube, "r");
if ($fp !== FALSE) {
    file_put_contents($zipFileIoncube, "");
    while(!feof($fp)) {
        $buffer = fread($fp, 4096);
        if ($buffer !== FALSE) {
            file_put_contents($zipFileIoncube, $buffer, FILE_APPEND);
        }
    }
    fclose($fp);
} else {
    echo 'ioncube loader download Error ! : '.$download;
    exit;
}

// --------------------------
// ioncube ファイルを解凍
// --------------------------

$zip = new ZipArchive();
$res = $zip->open($zipFileIoncube);
 
if($res === true){
    $zip->extractTo($installPath);
    $zip->close();

} else {
    echo 'ioncube loader unZip Error ! : '. $zipFileIoncube;
    exit;
}

// --------------------------
// php.ini の設定
// --------------------------

$iniFileName = "php.ini";
$iniData = sprintf("date.timezone = 'Asia/Tokyo'\nzend_extension = \"%s/ioncube/ioncube_loader_fre_%s.so\"",$installPath,$ioncubePhpVersion);
file_put_contents($installPath."/".$iniFileName, $iniData, FILE_APPEND | LOCK_EX);

# setupディレクトリにも php.ini が必要な時のために
copy($installPath."/php.ini", $installPath."/setup/php.ini");



// --------------------------
// .htaccess の設定
// --------------------------

rename($installPath."/htaccess.txt", $installPath.'/.htaccess');
rename($installPath."/archives/htaccess.txt", $installPath.'/archives/.htaccess');
rename($installPath."/archives_rev/htaccess.txt", $installPath.'/archives_rev/.htaccess');
rename($installPath."/media/htaccess.txt", $installPath.'/media/.htaccess');
rename($installPath."/private/htaccess.txt", $installPath.'/private/.htaccess');
rename($installPath."/themes/htaccess.txt", $installPath.'/themes/.htaccess');

$htaccess = file_get_contents($installPath."/.htaccess");

$new = sprintf("<Files ~ \"\.ini\">
deny from all
</Files>
Options +SymLinksIfOwnerMatch
AddHandler x-httpd-php%s .php\n\n",$phpVersion);

$fp = fopen($installPath."/.htaccess",'w');
fwrite($fp,$new.$htaccess);
fclose($fp);

// --------------------------
// DB 初期設定
// --------------------------

if ($mysql_version == "5.6") {
    $dbHost .= ":3307";
}

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
// ファイルの削除
// --------------------------

unlink($zipFile);
unlink($zipFileIoncube);
unlink($phpName);

# index.html があった時にリネームしておく
if (is_file("./index.html")) {
    rename("./index.html", "_index.html");
}

# ioncube loader wizard は削除しておいた方がいいので
unlink($installPath."/ioncube/loader-wizard.php");

# プログラム以外のディレクトリを削除
dir_shori ("delete", $zipAfterDirName);

// --------------------------
// インストーラーに飛ぶ
// --------------------------

$jump = str_replace($phpName, "", $_SERVER['SCRIPT_NAME']);
header("Location: " . $jump);

// --------------------------
// ディレクトリを削除する function
// --------------------------

function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
       } 
     } 
     reset($objects); 
     rmdir($dir); 
   } 
} 

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
