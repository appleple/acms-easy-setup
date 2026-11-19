<?php
// 依存なしの最小テストランナー用ヘルパー。
// 使い方: php tests/run.php update|install [--coverage=90]

class AssertionFailed extends Exception
{
}

$GLOBALS['__tests'] = array();
$GLOBALS['__dir'] = null;

/** テストを登録する。各テストは空の一時ディレクトリを cwd として実行される。 */
function test($name, $fn)
{
    $GLOBALS['__tests'][] = array($name, $fn);
}

function fail($message)
{
    throw new AssertionFailed($message);
}

function assertSame($expected, $actual, $message = '')
{
    if ($expected !== $actual) {
        fail(($message ? $message . "\n" : '') . "  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
    }
}

function assertTrue($actual, $message = '')
{
    assertSame(true, $actual, $message);
}

function assertFalse($actual, $message = '')
{
    assertSame(false, $actual, $message);
}

function assertContains($needle, $haystack, $message = '')
{
    if (strpos($haystack, $needle) === false) {
        fail(($message ? $message . "\n" : '') . "  expected to contain: " . var_export($needle, true) . "\n  in: " . var_export($haystack, true));
    }
}

function assertNotContains($needle, $haystack, $message = '')
{
    if (strpos($haystack, $needle) !== false) {
        fail(($message ? $message . "\n" : '') . "  expected NOT to contain: " . var_export($needle, true));
    }
}

function assertFile($path)
{
    if (!file_exists($path)) {
        fail("file should exist: " . $path);
    }
}

function assertNoFile($path)
{
    if (file_exists($path)) {
        fail("file should NOT exist: " . $path);
    }
}

// ---- 一時ディレクトリ関連 ----

function testDir()
{
    return $GLOBALS['__dir'];
}

/** 一時ディレクトリ配下にファイルを作る。 */
function put($relativePath, $content = '')
{
    $path = testDir() . '/' . $relativePath;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $content);
    return $path;
}

/**
 * zip を作る。
 *
 * @param array $entries zip 内パス => 内容（末尾 / はディレクトリ）
 */
function makeZip($zipPath, array $entries)
{
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        if (substr($name, -1) === '/') {
            $zip->addEmptyDir(rtrim($name, '/'));
        } else {
            $zip->addFromString($name, $content);
        }
    }
    $zip->close();
    return $zipPath;
}

function removeTree($path)
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                removeTree($path . '/' . $entry);
            }
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

// ---- 実行 ----

/** @return array{0:int,1:int} [成功数, 失敗数] */
function runTests()
{
    $passed = 0;
    $failed = 0;
    $original = getcwd();

    set_error_handler(function ($no, $str, $file, $line) {
        if (!(error_reporting() & $no)) {
            return false; // @ で抑制された警告は対象外
        }
        throw new ErrorException($str, 0, $no, $file, $line);
    });

    foreach ($GLOBALS['__tests'] as $t) {
        list($name, $fn) = $t;
        $dir = realpath(sys_get_temp_dir()) . '/acms-easy-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $GLOBALS['__dir'] = $dir;
        chdir($dir);
        try {
            $fn();
            $passed++;
            echo "ok   {$name}\n";
        } catch (Exception $e) {
            $failed++;
            echo "FAIL {$name}\n" . $e->getMessage() . "\n  (" . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
        }
        chdir($original);
        removeTree($dir);
    }

    restore_error_handler();
    return array($passed, $failed);
}
