<?php
/**
 * Keluar dari panel admin.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];

// Hapus juga cookie session di sisi browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

session_destroy();

redirect('admin/login.php');
