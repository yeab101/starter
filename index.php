<?php
require __DIR__ . '/includes/config.php';
redirect(empty($_SESSION['user_id']) ? 'login.php' : 'dashboard.php');
