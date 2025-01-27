<?php

namespace misc\etc;

function sanitize($input)
{
    if (empty($input) & !is_numeric($input)) {
        return NULL;
    }
    $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
    $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
    return str_replace($search, $replace, strip_tags(trim($input))); // return string with quotes escaped to prevent SQL injection, script tags stripped to prevent XSS attach, and trimmed to remove whitespace
}
function random_string_upper($length = 10, $keyspace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
{ // https://github.com/FinGu/c_auth/blob/cfbd7036e69561e538e26dc47f7690dbc0d8ba53/functions/general/functions.php#L55
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $rand_index = random_int(0, strlen($keyspace) - 1);
        $out .= $keyspace[$rand_index];
    }
    return $out;
}
function random_string_lower($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyz'): string
{ // https://github.com/FinGu/c_auth/blob/cfbd7036e69561e538e26dc47f7690dbc0d8ba53/functions/general/functions.php#L55
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $rand_index = random_int(0, strlen($keyspace) - 1);
        $out .= $keyspace[$rand_index];
    }
    return $out;
}
function formatBytes($bytes, $precision = 2)
{ // https://stackoverflow.com/a/2510459
    $units = array(
        'B',
        'KB',
        'MB',
        'GB',
        'TB'
    );
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    // Uncomment one of the following alternatives
    // $bytes /= pow(1024, $pow);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
function generateRandomString($length = 10)
{ // https://stackoverflow.com/a/4356295
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function generateRandomNum($length = 6)
{ // adapted from https://stackoverflow.com/a/4356295
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function isBreached($pw)
{ // from https://github.com/Mikjaer/haveibeenpwned/blob/main/p0wned.php
    $hash = strtoupper(sha1($pw));
    foreach (explode("\n", file_get_contents("https://api.pwnedpasswords.com/range/" . substr($hash, 0, 5))) as $pmatch)
        if (substr($hash, 0, 5) . substr($pmatch, 0, strpos($pmatch, ":")) == $hash)
            return true;
}
function isPhonyEmail($email)
{
	if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return true;
	}
	
    $resp = file_get_contents("https://api.mailcheck.ai/email/" . $email);
    $json = json_decode($resp);
	
	if($json->disposable) {
		return true;
	}
	
	ini_set("default_socket_timeout", 1);
	$connection = @fsockopen("gmail-smtp-in.l.google.com", 25);
	
	// check if port 25 is open (many hosts have it closed inherently)
	if (is_resource($connection)) {
		global $mail;
		require_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/VerifyEmail.class.php'; 
		
		return (!$mail->check($email));
	}
	else {
	    return false;
	}
}