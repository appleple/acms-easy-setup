<?php

// --------------------------
//
// さくら 共用レンタルサーバー a-blog cms v2.8.x 簡単セットアップ
//
// --------------------------

$ablogcmsVersion = ""; #サイトからバージョンを自動チェック

# ERROR になる場合や 2.8系のバージョンを
# 指定したい場合には、バージョンを設定してください。

#$ablogcmsVersion = "2.8.0";

// --------------------------

# インストーラー の
# MySQL の設定を事前に行う場合に
# ここを設定してください。

$dbHost     = 'mysql@@@.db.sakura.ne.jp';
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
$downloadIoncube = "http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_fre_11_x86-64.zip";
if (preg_match('/FreeBSD 9.1/',php_uname("v"))){
  $downloadIoncube = "http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_fre_9_x86-64.zip";
}

# ioncube Loader ダウンロード後のZipファイル名
$zipFileIoncube ="ioncube.zip";

$installPath = realpath('.');

$phpName = basename($_SERVER['SCRIPT_NAME']);

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

if ($versionArray[0]==7 && $versionArray[1] > 0) {
   $download = $download71;
   $zipAfterDirName = $zipAfterDirName71;
} elseif ($versionArray[0] == 7 && $versionArray[1] == 0) {
   $download = $download56;
   $zipAfterDirName = $zipAfterDirName56;
} elseif ($versionArray[1] >= 6) { 
    $download = $download56;
    $zipAfterDirName = $zipAfterDirName56;
} elseif ($versionArray[1] == 5) { 
    $download = $download55;
    $zipAfterDirName = $zipAfterDirName55;
} elseif ($versionArray[0] == 5 && $versionArray[1] == 4) { 
    $download = $download55;
    $zipAfterDirName = $zipAfterDirName55;
} elseif ($versionArray[1] == 3) { 
    $download = $download55;
    $zipAfterDirName = $zipAfterDirName55;
} else { 
    echo "php version check error!";
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
$iniData = sprintf("date.timezone = 'Asia/Tokyo'\nzend_extension = \"%s/ioncube/ioncube_loader_fre_%s.so\"",$installPath,$version);
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

# RewriteBase を調整
if (!preg_match("/www$/", $installPath)) {

	if ($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'] != $_SERVER['SCRIPT_FILENAME']) {

		$installDir = str_replace($phpName, "", $_SERVER['SCRIPT_NAME']);
		$htaccessFile = $installPath."/.htaccess";
		$data = file_get_contents($htaccessFile);
		$data = str_replace("# RewriteBase /", "RewriteBase ".$installDir, $data);
		file_put_contents($htaccessFile, $data);
	}
} 

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
