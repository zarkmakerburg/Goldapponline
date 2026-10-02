<?php
require_once __DIR__ . '/inc/config.php';

destroy_admin_session();
header('Location: login.php');
exit;
