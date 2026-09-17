<?php
require __DIR__ . '/lib/Auth.php';
Auth::logout();
header('Location: login.php');
exit;
