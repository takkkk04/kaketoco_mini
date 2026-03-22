<?php

session_start();

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? currentUserId() : null;
$userName = $isLoggedIn ? currentUserName() : "";
