<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) { unset($_SESSION['cid']); session_regenerate_id(true); }
header('Location: book.php');
