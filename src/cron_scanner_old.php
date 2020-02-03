<?php
declare(strict_types = 1);

// I somehow managed to make a mess of this...
require_once ('everything.inc.php');
require_once ('hhb_.inc.php');

die("DEPRECATED: use cron_scanner_simple.php instead");

const BLACKLISTED_NAMES = array(
    'D99' // bugged char, can't get name properly...
);

$db = getDB();
$hc = new hhb_curl('', true);
$chars = fetchAllCharacters();
foreach ($chars as $char) {
    $char->fetchLevelAndOnlineStatus();
    $char->addNewExpRecord();
}

/**
 *
 * @return Character[]
 */
function fetchAllCharacters(): array
{
    $ret = [];
    $createNewOnMissing = true;

    foreach (fetchAllCharacterNames() as $name) {
        $ret[] = Character::loadFromName($name, $createNewOnMissing);
    }
    return $ret;
}

class Character
{

    /**
     * mysql db id
     *
     * @var int $id
     */
    public $id;

    /**
     * mysql name
     *
     * @var string $name
     */
    public $name;

    /**
     * first time character was ever seen
     *
     * @var DateTimeImmutable $first_seen
     */
    public $first_seen;

    /**
     * ...unused for now
     *
     * @var NULL $last_seen
     */
    public $last_seen;

    /**
     * first time character was ever seen
     *
     * @var DateTimeImmutable $first_seen_level
     */
    public $first_seen_level;

    /**
     * is online
     *
     * @var bool|null
     */
    public $is_online = null;

    public $new_level = null;

    function __construct()
    {
        $this->first_seen = (new DateTimeImmutable())->setTimestamp(strtotime($this->first_seen));
    }

    public function fetchLevelAndOnlineStatus(): void
    {
        if (! is_null($this->is_online) && ! empty($this->new_level)) {
            // we already got the data..
            return;
        }
        static $hc = null;
        if ($hc === null) {
            $hc = new hhb_curl('', true);
        }
        $html = retryExec('http://tibiafun.pl/index.php?subtopic=characters&name=' . urlencode($this->name), $hc)->getStdOut();
        $domd = @DOMDocument::loadHTML($html);
        $domd->getElementById("sidebar_right")->parentNode->removeChild($domd->getElementById("sidebar_right"));

        $xp = new DOMXPath($domd);
        $level = $xp->query("//td[contains(text(),'Level:')]")->item(0)->nextSibling->textContent;
        $level = trim($level);
        if (false === ($ilevel = filter_var($level, FILTER_VALIDATE_INT))) {
            var_dump($xp->query("//td[contains(text(),'Name:')]"), $level, $ilevel);
            throw new \LogicException("failed to fetch level from name: " . $this->name);
        }
        $this->new_level = $ilevel;
        $isOnline = $xp->query("//td[contains(text(),'Name:')]")->item(0)->nextSibling->firstChild->getAttribute("color");
        $isOnline = (strtolower($isOnline) === "green");
        $this->is_online = $isOnline;
    }

    public function addNewExpRecord()
    {
        $db = getDB();
        $oldLevel = $db->query('SELECT `level` FROM exp_records WHERE character_id = ' . ((int) $this->id) . " ORDER BY id DESC LIMIT 1");
        $oldLevel = $oldLevel->fetch(PDO::FETCH_NUM);
        $oldLevel = empty($oldLevel) ? "null" : $oldLevel[0];
        $this->fetchLevelAndOnlineStatus();
        $stm = $db->prepare('INSERT INTO exp_records (`character_id`,`online`,`level`,`calculated_exp`) VALUES(:character_id,:is_online,:level,:calculated_exp);');
        $stm->execute(array(
            ':character_id' => $this->id,
            ':is_online' => $this->is_online ? "1" : "0",
            ':level' => $this->new_level,
            ':calculated_exp' => experience_for_level($this->new_level)
        ));
        if ($this->new_level !== $oldLevel) {
            echo "\nnewsflash: {$this->name} went from {$oldLevel} to {$this->new_level}\n";
        }
    }

    public static function loadFromName(string $name, bool $createNewOnMissing): self
    {
        $db = getDB();
        $stm = $db->prepare('SELECT `id`, `name`, `first_seen`, `last_seen`, `first_seen_level` ' . ' FROM characters WHERE `name` = ?');
        $stm->setFetchMode(PDO::FETCH_CLASS, self::class);
        $stm->execute(array(
            $name
        ));
        $data = $stm->fetch();

        if (empty($data)) {
            if (! $createNewOnMissing) {
                throw new \InvalidArgumentException('not a valid name.. and createNewOnMissing is false');
            }
            echo "creating new char: {$name}..";
            $sql = 'INSERT INTO `characters` (`name`,`first_seen_level`) VALUES(?,?);';
            $level = fetchLevelFromName($name);
            echo " level {$level}..";
            $stm = $db->prepare($sql);
            $stm->execute(array(
                $name,
                $level
            ));
            echo "done.";
            return self::loadFromName($name, false);
        }
        return $data;
    }
}

function fetchLevelFromName(string $name): int
{
    static $hc = null;
    if ($hc === null) {
        $hc = new hhb_curl('', true);
    }
    $html = retryExec('http://tibiafun.pl/index.php?subtopic=characters&name=' . urlencode($name), $hc)->getStdOut();
    $domd = @DOMDocument::loadHTML($html);
    $domd->getElementById("sidebar_right")->parentNode->removeChild($domd->getElementById("sidebar_right"));

    $xp = new DOMXPath($domd);
    $level = $xp->query("//td[contains(text(),'Level:')]")->item(0)->nextSibling->textContent;
    $level = trim($level);
    if (false === ($ilevel = filter_var($level, FILTER_VALIDATE_INT))) {
        throw new \LogicException("failed to fetch level from name: " . $name);
    }
    return $ilevel;
}

/**
 * fetch all characters from website
 *
 * @return string[] names
 */
function fetchAllCharacterNames(): array
{
    $hc = new hhb_curl();

    $ret = [];

    for ($i = 0; $i < 999; ++ $i) {
        // starting at 0 is not a bug,
        // the website starts at 0 too.
        $url = 'http://tibiafun.pl/index.php?subtopic=highscores&list=fishing&page=' . $i;
        $html = retryExec($url, $hc)->getStdOut();
        $domd = @DOMDocument::loadHTML($html);
        $domd->getElementById("sidebar_right")->parentNode->removeChild($domd->getElementById("sidebar_right"));
        $xp = new DOMXPath($domd);
        // <a href="index.php?subtopic=characters&amp;name=Drunkard">Drunkard</a>
        $characters = $xp->query("//a[contains(@href,'index.php?subtopic=characters') and contains(@href,'name=')]");
        if ($characters->length < 1) {
            break;
        }
        foreach ($characters as $char) {
            $name = trim($char->textContent);
            if (in_array($name, $ret, true) || in_array($name, BLACKLISTED_NAMES, true)) {
                continue;
            }
            $ret[] = $name;
        }
    }
    // $ret = array_reverse($ret, false);
    return $ret;
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

function retryExec(string $url = null, hhb_curl $hc, int $max_retry = 5): hhb_curl
{
    $attempts = 0;
    $last_ex = null;
    for ($attempts = 0; $attempts < $max_retry; ++ $attempts) {
        try {
            $hc->exec($url);
            return $hc;
        } catch (Throwable $ex) {
            $last_ex = $ex;
        }
    }
    throw $last_ex;
}