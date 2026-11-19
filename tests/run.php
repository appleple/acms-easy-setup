<?php
// 使い方: php tests/run.php update|install [--coverage=90]
//   update  : packages/update/update.php を対象に tests/update/*Test.php を実行
//   install : packages/install/setup.php を対象に tests/install/*Test.php を実行
// update.php と setup.php は同名の関数を持つため、対象ごとに別プロセスで実行する。
// --coverage=N を付けると pcov で行カバレッジを測り、N% 未満なら失敗にする。

$target = isset($argv[1]) ? $argv[1] : '';
$targets = array(
    'update'  => array('packages/update/update.php', 'ACMS_EASY_UPDATE_NO_RUN'),
    'install' => array('packages/install/setup.php', 'ACMS_EASY_SETUP_NO_RUN'),
);
if (!isset($targets[$target])) {
    fwrite(STDERR, "usage: php tests/run.php update|install [--coverage=90]\n");
    exit(2);
}

$threshold = null;
foreach ($argv as $arg) {
    if (preg_match('/^--coverage=(\d+(?:\.\d+)?)$/', $arg, $m)) {
        $threshold = (float) $m[1];
    }
}

$root = realpath(__DIR__ . '/..');
list($relative, $guard) = $targets[$target];
$source = $root . '/' . $relative;

require __DIR__ . '/lib/mini.php';

$collect = $threshold !== null;
if ($collect) {
    if (!function_exists('pcov\start')) {
        fwrite(STDERR, "pcov 拡張が必要です（php -d pcov.enabled=1 -d pcov.directory=. で実行）\n");
        exit(2);
    }
    \pcov\start();
}

define($guard, true);
require $source;
foreach (glob(__DIR__ . '/' . $target . '/*Test.php') as $file) {
    require $file;
}

list($passed, $failed) = runTests();
echo "\n{$passed} passed, {$failed} failed\n";

if ($collect) {
    \pcov\stop();
    $data = \pcov\collect(\pcov\inclusive, array($source));
    $lines = isset($data[$source]) ? $data[$source] : array();

    // @coverage-ignore-start ～ @coverage-ignore-end の範囲は計測対象から除く
    $ignored = array();
    $inIgnore = false;
    foreach (file($source) as $i => $text) {
        if (strpos($text, '@coverage-ignore-end') !== false) {
            $inIgnore = false;
        }
        if ($inIgnore) {
            $ignored[$i + 1] = true;
        }
        if (strpos($text, '@coverage-ignore-start') !== false) {
            $inIgnore = true;
        }
    }

    $covered = 0;
    $total = 0;
    $missed = array();
    foreach ($lines as $no => $state) {
        if ($state === -2 || isset($ignored[$no])) { // -2: 実行され得ない行
            continue;
        }
        $total++;
        if ($state === 1) {
            $covered++;
        } else {
            $missed[] = $no;
        }
    }
    $percent = $total > 0 ? round($covered / $total * 100, 2) : 0;
    echo "coverage {$relative}: {$percent}% ({$covered}/{$total})\n";
    if ($percent < $threshold) {
        echo "未実行の行: " . implode(',', $missed) . "\n";
        echo "カバレッジが {$threshold}% 未満です\n";
        exit(1);
    }
}

exit($failed > 0 ? 1 : 0);
