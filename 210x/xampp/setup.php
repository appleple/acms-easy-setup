<!DOCTYPE HTML>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>a-blog cms インストーラー (Windows XAMPP版)</title>
</head>
<body>
<?php

set_time_limit(0);

// --------------------------
//
// Windows XAMPP用 a-blog cms 2.10.x 簡単セットアップ
//
// --------------------------

$ablogcmsVersion = ""; #サイトからバージョンを自動チェック

# ERROR になる場合や 2.10系のバージョンを
# 指定したい場合には、バージョンを設定してください。

#$ablogcmsVersion = "2.10.0";

// --------------------------

$dbHost     = 'localhost';
$dbName     = 'DBacms';
$dbCreate   = 'checked';
$dbUser     = 'root';
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
$download56 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_php5.6.zip",$ablogcmsVersion,$ablogcmsVersion);
$download71 = sprintf("http://developer.a-blogcms.jp/_package/%s/acms%s_php7.1.zip",$ablogcmsVersion,$ablogcmsVersion);


# ダウンロード後のZipファイル名
$zipFile = "./acms_install.zip";

# 解凍後の全体フォルダ名
$zipAfterDirName56 = sprintf("acms%s_php5.6",$ablogcmsVersion);
$zipAfterDirName71 = sprintf("acms%s_php7.1",$ablogcmsVersion);

# 解凍後の a-blog cms のフォルダ名
$cmsDirName = "ablogcms";

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

$versionArray = explode(".", phpversion());
$version = $versionArray[0].".".$versionArray[1];

if (PHP_INT_SIZE == 4) {
	$bits = "x86";
} else {
	$bits = "x86-64";
}

if ($versionArray[0] == 7) {

	switch ($versionArray[1]) {

    case 0:
    $download = $download56;
    $zipAfterDirName = $zipAfterDirName56;
    $vc = "vc14";
    break;

		case 1:
			$download = $download71;
			$zipAfterDirName = $zipAfterDirName71;
			$vc = "vc14";
			break;

		case 2:
			$download = $download71;
			$zipAfterDirName = $zipAfterDirName71;
			$vc = "vc15";
      break;

		default:
			echo 'php version Error ! : '.$download;
			exit;
	}

} elseif ($versionArray[0] == 5) {

	switch ($versionArray[1]) {

		case 6:
			$download = $download56;
			$zipAfterDirName = $zipAfterDirName56;
			$vc = "vc11";
			break;

		default:
			echo 'php version Error ! : '.$download;
			exit;
	}
} else {
    echo 'php version Error ! : '.$download;
    exit;
}

# ioncube Loader ダウンロード元 URL
$downloadIoncube = sprintf("http://downloads.ioncube.com/loader_downloads/ioncube_loaders_win_%s_%s.zip",$vc,$bits);

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
    echo 'a-blog cms download Error ! : '.$phpversion();
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

# index.php があった時にリネームしておく
if (is_file("./index.php")) {
    rename("./index.php", "_index.php");
}

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

# php.ini のパスを設定する
$phpiniDir = explode("\htdocs",$installPath);
$iniFile = $phpiniDir[0]."\php\php.ini";

# 追記する設定内容
$iniData = sprintf("\r\n\r\ndate.timezone = 'Asia/Tokyo'\r\n\r\nzend_extension = \"%s\ioncube\ioncube_loader_win_%d.%d.dll\"",$installPath,$versionArray[0],$versionArray[1]);

$file = file_get_contents($iniFile);

if (preg_match("/ioncube_loader/i", $file)) {

	# 設定済み

} else {
	$file = fopen( $iniFile, "a+" );
	fwrite( $file, $iniData );
	fclose( $file );
}

// --------------------------
// .htaccess の設定
// --------------------------

if ( is_file($installPath."/htaccess.txt") ) {
	rename($installPath."/htaccess.txt", $installPath.'/.htaccess');
}

// --------------------------
// DB 初期設定
// --------------------------

$data = sprintf("<?php
\$dbDefaultHost     = '%s';
\$dbDefaultName     = '%s';
\$dbDefaultCreate   = '%s'; // '' or 'checked'
\$dbDefaultUser     = '%s';
\$dbDefaultPass     = '%s';
\$dbDefaultPrefix   = 'acms_';",$dbHost,$dbName,$dbCreate,$dbUser,$dbPass);
$db_default = $installPath."/setup/lib/db_default.php";
file_put_contents($db_default, $data);

// --------------------------
// ファイルの削除
// --------------------------

if ( is_file($zipFile) ) unlink($zipFile);
if ( is_file($zipFileIoncube) ) unlink($zipFileIoncube);
if ( is_file($phpName) ) unlink($phpName);

if ( is_file($installPath."/ioncube/loader-wizard.php") ) unlink($installPath."/ioncube/loader-wizard.php");

# プログラム以外のディレクトリを削除
dir_shori ("delete", $zipAfterDirName);

// --------------------------
// インストーラーに飛ぶ
// --------------------------

$jump = "http://".$_SERVER['HTTP_HOST'].str_replace($phpName, "", $_SERVER['SCRIPT_NAME']);
echo sprintf('<p style="text-align:center; margin-top:100px">XAMPPを再起動して <a href="%s">%s</a> にアクセスしてください。</p>',$jump,$jump);

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

  // Version 2.10.x のチェック用
  // 正常にチェックできない場合には 空 でかえす。

  $options['ssl']['verify_peer']=false;
  $options['ssl']['verify_peer_name']=false;
  $html=file_get_contents('https://developer.a-blogcms.jp/download/', false, stream_context_create($options));
  preg_match('/<h1 class="entry-title" id="(.*)"><a href="https:\/\/developer.a-blogcms.jp\/download\/package\/2.10.(.*).html">(.*)<\/a><\/h1>/',$html,$matches);

  if (is_numeric($matches[2])) {
    return "2.10.".$matches[2];
  } else {
    return;
  }

}


?>
</body>
</html>