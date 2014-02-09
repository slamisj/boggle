<?php

/**
 * My NApplication
 *
 * @copyright  Copyright (c) 2010 John Doe
 * @package    MyApplication
 */



/**
 * Users authenticator.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class GameModel extends NObject
{
  public $userId;
  public $variants = array(
    "a" => array("á"),
    "c" => array("", "č"),
    "d" => array("", "ď"),
    "e" => array("é", "ě"),
    "i" => array("í"),
    "n" => array("", "ň"),
    "o" => array("ó"),
    "r" => array("", "ř"),
    "s" => array("", "š"),
    "t" => array("", "ť"),
    "u" => array("ú", "ů"),
    "y" => array("ý"),
    "z" => array("", "ž"));


  public function log($msg, $iduser = null)
  {
    dibi::query("INSERT INTO log",
                  array('msg' => $msg,
                        'iduser' => $iduser,
                        'createdat%sql' => 'NOW()'));
    //echo $msg . "<br />";
  }

  /**
   * Computes salted password hash.
   * @param  string
   * @return string
   */
  public function calculateHash($password)
  {
    $salt = $this->getParam('HASH_SALT');
    return md5($password . str_repeat($salt, 10));
  }

  public function calculateSecret($username)
  {
    $salt = $this->getParam('SECRET_HASH_SALT');
    return md5($username . str_repeat($salt, 10));
  }

  public function evalErrorWords()
  {
    for ($i = 0; $i <= 2; $i++) {
      $idWord = dibi::fetchSingle('SELECT MIN(id) FROM word WHERE status = "E"');
      $text = dibi::fetchSingle('SELECT text FROM word WHERE id = %i', $idWord);

      echo 'idword: ' . $idWord . "/" . $text . "<br />";
      $status = $this->checkWord($idWord, true);
      echo 'status: ' . $status . "<br />";
      sleep(2);
    }
    die();
  }

  public function delAnonymous()
  {
    for ($i = 0; $i < 50; $i++) {
      $idUser = dibi::fetchSingle('SELECT MIN(id) FROM user WHERE isAnonymous = 1');
      echo 'iduser: ' . $idUser . "<br />";
      dibi::query('DELETE FROM game_user_word WHERE idgameuser IN
          (SELECT id FROM game_user gu WHERE iduser = %i)', $idUser);
      echo 'delguw: ' . dibi::affectedRows() . "<br />";
      //$numGames = dibi::fetchSingle('SELECT COUNT(*) FROM game_user gu JOIN user u ON u.id = gu.iduser WHERE u.isAnonymous = 1');
      dibi::query('DELETE FROM game_user WHERE iduser = %i', $idUser);
      $numGames = dibi::affectedRows();
      echo 'delgames: ' . $numGames . "<br />";

      dibi::query('DELETE FROM user WHERE id = %i', $idUser);
      $current = $this->getParam('GAMES_DELETED');
      $this->setParam('GAMES_DELETED', $current + $numGames);
      echo 'totaldelgames: ' . ($current + $numGames) . "<br />";
    }
    die();
  }

  public function getParam($code)
  {
    $val = dibi::fetchSingle('SELECT value FROM param WHERE code = %s', $code);
    return $val;
  }

  public function setParam($code, $value)
  {
    dibi::query('UPDATE param SET ',
            array('value' => $value),
                  'WHERE `code` = %s', $code);
  }

  public function lostPwd($email)
  {
    $idUser = dibi::fetchSingle('SELECT id FROM user WHERE email = %s', $email);
    if (!$idUser) {
      throw new RuntimeException("E-mail '$email' nenalezen.");
    }
    $token = md5(time());
    dibi::query("INSERT INTO token",
                  array('iduser' => $idUser,
                        'token' => $token));
    return $token;
  }

  public function changePwd($pwd, $token)
  {
    $idUser = dibi::fetchSingle('SELECT iduser FROM token WHERE token = %s', $token);
    if (!$idUser) {
      throw new RuntimeException("Chybný e-mailový odkaz.");
    }
    dibi::query('UPDATE user SET ',
            array('password' => $this->calculateHash($pwd)),
                  'WHERE `id` = %i', $idUser);
  }

  public function isMyGame($idUser, $idGU)
  {
    if (is_null($idUser) || is_null($idGU)) {
      return false;
    }
    $idUser2 = dibi::fetchSingle('SELECT iduser FROM game_user WHERE id = %i', $idGU);
    return $idUser2 == $idUser;
  }

  public function getTravel()
  {
    $games = array();
    for ($i = 1; $i <= 10; $i++) {
      $gs = $this->createGameString(true);
      foreach ($gs as &$val) {
        $val = $this->fromHash($val);
      }
      $games[] = $gs;
    }
    return $games;
  }

  public function forceRegister($idUser = null)
  {
    if (!$idUser) {
      if ($_SERVER["REMOTE_ADDR"]) {
        $num = dibi::fetchSingle('SELECT COUNT(1) FROM user WHERE ip = %s', $_SERVER["REMOTE_ADDR"]);
        $this->log('forceReg1' . $num, $idUser);
        return $num > 0;
      } else {
        return false;
      }
    } else {
      $rec = $this->getUserRec($idUser);
      if ($rec["isAnonymous"] != 1) {
        return false;
      }
      if ($rec["ip"]) {
        $num = dibi::fetchSingle('SELECT COUNT(1) FROM user WHERE ip = %s', $rec["ip"]);
        if ($num > 1) {
          $this->log('forceReg2' . $rec["ip"], $idUser);
          return true;
        }
      }
      $num = dibi::fetchSingle('SELECT COUNT(1) FROM game_user WHERE iduser = %i', $idUser);
      if ($num >= 5) {
        $this->log('forceReg3', $idUser);
        return true;
      }
    }
  }

  public function getUserIdByFbId($fbId)
  {
    $user = dibi::fetchSingle('SELECT id FROM user WHERE facebookid = %s', $fbId);
    return $user;
  }

  public function getUserByHash($val)
  {
    $user = dibi::fetchSingle('SELECT * FROM user WHERE hash = %s', $val);
    return $user;
  }

  public function getCheckWord()
  {
    $word = dibi::fetch('SELECT * FROM word WHERE status IN ("I", "C") ORDER BY id LIMIT 1');
    $num = dibi::fetchSingle('SELECT COUNT(1) FROM word WHERE status IN ("I", "C", "E")');
    return array("word" => $word,
                 "num" => $num);
  }

  public function register($userInfo, $userId = null)
  {
    $retId = $userId;
      try {
        if (!is_null($userId)) {
          dibi::query('UPDATE user SET ',
            array('email' => $userInfo["email"],
                        'name' => $userInfo["name"],
                        'secret' => $this->calculateSecret($userInfo["email"]),
                        'isAnonymous' => null,
                        'password' => $this->calculateHash($userInfo["password"])),
                  'WHERE `id` = %i', $userId);
        } else {
          dibi::query("INSERT INTO user",
                  array('email' => $userInfo["email"],
                        'name' => $userInfo["name"],
                        'secret' => $this->calculateSecret($userInfo["email"]),
                        'isAll' => 1,
                        'password' => $this->calculateHash($userInfo["password"]),
                        'facebookid' => isset($userInfo["facebookid"]) ? $userInfo["facebookid"] : null,
                        'createdat%sql' => 'NOW()',
                        'ip' => $_SERVER["REMOTE_ADDR"]));
          $retId = dibi::insertId();
        }
        $mail = new NMail;
        $mail->setFrom($userInfo["email"])
        ->addTo('slamisj@seznam.cz')
        ->setSubject('Boggle - nový uživatel')
        ->setBody($userInfo["name"])
        ->send();
        return $retId;
      } catch (Exception $e) {
        throw new NAuthenticationException("E-mail je už obsazen, pokud ses registroval dříve, zkus se namísto registrace přihlásit.");
      }
  }

  public function createUser()
  {
      $id = 1;
      $counter = 1;
      $continue = true;
      do {
        $counter++;
        $email = 'anonym' . $counter . '@seznam.cz';
        try {
          dibi::query("INSERT INTO user",
                array('email' => $email,
                      'name' => 'anonym' . $counter,
                      'isAnonymous' => 1,
                      'isAll' => 1,
                      'password' => $this->calculateHash('heslo'),
                      'createdat%sql' => 'NOW()',
                      'ip' => $_SERVER["REMOTE_ADDR"]));
          $continue = false;
        } catch (Exception $e) {
          $this->log('CreateUser:' . $e->getMessage());
          null;
        }
      } while ($continue && $counter < 1000);
      return array('email' => $email, 'password' => 'heslo');
  }


  public function getAdjacent($pos) {
    if (is_null($pos)) {
      return range(0, 15);
    }
    if ($pos % 4 == 0) {
     $tmp = array(
      $pos - 4,
      $pos - 3,
      $pos + 1,
      $pos + 4,
      $pos + 5,
    );
    } elseif (($pos + 1) % 4 == 0) {
      $tmp = array(
        $pos - 5,
        $pos - 4,
        $pos - 1,
        $pos + 3,
        $pos + 4,
      );
    } else {
      $tmp = array(
        $pos - 5,
        $pos - 4,
        $pos - 3,
        $pos - 1,
        $pos + 1,
        $pos + 3,
        $pos + 4,
        $pos + 5,
      );
    }
    $out = array();
    foreach ($tmp as $val) {
      if ($val >= 0 && $val <= 15) {
        $out[] = $val;
      }
    }

    return $out;
  }



  public function inGameString($word, $gameString, $previousPos = null) {
    if (empty($word)) {
      return true;
    }
    foreach ($this->getAdjacent($previousPos) as $pos) {
      //echo $word[0] . '|' . $this->toAscii($word[0]) . '#';
      if ($gameString[$pos] == NStrings::toAscii($word[0])) {
        $newGameString = $gameString;
        $newGameString[$pos] = '-';
        if ($this->inGameString(array_slice($word, 1), $newGameString, $pos)) {
          return true;
        }
      }
    }
    return false;
  }
  public function createGameString($explode = true) {
    if (CZ) {
    $config = array(
      'eekyjs',
      'ltidze',
      'dvicwu',
      'madial',
      'pekrea',
      'petbsc',
      'teauob',
      'aclron',
      'lunvs#',
      'sumeao',
      'oevinq',
      'yjomit',
      'ornotz',
      'yfznvk',
      'iakrgs',
      'rxhiop'
    );
  } else {
    $config = array(
     'aaeegn',
    'elrtty',
    'aoottw',
    'abbjoo',
    'ehrtvw',
    'cimotu',
    'distty',
    'eiosst',
    'delrvy',
    'achops',
    'himnqu',
    'eeinsu',
    'eeghnw',
    'affkps',
    'hlnnrz',
    'deilrx'
    );
  }
    $output = array();
    foreach ($config as $i => $val) {
      $output[] = $val[rand(0,5)];
    }
    shuffle($output);

    //echo implode('',$output);
    //return 'lseiyvoexisvazkm';
    /*
      ejtc
      tean
      snlu
      orep
    */

    //echo $this->inGameString('eelp','ejtcteansnluorep') == true ? 'Y' : 'N';
    //return str_split('ejtcteansnluorep');
    if ($explode) {
      //return str_split('ejtcteansnluore#');
      return $output;

    } else {
      //return 'ejtcteansnluore#';
      return implode("", $output);

    }
  }
  public function fromHash($string) {
    return str_replace("#", "ch", $string);
  }
  public function toHash($string) {
    return str_replace("ch", "#", $string);
  }
  public function getGame($iduser, $idGame) {
    $numSingle = dibi::fetchSingle('SELECT COUNT(*) FROM game g
                              JOIN game_user gu1 ON gu1.idgame = g.id
                              LEFT JOIN game_user gu2 ON gu2.idgame = g.id AND gu2.iduser != gu1.iduser
                              WHERE gu1.iduser = %i
                                AND gu2.iduser IS NULL', $iduser);
    if ($numSingle >= 3) {
      $forceNew = false;
    } else {
      $forceNew = (rand(1, 5) == 1);
    }
    $row = null;
    if (!$forceNew) {
      if ($idGame) {
        $row = dibi::fetch('SELECT g.id, g.gamestring FROM game g WHERE g.id = %i', $idGame);
        $rowGU = dibi::fetch('SELECT * FROM game_user gu WHERE %and', array(
          array('gu.iduser = %i', $iduser),
          array('gu.idgame = %i', $idGame)
        ));
        if ($rowGU) {
          $row = null;
        }
      }
      if (!$row) {
        $row = dibi::fetch('SELECT g.id, g.gamestring FROM game g
                              WHERE NOT EXISTS (SELECT * FROM game_user gu2 WHERE gu2.iduser = %i AND gu2.idgame = g.id) AND g.lang = ' . LANG . '
                              ORDER BY g.id DESC', $iduser);
      }
    }
    if (!$forceNew && $row) {
      $idgame = $row->id;
      $gs = $row->gamestring;
      $users = dibi::fetchAll(
        "SELECT (TIMESTAMPDIFF(SECOND, gu.start, NOW()) < 180 AND (gu.finish IS NULL)) AS online, gu.*, u.* FROM game_user gu
          JOIN user u ON gu.iduser = u.id
          WHERE gu.idgame = %i ORDER BY name", $idgame);
      $isNew = count($users) == 0;
    } else {
      $counter = 0;
      $continue = true;
      do {
        $counter++;
        $gs = $this->createGameString(false);
        try {
          dibi::query("INSERT INTO game",
                      array('gamestring' => $gs,
                            'createdat%sql' => 'NOW()',
                            'lang' => LANG));
          $continue = false;
        } catch (Exception $e) {
          $this->log('GameException' . $gs . $e->getMessage());
          null;
        }
      } while ($continue && $counter < 100);
      if ($continue) {
        $this->log('GameException!!!' . $e->getMessage());
        throw new Exception('Game not created.');
      }
      $idgame = dibi::insertid();
      $isNew = true;
      $users = array();
    }

    dibi::query("INSERT INTO game_user",
                array('idgame' => $idgame,
                      'iduser' => $iduser,
                      'start%sql' => 'NOW()'));
    $idgameuser = dibi::insertid();
    foreach (str_split($gs) as $key => $val) {
      $gameGrid[$key] = array("letter" => $this->fromHash($val),
                              "variants" => (isset($this->variants[$val]) && CZ) ? array_merge(array(), $this->variants[$val]) : array());
    }
    return array(
      'idGameUser' => $idgameuser,
      'gameString' => $gs,
      'gameGrid' => $gameGrid,
      'isNew' => $isNew,
      'users' => $users
    );
  }
  public function getGameWords($idGameUser) {
    return dibi::fetchAll('SELECT *, IF(w.status IN ("I", "MI", "E"),0,1) * (char_length(replace(w.text, "ch", "#")) - ' . DIFF . ') ' . ($this->isAll() ? '' : '* CASE
            (SELECT COUNT(*)-1 FROM game_user_word guw2
                            JOIN game_user gu2 ON guw2.idgameuser = gu2.id WHERE guw2.idword = guw.idword AND idgame = gu.idgame) WHEN 0 THEN 1 ELSE 0 END ') .
            ' AS points, (SELECT COUNT(*)-1 FROM game_user_word guw2
                JOIN game_user gu2 ON guw2.idgameuser = gu2.id WHERE guw2.idword = guw.idword AND idgame = gu.idgame) AS numusers
              FROM game_user_word guw
                                  JOIN game_user gu ON guw.idgameuser = gu.id
                                  JOIN word w ON w.id = guw.idword
                                  WHERE guw.idgameuser = %i
                                  ORDER BY guw.id', $idGameUser);
  }
  public function getGameWord($idGameUserWord) {
    return dibi::fetch('SELECT *, IF(w.lang = ' . LANG . ' ,1,0) * IF(w.status IN ("I", "MI", "E"),0,1) * (char_length(replace(w.text, "ch", "#")) - ' . DIFF . ') ' . ($this->isAll() ? '' : '* CASE
            (SELECT COUNT(*)-1 FROM game_user_word guw2
                            JOIN game_user gu2 ON guw2.idgameuser = gu2.id WHERE guw2.idword = guw.idword AND idgame = gu.idgame) WHEN 0 THEN 1 ELSE 0 END') .
            ' AS points, (SELECT COUNT(*)-1 FROM game_user_word guw2
                JOIN game_user gu2 ON guw2.idgameuser = gu2.id WHERE guw2.idword = guw.idword AND idgame = gu.idgame) AS numusers
              FROM game_user_word guw
                                  JOIN game_user gu ON guw.idgameuser = gu.id
                                  JOIN word w ON w.id = guw.idword
                                  WHERE guw.id = %i', $idGameUserWord);
  }
  public function getMy($idUser) {
    $games = dibi::fetchAll("SELECT gu.* FROM game_user gu JOIN game g ON g.id = gu.idgame WHERE g.lang = " . LANG . " AND iduser = %i ORDER BY START DESC LIMIT 20", $idUser);
    $ret = array();
    $ret["games"] = array();
    foreach ($games as $key => $val) {
      $ret["games"][] = $this->getResult($val["id"]);
    }
    $ret["numgames"] = dibi::fetchSingle("SELECT COUNT(*) FROM game_user gu JOIN game g ON g.id = gu.idgame WHERE gu.iduser = %i AND g.lang = " . LANG, $idUser);
    //print_r($ret);
    //die();
    return $ret;
  }
  public function getUserRec($idUser) {
    $rec = dibi::fetch('SELECT * FROM user u WHERE u.id = %i', $idUser);
    return $rec;
  }
  public function setFbId($idUser, $fbId) {
    dibi::query('UPDATE user SET ', array("facebookid" => $fbId), 'WHERE `id` = %i', $idUser);
  }
  public function setMobile($status, $idUser) {
    dibi::query('UPDATE user SET ', array("isMobile" => $status), 'WHERE `id` = %i', $idUser);
  }
  public function setAll($status, $idUser) {
    dibi::query('UPDATE user SET ', array("isAll" => $status), 'WHERE `id` = %i', $idUser);
  }
  public function setWord($id, $status) {
    dibi::query('UPDATE word SET ', array("status" => $status), 'WHERE `id` = %i', $id);
  }
  public function getList($idUser) {
    $games = dibi::fetchAll('SELECT gu.iduser, u.name, SUM(gu.points) as points, COUNT(1) as num, ROUND(AVG(gu.points), 2) as avgpoints
FROM game_user gu JOIN user u ON u.id = gu.iduser
 JOIN game g ON g.id = gu.idgame
WHERE g.lang = ' . LANG . ' AND IFNULL(u.isanonymous, 0) != 1 and (SELECT COUNT(1) FROM game_user gu2
WHERE gu2.idgame = gu.idgame) >= 3
GROUP BY gu.iduser order by avgpoints desc');

    return $games;
  }
  public function getJoin($idUser, $offset = 0) {
    $perPage = 10;
    $totalQuery = dibi::fetchAll('SELECT COUNT(1) FROM
(SELECT g.id, g.gamestring, MAX(gu.id) AS idgameuser,
(GROUP_CONCAT(CASE u.isanonymous WHEN 1 THEN null ELSE gu.iduser END ORDER BY gu.iduser SEPARATOR \'|\')) AS users
FROM game g JOIN game_user gu ON g.id = gu.idgame
  JOIN user u ON gu.iduser = u.id
WHERE g.lang = ' . LANG . ' AND NOT EXISTS (SELECT * FROM game_user gu2 WHERE gu2.iduser = %i
AND gu2.idgame = g.id)
GROUP BY g.id)
gr GROUP BY users', $idUser);
    $total = count($totalQuery);
    $games = dibi::fetchAll("SELECT MAX(id) AS id, MAX(idgameuser) AS idgameuser, users FROM
(SELECT g.id, g.gamestring, MAX(gu.id) AS idgameuser,
(GROUP_CONCAT(CASE u.isanonymous WHEN 1 THEN null ELSE gu.iduser END ORDER BY gu.iduser SEPARATOR '|')) AS users
FROM game g JOIN game_user gu ON g.id = gu.idgame
  JOIN user u ON gu.iduser = u.id
WHERE g.lang = " . LANG . " AND NOT EXISTS (SELECT * FROM game_user gu2 WHERE gu2.iduser = $idUser
AND gu2.idgame = g.id)
GROUP BY g.id)
gr GROUP BY users ORDER BY idgameuser DESC LIMIT $perPage OFFSET %i", $offset);
    $ret = array();
    $ret["games"] = array();
    foreach ($games as $key => $val) {
      $ret["games"][] = $this->getResult($val["idgameuser"]);
    }
    $ret["total"] = $total;
    $ret["offset"] = $offset;
    $ret["prev"] = -1;
    if ($offset > 0) {
      $ret["prev"] = max(0, $offset - $perPage);
    }
    $ret["next"] = -1;
    if ($offset + $perPage < $total) {
      $ret["next"] = $offset + $perPage;
    }
    $ret["offset"] = $offset;

    //print_r($ret);
    //die();
    return $ret;
  }
  public function getStats() {
    $ret["users"] = dibi::fetchAll("SELECT * FROM user WHERE IFNULL(isAnonymous, 0) = 0 ORDER BY id DESC");
    $ret["last"] = dibi::fetchAll("SELECT COUNT(*) AS num, MAX(gu.start) AS start, u.name FROM game_user gu JOIN user u ON u.id = gu.iduser WHERE DATE(gu.start) >= (CURDATE() - INTERVAL 7 DAY) GROUP BY gu.iduser, DATE(gu.start) ORDER BY MAX(gu.start) DESC");
    $ret["invalid"] = dibi::fetchSingle("SELECT COUNT(*) FROM word WHERE status = 'E'");
    $ret["numusers"] = dibi::fetchSingle("SELECT COUNT(*) FROM user WHERE IFNULL(isanonymous, 0) != 1");
    $ret["numgames"] = dibi::fetchSingle("SELECT COUNT(*) FROM game_user gu JOIN game g ON g.id = gu.idgame WHERE g.lang = " . LANG) + (CZ ? $this->getParam('GAMES_DELETED') : 0);
    return $ret;
  }

  public function getUserBySecret($secret) {
    $row = dibi::fetch("SELECT * FROM user WHERE secret = %s", $secret);
    return $row;
  }

  public function isAll()
  {
    return dibi::fetchSingle('SELECT isAll FROM user WHERE id = %i', $this->userId);
  }
  public function getPointsCol()
  {
    return $this->isAll() ? 'allpoints' : 'points';
  }
  public function getResult($idGameUser, $save = null) {
    $gameRow = dibi::fetch('SELECT * FROM game_user WHERE id = %i', $idGameUser);
    $pointsCol = $this->getPointsCol($this->userId);

    $gs = dibi::fetchSingle('SELECT gamestring FROM game WHERE id = %i', $gameRow["idgame"]);
    $users = dibi::fetchAll('SELECT gu.id AS idgameuser, u.id AS iduser, u.name, u.isanonymous,
    IFNULL(SUM((TIMESTAMPDIFF(SECOND, gu.start, guw.createdat) < 300) * IF(w.status IN ("I", "MI", "E"),0,1) * (char_length(replace(w.text, "ch", "#")) - ' . DIFF . ') * CASE (
            (SELECT COUNT(*)-1 FROM game_user_word guw2
                            JOIN game_user gu2 ON guw2.idgameuser = gu2.id WHERE guw2.idword = guw.idword AND idgame = gu.idgame)) WHEN 0 THEN 1 ELSE 0 END), 0)
            AS points,
    IFNULL(SUM((TIMESTAMPDIFF(SECOND, gu.start, guw.createdat) < 300) * IF(w.status IN ("I", "MI", "E"),0,1) * (char_length(replace(w.text, "ch", "#")) - ' . DIFF . ')), 0)
            AS allpoints,
            (TIMESTAMPDIFF(SECOND, gu.start, NOW()) < 180 AND (gu.finish IS NULL)) AS online
              FROM game_user gu
                                  LEFT JOIN game_user_word guw ON guw.idgameuser = gu.id
                                  LEFT JOIN word w ON w.id = guw.idword
                                  JOIN user u ON gu.iduser = u.id
                                  WHERE gu.idgame = %i
                                  GROUP BY gu.id
                                  ORDER BY ' . $pointsCol . ' DESC, idgameuser DESC', $gameRow["idgame"]);
    $data["list"] = array();
    $order = 1;
    $names = array();
    foreach ($users as &$row) {
      $points = $row["allpoints"];
      $words = $this->getGameWords($row["idgameuser"]);
      $row["points"] = $row[$pointsCol];
      $data["list"][] = array('user' => $row,
                      'words' => $words,
                      'isMe' => $row["idgameuser"] == $idGameUser);
      if ($row["idgameuser"] == $idGameUser) {
        $data["order"] = $order;
        $data["points"] = $row[$pointsCol];
      }
      $names[] = $row["name"];
      $order++;
      if ($save == 1) {
        dibi::query('UPDATE game_user SET ', array("points" => $points), 'WHERE `id` = %i', $row["idgameuser"]);
  }
    }

    $data["gs"] = str_split($gs);
    $data["idgame"] = $gameRow["idgame"];
    $data["idgameuser"] = $idGameUser;
    $data["start"] = $gameRow["start"];
    $data["numusers"] = count($users);
    $data["usernames"] = implode(", ", $names);
    foreach ($data["gs"] as &$val) {
      $val = $this->fromHash($val);
    }
    return $data;
  }

  public function getVote($idGameUser) {
    $result = array();
    $gameRow = dibi::fetch('SELECT * FROM game_user WHERE id = %i', $idGameUser);
    $result["list"] = dibi::fetchAll('SELECT w.id AS idword, CASE WHEN w.status IN ("I", "MI", "E") THEN "I" ELSE "C" END AS status, w.text, w.context,
                IFNULL(v.value, "X") AS voted
              FROM game_user gu
              JOIN game_user_word guw ON guw.idgameuser = gu.id
              JOIN word w ON w.id = guw.idword
              LEFT JOIN vote v ON v.idword = w.id AND v.iduser = '
                . $gameRow["iduser"] . '
              WHERE gu.idgame = %i
              AND w.status != "D"
              GROUP BY w.id
              ORDER BY status desc, w.text', $gameRow["idgame"]);
    return $result;
  }

  public function doVote($idUser, $idWord, $value) {
    if (in_array($value, array('Y', 'N'))) {
      try {
        dibi::query("INSERT INTO vote", array('iduser' => $idUser,
                                              'idword' => $idWord,
                                              'value' => $value));
      } catch (Exception $e) {
        dibi::query('UPDATE vote SET ', array("value" => $value), 'WHERE %and',
          array('iduser' => $idUser,
                'idword' => $idWord)
        );
      }
      $current = dibi::fetchSingle('SELECT status FROM word WHERE id = %i', $idWord);
      $pos = dibi::fetchSingle('SELECT COUNT(*) FROM vote WHERE idword = %i AND value = "Y"', $idWord);
      $neg = dibi::fetchSingle('SELECT COUNT(*) FROM vote WHERE idword = %i AND value = "N"', $idWord);
      if (($pos + $neg >= 1) && ($current != 'D')) {
        if ($pos > $neg) {
          $newStatus = 'MC';
        } elseif ($pos < $neg) {
          $newStatus = 'MI';
        }
        if ($pos != $neg) {
          dibi::query('UPDATE word SET ', array("status" => $newStatus), 'WHERE id = %i', $idWord);
        }
      }
    }
  }

  public function fillFound() {
     $words = dibi::fetchAll(
        "SELECT * FROM `word` WHERE `status` = 'C' and found IS NULL");
     foreach ($words as $word) {
      preg_match("~výskytů z  ([0-9]+)~", $word["context"], $matches);
      if (isset($matches[1])) {
        $found = $matches[1];
      }
      dibi::query('UPDATE word SET ', array("found" => $found), 'WHERE `id` = %i', $word["id"]);
     }
  }
  /*
   * C - confirmed
   * I - invalid
   * N - new
   * E - error
   * W - wrong (short, double,...)
   * D - dictionary
   * MI - manually invalid
   * MC - manually confirmed
   */
  public function checkWord($idWord, $forceCheck = false) {
    $found = null;
    $row = dibi::fetch('SELECT status, text FROM word WHERE id = %i', $idWord);
    $word = $row["text"];
    if (($row["status"] != "N" || EN) && !$forceCheck) {
      return;
    }
    $url = "http://ucnk.ff.cuni.cz/verejny.php";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "query=" . urlencode(iconv('utf-8', 'iso-8859-2', $word)) . "&submit=Hledej");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    $output = curl_exec($ch);
    curl_close($ch);
    //echo $output;
    //echo "tu2";
    //$output = iconv('iso-8859-2', 'utf-8', $output);
    //echo $output;
    if (mb_strpos($output, 'Nenalezeno nebo nesprávně zadaný dotaz.') !== false) {
      $status = 'I';
      $output = "";
    } elseif (mb_strpos($output, $word) !== false) {
      $status = 'C';
      $index = strpos($output, '<TABLE BORDER=0 CELLPADDING=0 CELLSPACING=5>');
      $tmpOut = substr($output, $index);
      $index2 = strpos($tmpOut, 'celkových.<BR>');
      $tmpOut = substr($tmpOut, 0, $index2 + 15);
      if (mb_strpos($tmpOut, $word) !== false) {
        $output = $tmpOut;
        preg_match("~výskytů z  ([0-9]+)~", $output, $matches);
        if (isset($matches[1])) {
          $found = $matches[1];
          if ($found == 1) {
            $status = 'I';
          }
        }
      }
    } else {
      $status = 'E';
    }
    //echo "tu3";
    //die($status);
    dibi::query('UPDATE word SET ', array("context" => $output,
                                          "status" => $status,
                                          "found" => $found), 'WHERE `id` = %i', $idWord);
    //'<TABLE BORDER=0 CELLPADDING=0 CELLSPACING=5>'
    //die();
    return $status;
  }

  public function evalGame($gameInfo, $string, $isAjax = false) {
    $words = explode(';', mb_strtolower($string));
    $words = array_unique($words);
    $data = array();
    //print_r($words);
    $points = 0;
    $gameString = $gameInfo['gameString'];
    $idGameUser = $gameInfo['idGameUser'];
    //echo $gameString . '<br />';
    if (!$isAjax) {
      dibi::query('UPDATE game_user SET ', array("answer" => trim($string, ';'),
                                                 'finish%sql' => 'NOW()'), 'WHERE `id`=%i', $idGameUser);
    }
    if (count($words) > 100) {
      return;
    }
    foreach ($words as $word) {
      //echo $word . '<br />';
      $word = trim($word);
      $wordA = preg_split('~(?<!^)(?!$)~u', $this->toHash($word));
      if (count($wordA) > DIFF && count($wordA) <= 16) {
        if ($this->inGameString($wordA, $gameString)) {
            $row = dibi::fetch('SELECT * FROM word WHERE lang = ' . LANG . ' AND text=%s COLLATE utf8_bin', $this->fromHash(implode('', $wordA)));
            if ($row) {
              $idword = $row["id"];
              $isNew = false;
            } else {
              dibi::query("INSERT INTO word", array('text' => $word, 'lang' => LANG, 'status' => (CZ ? 'N' : 'I')));
              $idword = dibi::insertid();
              $isNew = true;
            }
            try {
              dibi::query("INSERT INTO game_user_word",
                    array('idgameuser' => $idGameUser,
                          'idword' => $idword,
                          'createdat%sql' => 'NOW()'));
              $idguw = dibi::insertid();
            } catch (Exception $e) {
              if ($isAjax) {
                return array("text" => $this->fromHash(implode("", $wordA)),
                       "numusers" => 0,
                       "points" => 0,
                       "status" => "W");
              }
            }
            if ($isNew) {
              $this->checkWord($idword);
            }
            if ($isAjax) {
              return $this->getGameWord($idguw);
            }
        } else {
          if ($isAjax) {
            return array("text" => $this->fromHash(implode("", $wordA)),
                       "numusers" => 0,
                       "points" => 0,
                       "status" => "W");
          }
        }
      } else {
        if ($isAjax) {
          return array("text" => $this->fromHash(implode("", $wordA)),
                       "numusers" => 0,
                       "points" => 0,
                       "status" => "W");
        }
      }
    }
    return $data;
  }

  public function calculatePoints() {
    $rows = dibi::fetchAll(
        "SELECT MAX(gu.id) AS id, g.id AS idgame FROM game g join game_user gu ON g.id = gu.idgame WHERE g.recalculated IS NULL GROUP BY g.id");
     foreach ($rows as $row) {
      $this->getResult($row["id"], 1);
      dibi::query('UPDATE game SET ', array("recalculated" => 1), 'WHERE `id`=%i', $row["idgame"]);
     }
  }
  public function setMsg($values) {
    dibi::query("INSERT INTO msg",
                    array('email' => $values["email"],
                          'text' => $values["text"],
                          'createdat%sql' => 'NOW()'));
    $mail = new NMail;
    $mail->setFrom($values["email"])
    ->addTo('slamisj@seznam.cz')
    ->setSubject('Boggle - vzkaz')
    ->setBody($values["text"])
    ->send();
  }
}
