<?php
require_once 'config.php';
Auth::logout();
header('Location: login.php');
exit;
