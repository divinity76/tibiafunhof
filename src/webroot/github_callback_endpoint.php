<?php
declare(strict_types = 1);

// generating a secure token:
// php -r '$token=base64_encode(random_bytes(20));echo "token: ".$token.PHP_EOL."token urlencoded: ".urlencode($token).PHP_EOL."php-ified pre-computed sha1-hash: \"\\x".implode("\\x",str_split(bin2hex(hash("sha1",$token,true)),2))."\"".PHP_EOL;'
const TOKEN_SHA1 = "\xa0\x53\xb2\x4a\x60\x49\xfa\xa9\xb9\x3a\x5c\xd3\xba\x8b\x2c\x36\x52\xe0\x37\x78";
header("Content-Type: text/plain;charset=UTF-8");
$auth = function (): void {
    $user_string = (string) ($_GET['token'] ?? '');
    if (! hash_equals(TOKEN_SHA1, hash('sha1', $user_string, true))) {
        http_response_code(403);
        die("incorrect token.");
    }
};
$auth();
ignore_user_abort(true);
// disabled debug code
// Debugstuff();
$fp = fopen(__FILE__, 'rb');
if (! $fp) {
    throw new Exception('could not open self for flock');
}
if (! flock($fp, LOCK_EX | LOCK_NB)) {
    throw new Exception('could not lock self!');
}
register_shutdown_function(function () use (&$fp) {
    if (! flock($fp, LOCK_UN)) {
        throw new Exception('failed to release flock');
    }
    fclose($fp);
});
// don't need to be in root dir to run git pull :)

if (0) {
    if (! chdir(__DIR__ . "/../../")) {
        throw new Exception('chdir failed.. wtf, am i not in  src/webroot ?');
    }
}
// myexec ( "git reset --hard 2>&1" );

// no submodules, dont need this: myexec ( 'git submodule update --init --recursive 2>&1' );
// myexec ( "git pull --recurse-submodules=on-demand 2>&1" );
myexec("git pull 2>&1");

function myexec(string $str)
{
    $starttime = microtime(true);
    echo 'executing: ';
    var_dump($str);
    flush();
    $ret = 0;
    passthru($str, $ret);
    if ($ret !== 0) {
        var_dump($ret);
        var_dump('Error: this command failed: ', $str);
        die();
    }
    $endtime = microtime(true);
    echo ' time: ', number_format($endtime - $starttime, 17), "\n";
    return;
}

function Debugstuff()
{
    if (0) {
        ob_start();
        echo "getallheaders: ";
        var_dump(getallheaders());
        echo "php://input: ";
        var_dump(file_get_contents("php://input"));
        echo "_GET: ";
        var_dump($_GET);
        echo "_POST: ";
        var_dump($_POST);
        $str = ob_get_clean();
        echo $str;
        file_put_contents(((string) time()) . ".txt", $str);
    }
}