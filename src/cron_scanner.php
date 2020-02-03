<?php
declare(strict_types = 1);

require_once ('everything.inc.php');
require_once ('hhb_.inc.php');

class hcr extends hhb_curl
{

    function __construct()
    {
        parent::__construct('', true);
    }

    public function exec_retry(string $url = null, int $max_retry = 5, &$retry_count = null): self
    {
        $last_ex = null;
        for ($i = 0; $i < $max_retry; ++ $i) {
            try {
                return $this->exec($url);
            } catch (Throwable $ex) {
                $last_ex = $ex;
            }
        }
        throw $last_ex;
    }
}
$hc = new hcr('', true);

// var_dump($hc) & die();
class new_char_data
{

    public $id;

    public $name;

    public $level;

    public $calculated_exp;

    public $online;
}
$db = getDB();
$addExpRecordStm = $db->prepare('INSERT INTO `exp_records` (`character_id`,`online`,`level`,`calculated_exp`)
VALUES(:character_id,:online,:level,:calculated_exp);');
for ($pageNum = 0; $pageNum < 999; ++ $pageNum) {
    // starting at 0 is not a bug; the website starts at zero too.
    $html = $hc->exec_retry('http://tibiafun.pl/index.php?subtopic=highscores&list=level&page=' . $pageNum)->getStdOut();
    $domd = @DOMDocument::loadHTML($html);
    $domd->getElementById("sidebar_right")->parentNode->removeChild($domd->getElementById("sidebar_right"));
    $xp = new DOMXPath($domd);
    $players = $xp->query("//a[contains(@href,'subtopic=characters&name=')]/parent::td/parent::tr");
    if ($players->length < 1) {
        break;
    }
    foreach ($players as $player) {
        $nd = new new_char_data();
        $nd->name = trim($xp->query(".//a", $player)->item(0)->textContent);
        $nd->level = (int) trim($xp->query("./td[last()]", $player)->item(0)->textContent);
        $nd->id = getDBIdFromName($nd->name, true, $nd->level);
        $nd->calculated_exp = experience_for_level($nd->level);
        $nd->online = ($xp->query(".//font[@color='green']", $player)->length === 1);
        $oldlevel = getDBLevelFromName($nd->name);
        if ($nd->level != $oldlevel) {
            echo "newsflash! \"{$nd->name}\" went from lvl {$oldlevel} to {$nd->level}\n";
        }
        $addExpRecordStm->execute(array(
            ':character_id' => $nd->id,
            ':online' => $nd->online,
            ':level' => $nd->level,
            ':calculated_exp' => $nd->calculated_exp
        ));
    }
}

function experience_for_level(int $level): int
{
    $ret = ((50 * pow($level, 3)) / 3) - (100 * pow($level, 2)) + (((850 * $level) / 3) - 200);
    if (false === ($ret_int = filter_var($ret, FILTER_VALIDATE_INT))) {
        var_dump($ret, number_format($ret, 200, ".", ""));
        throw new \LogicException("failed to get a whole number!");
    }
    return $ret_int;
}

function getDBLevelFromName(string $name): int
{
    $db = getDB();
    // $stm = $db->prepare('SELECT `id`, `name`, `first_seen`, `last_seen`, `first_seen_level` FROM characters WHERE `name` = ?');
    $stm = $db->prepare('SELECT `level` FROM `exp_records`
INNER JOIN `characters` ON `exp_records`.`character_id` = `characters`.`id`
WHERE `characters`.`name` = ?
ORDER BY `exp_records`.`id` DESC
LIMIT 1');

    $stm->execute(array(
        $name
    ));
    $data = $stm->fetchAll(PDO::FETCH_NUM);
    if (empty($data)) {
        $stm = $db->prepare('SELECT `first_seen_level` FROM `characters` WHERE `name` = ? LIMIT 1');
        $stm->execute([
            $name
        ]);
        $data = $stm->fetchAll(PDO::FETCH_NUM);
        if (empty($data)) {
            throw new \InvalidArgumentException("name is not in db - never call this function before putting the name in db!");
        }
    }
    return (int) ($data[0][0]);
}

function getDBIdFromName(string $name, bool $createNewOnMissing, int $level): int
{
    $db = getDB();
    // $stm = $db->prepare('SELECT `id`, `name`, `first_seen`, `last_seen`, `first_seen_level` FROM characters WHERE `name` = ?');
    $stm = $db->prepare('SELECT `id` FROM characters WHERE `name` = ?');

    $stm->execute(array(
        $name
    ));
    $data = $stm->fetchAll(PDO::FETCH_NUM);
    if (empty($data)) {
        if (! $createNewOnMissing) {
            throw new \InvalidArgumentException('not a valid name.. and createNewOnMissing is false');
        }
        echo "creating new char: {$name}..";
        $sql = 'INSERT INTO `characters` (`name`,`first_seen_level`) VALUES(?,?);';
        $stm = $db->prepare($sql);
        $stm->execute(array(
            $name,
            $level
        ));
        return (int) ($db->lastInsertId());
    }
    return (int) ($data[0][0]);
}
