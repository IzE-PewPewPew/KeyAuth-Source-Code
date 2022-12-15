<?php

//error_reporting(0); // disable useless warnings, should turn this on if you need to debug a problem

/* Attempt MySQL server connection. Assuming you are running MySQL

server with default setting (user 'root' with no password) */

$link = mysqli_connect("localhost", "root", "", "main");

// Check connection status

if ($link === false) {
    http_response_code(503); // produce non-200 HTTP code so UptimeRobot notifies me MySQL is down
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// set character set to ensure greek characters don't cause issues
mysqli_query($link, "SET NAMES 'utf8'");

$logwebhook = "https://discord.com/api/webhooks/1052932112489136188/6mbeyNrBu9ko4tGIexHGWsVH-n4OJvnH9JdWFy-D0nKyIY7GzJAn6IlzokCdfn9C7Zlg"; // discord webhook which receives login logs and keys created

$adminwebhook = "https://discord.com/api/webhooks/1052932112489136188/6mbeyNrBu9ko4tGIexHGWsVH-n4OJvnH9JdWFy-D0nKyIY7GzJAn6IlzokCdfn9C7Zlg"; // discord webhook which receives admin actions

$webhookun = "KeyAuth Logs"; // webhook username

$adminwebhookun = "KeyAuth Admin Logs"; // admin webhook's username


$adminapikey = ""; // api key for api/admin (an api only my staff can use)

$proxycheckapikey = ""; // proxycheck.io API key to check if IP is considered a VPN

$bunnyNetKey = ""; // bunny.net CDN used for custom domains
