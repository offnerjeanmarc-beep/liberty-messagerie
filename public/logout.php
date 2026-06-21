<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
auth_start();
auth_logout();
header('Location: login.php');
exit;
