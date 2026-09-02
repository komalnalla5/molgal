<?php
// config.php -- use this same file on local XAMPP and the live website.

/*
|--------------------------------------------------------------------------
| Database connection: local and live automatic
|--------------------------------------------------------------------------
*/
if (!defined('DB_HOST')) {
    $requestHostForEnvironment = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $requestHostForEnvironment = preg_replace('/:\d+$/', '', $requestHostForEnvironment);

    $isLocalEnvironment = PHP_OS_FAMILY === 'Windows'
        || in_array(
            $requestHostForEnvironment,
            ['localhost', '127.0.0.1', '::1'],
            true
        );

    define('DB_HOST', 'localhost');

    if ($isLocalEnvironment) {
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'anmolkamdar_mubyadmin');
    } else {
        define('DB_USER', 'anmolkamdar_muby');
        define('DB_PASS', 'Anmol$$4321$$');
        define('DB_NAME', 'anmolkamdar_mubyadmin');
    }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$conn) {
        die('Database connection failed: ' . mysqli_connect_error());
    }

    mysqli_set_charset($conn, 'utf8mb4');
}

/*
|--------------------------------------------------------------------------
| Automatically detect this website's SITE_ID
|--------------------------------------------------------------------------
|
| Local URL:
| http://localhost/molgal.com/ -> detects molgal.com from the URL folder.
|
| Live URL:
| https://molgal.com/ -> detects molgal.com from HTTP_HOST.
|
*/
if (!defined('SITE_ID')) {
    if (!function_exists('_extract_domain_host')) {
        function _extract_domain_host($domain)
        {
            $domain = trim((string) $domain);

            if (preg_match('#^https?://#i', $domain)) {
                $host = parse_url($domain, PHP_URL_HOST);
            } else {
                $host = parse_url('http://' . ltrim($domain, '/'), PHP_URL_HOST);
            }

            if (empty($host)) {
                $host = trim($domain, "/\\ ");
            }

            $host = strtolower((string) $host);
            return preg_replace('/^www\./i', '', $host);
        }
    }

    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost);
    $requestHost = preg_replace('/^www\./i', '', $requestHost);

    $isLocalRequest = PHP_OS_FAMILY === 'Windows'
        || in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);

    if ($isLocalRequest) {
        $requestPath = (string) parse_url(
            $_SERVER['REQUEST_URI'] ?? '',
            PHP_URL_PATH
        );

        $pathParts = array_values(
            array_filter(
                explode('/', trim($requestPath, '/')),
                static function ($part) {
                    return $part !== '';
                }
            )
        );

        // For http://localhost/molgal.com/blog.php this is molgal.com.
        $localFolderDomain = $pathParts[0] ?? '';

        // CLI/direct fallback: config.php is inside the website root folder.
        if ($localFolderDomain === '' || strpos($localFolderDomain, '.') === false) {
            $localFolderDomain = basename(__DIR__);
        }

        $currentDomain = _extract_domain_host($localFolderDomain);
    } else {
        $currentDomain = _extract_domain_host($requestHost);
    }

    $siteIdFound = 0;
    $siteQuery = mysqli_query(
        $conn,
        "SELECT id, domain
         FROM oursites
         WHERE status = 'active'
           AND deleted_at IS NULL"
    );

    if ($siteQuery === false) {
        die('Website lookup failed: ' . mysqli_error($conn));
    }

    while ($siteRow = mysqli_fetch_assoc($siteQuery)) {
        if (_extract_domain_host($siteRow['domain']) === $currentDomain) {
            $siteIdFound = (int) $siteRow['id'];
            break;
        }
    }

    mysqli_free_result($siteQuery);

    if ($siteIdFound <= 0) {
        die(
            'Website setup error: no active website in the oursites table ' .
            'matches the detected domain (' .
            htmlspecialchars($currentDomain, ENT_QUOTES, 'UTF-8') .
            ').'
        );
    }

    define('SITE_ID', $siteIdFound);
}

/*
|--------------------------------------------------------------------------
| Admin image URLs: local and live automatic
|--------------------------------------------------------------------------
*/
if (!defined('ADMIN_BASE_URL')) {
    $adminUrlHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $adminUrlHost = preg_replace('/:\d+$/', '', $adminUrlHost);

    $useLocalAdminUrl = PHP_OS_FAMILY === 'Windows'
        || in_array($adminUrlHost, ['localhost', '127.0.0.1', '::1'], true);

    define(
        'ADMIN_BASE_URL',
        $useLocalAdminUrl
            ? 'http://localhost/mubyadmin/'
            : 'https://mubyadmin.com/'
    );
}

if (!defined('CERTIFICATE_BASE_URL')) {
    define(
        'CERTIFICATE_BASE_URL',
        rtrim(ADMIN_BASE_URL, '/') . '/uploads/'
    );
}
