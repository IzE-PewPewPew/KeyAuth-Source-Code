<?php

namespace api\v1_0;

use api\shared\primary;
use misc\etc;
use misc\cache;
#region enc region
function Encrypt($string, $enckey)
{
    return bin2hex(openssl_encrypt($string, 'aes-256-cbc', substr(hash('sha256', $enckey), 0, 32), OPENSSL_RAW_DATA, substr(hash('sha256', $_POST['init_iv']), 0, 16)));
}
function Decrypt($string, $enckey)
{
    return openssl_decrypt(hex2bin($string), 'aes-256-cbc', substr(hash('sha256', $enckey), 0, 32), OPENSSL_RAW_DATA, substr(hash('sha256', $_POST['init_iv']), 0, 16));
}
#endregion
#region rgstr region
function register($un, $key, $pw, $hwid, $secret)
{
    global $link; // needed to refrence active MySQL connection
    include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
    $result = mysqli_query($link, "SELECT `minUsernameLength`, `blockLeakedPasswords` FROM `apps` WHERE `secret` = '$secret'");
    $row = mysqli_fetch_array($result);
    $minUsernameLength = $row['minUsernameLength'];
    $blockLeakedPasswords = $row['blockLeakedPasswords'];

    if (strlen($un) < $minUsernameLength) {
        return 'un_too_short';
    }

    if ($blockLeakedPasswords && etc\isBreached($pw)) {
        return 'pw_leaked';
    }

    // search username
    $result = mysqli_query($link, "SELECT 1 FROM `users` WHERE `username` = '$un' AND `app` = '$secret'");
    // if username already in existence
    if (mysqli_num_rows($result) >= 1) {
        return 'username_taken';
    }
    // search for key
    $result = mysqli_query($link, "SELECT `expires`, `status`, `level`, `genby` FROM `keys` WHERE `key` = '$key' AND `app` = '$secret'");
    // check if key exists
    if (mysqli_num_rows($result) < 1) {
        return 'key_not_found';
    }
    // if key does exist
    elseif (mysqli_num_rows($result) > 0) {
        // gather key info
        while ($row = mysqli_fetch_array($result)) {
            $expires = $row['expires'];
            $status = $row['status'];
            $level = $row['level'];
            $genby = $row['genby'];
        }
        // check license status
        switch ($status) {
            case 'Used':
                return 'key_already_used';
            case 'Banned':
                return 'key_banned';
        }
        $ip = primary\getIp();
        $hwidBlackCheck = mysqli_query($link, "SELECT 1 FROM `bans` WHERE (`hwid` = '$hwid' OR `ip` = '$ip') AND `app` = '$secret'");
        if (mysqli_num_rows($hwidBlackCheck) > 0) {
            mysqli_query($link, "UPDATE `keys` SET `status` = 'Banned',`banned` = 'This key has been banned as the client was blacklisted.' WHERE `key` = '$un' AND `app` = '$secret'");
			cache\purge('KeyAuthKeys:' . $secret);
            return 'hwid_blacked';
        }
        // add current time to key time
        $expiry = $expires + time();
        $result = mysqli_query($link, "SELECT `name` FROM `subscriptions` WHERE `app` = '$secret' AND `level` = '$level'");
        $num = mysqli_num_rows($result);
        if ($num == 0) {
            return 'no_subs_for_level';
        }
        // update key to used
        mysqli_query($link, "UPDATE `keys` SET `status` = 'Used',`usedon` = '" . time() . "',`usedby` = '$un' WHERE `key` = '$key'");
		cache\purge('KeyAuthKeys:' . $secret);
        while ($row = mysqli_fetch_array($result)) {
            // add each subscription that user's key applies to
            $subname = $row['name'];
            mysqli_query($link, "INSERT INTO `subs` (`user`, `subscription`, `expiry`, `app`, `key`) VALUES ('$un','$subname', '$expiry', '$secret','$key')");
        }
        $password = password_hash($pw, PASSWORD_BCRYPT);
        $createdate = time();
        // create user
        mysqli_query($link, "INSERT INTO `users` (`username`, `password`, `hwid`, `app`,`owner`,`createdate`, `lastlogin`, `ip`) VALUES ('$un','$password', NULLIF('$hwid', ''), '$secret', '$genby', '$createdate', '$createdate', '$ip')");
        $result = mysqli_query($link, "SELECT `subscription`, `key`, `expiry` FROM `subs` WHERE `user` = '$un' AND `app` = '$secret' AND `expiry` > " . time() . "");
        $rows = array();
        while ($r = mysqli_fetch_assoc($result)) {
            $timeleft = $r["expiry"] - time();
            $r += ["timeleft" => $timeleft];
            $rows[] = $r;
        }
        cache\purge('KeyAuthUser:' . $secret . ':' . $un);
		cache\purge('KeyAuthSubs:' . $secret . ':' . $un);
        // success
        return array(
            "username" => "$un",
            "subscriptions" => $rows,
            "ip" => $ip,
            "hwid" => $hwid,
            "createdate" => "$createdate",
            "lastlogin" => "" . time() . ""
        );
    }
}
#endregion
#region login region
function login($un, $pw, $hwid, $secret, $hwidenabled, $token = null)
{
    // Find username
    $row = cache\fetch('KeyAuthUser:' . $secret . ':' . $un, "SELECT * FROM `users` WHERE `username` = '$un' AND `app` = '$secret'", 0);
    // if not found
    if ($row == "not_found") {
        return 'un_not_found';
    }

    // get all rows from username query
    $pass = $row['password'];
    //$expires = $row['expires'];
    $hwidd = $row['hwid'];
    $banned = $row['banned'];
    $createdate = $row['createdate'];
    $lastlogin = $row['lastlogin'];
    if ($banned != NULL) {
        return 'user_banned';
    }
    $ip = primary\getIp();

    $row = cache\fetch('KeyAuthBlacklist:' . $secret . ':' . $ip . ':' . $hwid, "SELECT 1 FROM `bans` WHERE (`hwid` = '$hwid' OR `ip` = '$ip') AND `app` = '$secret'", 0);
    if ($row != "not_found") {
        global $link;
        include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
        mysqli_query($link, "UPDATE `users` SET `banned` = 'User is blacklisted' WHERE `username` = '$un' AND `app` = '$secret'");
        cache\purge('KeyAuthUser:' . $secret . ':' . $un);
        return 'hwid_blacked';
    }

    if (!is_null($token)) {
        $validToken = md5(substr($pass, -5));
        if ($validToken != $token) {
            return 'pw_mismatch';
        }
    } else if (!is_null($pass)) {
        // check if pass matches
        if (!password_verify($pw, $pass)) {
            return 'pw_mismatch';
        }
    } else {
        $pass_encrypted = password_hash($pw, PASSWORD_BCRYPT);
        global $link;
        include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
        mysqli_query($link, "UPDATE `users` SET `password` = '$pass_encrypted' WHERE `username` = '$un' AND `app` = '$secret'");
        cache\purge('KeyAuthUser:' . $secret . ':' . $un);
    }
    // check if hwid enabled for application
    if ($hwidenabled == "1") {
        // check if hwid in db contains hwid recieved
        if ($hwid != NULL && strpos($hwidd, $hwid) === false && $hwidd != NULL) {
            return 'hwid_mismatch';
        } else if ($hwidd == NULL && $hwid != NULL) {
            global $link;
            include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
            mysqli_query($link, "UPDATE `users` SET `hwid` = NULLIF('$hwid', '') WHERE `username` = '$un' AND `app` = '$secret'");
            cache\purge('KeyAuthUser:' . $secret . ':' . $un);
        }
    }
    $rows = cache\fetch('KeyAuthSubs:' . $secret . ':' . $un, "SELECT `subscription`, `key`, `expiry` FROM `subs` WHERE `user` = '$un' AND `app` = '$secret' AND `expiry` > " . time() . "", 1);
    if ($rows == "not_found") {
        global $link;
        include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
        $result = mysqli_query($link, "SELECT `paused` FROM `subs` WHERE `user` = '$un' AND `app` = '$secret' AND `paused` = 1");
        if (mysqli_num_rows($result) >= 1) {
            return 'sub_paused';
        }
        return 'no_active_subs';
    }
	
	$rowsFinal = array();  
	foreach ($rows as $row) {
		$timeleft = $row["expiry"] - time();
		$row += ["timeleft" => $timeleft];
		$rowsFinal[] = $row;
	}

    global $link;
    include_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/connection.php'; // create connection with MySQL
    mysqli_query($link, "UPDATE `users` SET `ip` = NULLIF('$ip', ''),`lastlogin` = " . time() . " WHERE `username` = '$un' AND `app` = '$secret'");

    return array(
        "username" => "$un",
        "subscriptions" => $rowsFinal,
        "ip" => $ip,
        "hwid" => $hwidd,
        "createdate" => $createdate,
        "lastlogin" => "" . time() . ""
    );
}
#endregion