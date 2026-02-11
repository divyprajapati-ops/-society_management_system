<?php

require_once __DIR__ . '/backend/config/auth.php';

if (!is_logged_in()) {
    header('Location: backend/auth/login.php');
    exit;
}

redirect_by_role();
