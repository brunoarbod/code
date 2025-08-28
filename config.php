<?php
//--- config.php (mise à jour) -------------------------------------------------
//--- debut du bloc 1 : Boot & options globales ---
declare(strict_types=1);

// (optionnel) activer/désactiver le debug applicatif
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

// Timezone & encodage
@date_default_timezone_set('Europe/Paris');
if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }

// Petite helper pour lire une variable d'environnement avec fallback
if (!function_exists('env')) {
  function env(string $key, $default=null) {
    $v = getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
  }
}
//--- fin du bloc 1 ------------------------------------------------------------


//--- debut du bloc 2 : Config BDD + accès PDO (db()) ---
$DB_HOST = env('DB_HOST', 'OOO.mysql.db');
$DB_NAME = env('DB_NAME', 'labase');
$DB_USER = env('DB_USER', 'User1');
$DB_PASS = env('DB_PASS', 'Jeveuxumot de passe'); // ⚠️ pense à mettre ça en variable d’env en prod

// DSN MySQL
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

// Options PDO
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

/**
 * Retourne un singleton PDO.
 * Utilise la config ci-dessus, lazy-init au premier appel.
 */
if (!function_exists('db')) {
  function db(): PDO {
    static $pdo = null;
    global $dsn, $DB_USER, $DB_PASS, $options;
    if ($pdo instanceof PDO) return $pdo;
    try {
      $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
      return $pdo;
    } catch (PDOException $e) {
      http_response_code(500);
      if (APP_DEBUG) {
        exit('Erreur de connexion BDD : '.$e->getMessage());
      }
      exit('Erreur de connexion BDD');
    }
  }
}
//--- fin du bloc 2 ------------------------------------------------------------


//--- debut du bloc 3 : Session PHP ---
if (session_status() === PHP_SESSION_NONE) {
  // Cookies de session un peu plus stricts (sans casser le local)
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  @session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}
//--- fin du bloc 3 ------------------------------------------------------------


//--- debut du bloc 4 : Fichiers de données & constantes ---
if (!defined('GAME_FILE'))  define('GAME_FILE',  __DIR__.'/game_state.json'); // état de partie (tour/phase/pioche…)
if (!defined('DATA_FILE'))  define('DATA_FILE',  __DIR__.'/plateau.json');    // plateau (tuiles/features/scores…)
if (!defined('LOBBY_FILE')) define('LOBBY_FILE', __DIR__.'/lobby_state.json');// lobby (hosts/ready/version)

if (!defined('TURN_LOCK_TTL'))   define('TURN_LOCK_TTL', 90); // durée d’un lock de tour (secondes)
if (!defined('LOBBY_PING_TTL'))  define('LOBBY_PING_TTL', 20); // indicatif côté UI

// Flags JSON par défaut
if (!defined('JSON_FLAGS_SAVE')) define('JSON_FLAGS_SAVE', JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
if (!defined('JSON_FLAGS_READ')) define('JSON_FLAGS_READ', 0);
//--- fin du bloc 4 ------------------------------------------------------------


//--- debut du bloc 5 : Helpers JSON (lecture/écriture atomique) ---
if (!function_exists('json_read')) {
  /**
   * Lit un fichier JSON et retourne un tableau/valeur, sinon $default.
   */
  function json_read(string $file, $default=null) {
    if (!is_file($file)) return $default;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : $default;
  }
}

if (!function_exists('json_write_atomic')) {
  /**
   * Écrit un JSON de manière (quasi) atomique : tmp + rename.
   * Retourne true/false.
   */
  function json_write_atomic(string $file, $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0775, true) && !is_dir($dir)) return false;
    }
    $json = json_encode($data, JSON_FLAGS_SAVE);
    if ($json === false) return false;

    $tmp = @tempnam($dir, 'tmpjson_');
    if (!$tmp) {
      // fallback simple
      return @file_put_contents($file, $json, LOCK_EX) !== false;
    }
    $ok = (@file_put_contents($tmp, $json) !== false);
    if (!$ok) { @unlink($tmp); return false; }

    // Sur Windows, rename peut échouer si le fichier existe => tentative d’unlink.
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && is_file($file)) { @unlink($file); }

    $ok = @rename($tmp, $file);
    if (!$ok) {
      // fallback
      @unlink($tmp);
      return @file_put_contents($file, $json, LOCK_EX) !== false;
    }
    @chmod($file, 0664);
    return true;
  }
}
//--- fin du bloc 5 ------------------------------------------------------------


//--- debut du bloc 6 : Helpers divers (uuidv4 de secours, sécurité) ---
if (!function_exists('uuidv4')) {
  /**
   * Génère un UUID v4. (Secours si non fourni ailleurs, ex: auth.php)
   */
  function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); // version 4
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80); // variant RFC 4122
    $hex = bin2hex($d);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
  }
}

/**
 * Petit helper pour répondre JSON depuis n’importe où (optionnel).
 * Utilisé par certains endpoints si besoin.
 */
if (!function_exists('json_respond')) {
  function json_respond(array $payload, int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }
}
//--- fin du bloc 6 ------------------------------------------------------------


//--- debut du bloc 7 : (optionnel) Entêtes anti-cache pour endpoints JSON ---
// À activer dans les endpoints si nécessaire, pas ici globalement.
// header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// header('Pragma: no-cache');
//--- fin du bloc 7 ------------------------------------------------------------

