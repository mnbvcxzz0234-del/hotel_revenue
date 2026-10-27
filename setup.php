<?php
// Run once, then DELETE this file. Set SETUP_PASS in Render env vars first.
require_once 'config.php';
$pass = getenv('SETUP_PASS');
if (!$pass) { die('Set SETUP_PASS env var first.'); }
$db = db();
$st = $db->prepare('INSERT INTO users(username,password_hash) VALUES(?,?) ON CONFLICT (username) DO UPDATE SET password_hash=EXCLUDED.password_hash');
$st->execute(['admin', password_hash($pass, PASSWORD_DEFAULT)]);
echo 'admin created. Now delete setup.php and remove SETUP_PASS.';
