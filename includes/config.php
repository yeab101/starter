<?php
// ---------------------------------------------------------------
// Database settings (XAMPP defaults: user "root", empty password)
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');   // not "localhost": on Windows that tries IPv6 first and adds ~2 s per page
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'srms');   // must match the name used in database.sql
// 3306 is the XAMPP default; the portable version sets SRMS_DB_PORT to its own port
define('DB_PORT', (int) (getenv('SRMS_DB_PORT') ?: 3306));
// Database viewer shown to administrators: the portable version's own phpMyAdmin, else XAMPP's
define('PMA_URL', getenv('SRMS_PMA_URL') !== false ? (string) getenv('SRMS_PMA_URL') : 'http://localhost/phpmyadmin/');

// First account created by install.php (change the password after first login)
define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'admin123');

// Grading rules
define('PASS_MARK', 50);     // marks at or above this are a Pass

const PROGRAMS = ['Regular', 'Extension', 'Weekend', 'Distance'];
const ACADEMIC_YEARS = ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5'];

date_default_timezone_set('Africa/Addis_Ababa');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Shared database connection. */
function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $ex) {
            http_response_code(500);
            die('Could not connect to the database. Start MySQL in the XAMPP Control Panel '
                . 'and open <a href="install.php">install.php</a> if you have not set up the database yet. '
                . '(' . htmlspecialchars($ex->getMessage()) . ')');
        }
    }
    return $conn;
}

/** Escape text for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Set a one-time message, or read (and clear) it when called with no arguments. */
function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [$message, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** CSRF protection for every form that changes data. */
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid or expired form. Go back, reload the page and try again.');
    }
}

/** Send visitors who are not logged in to the login page. */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

/** Pages only administrators may open (user management). */
function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        flash('Only administrators can open that page.', 'error');
        redirect('dashboard.php');
    }
}

/**
 * Letter grade and Pass/Fail for a mark out of 100.
 * Change the boundaries here if your college uses a different scale.
 */
function calc_grade(float $mark): array
{
    if ($mark >= 85)      $grade = 'A';
    elseif ($mark >= 70)  $grade = 'B';
    elseif ($mark >= 60)  $grade = 'C';
    elseif ($mark >= 50)  $grade = 'D';
    else                  $grade = 'F';

    return [$grade, $mark >= PASS_MARK ? 'Pass' : 'Fail'];
}

/** Grade points on a 4.0 scale, used for the GPA on the report. */
function grade_points(string $grade): int
{
    return ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1][$grade] ?? 0;
}

/** Navigation link that is highlighted on its own pages. */
function nav_link(string $file, string $label, array $alsoActiveOn = []): string
{
    $page = basename($_SERVER['PHP_SELF']);
    $active = in_array($page, array_merge([$file], $alsoActiveOn), true) ? ' class="active"' : '';
    return '<a href="' . $file . '"' . $active . '>' . $label . '</a>';
}
