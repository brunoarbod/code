<?php
// ---debut du bloc A1 : Boot & dépendances ---
declare(strict_types=1);
require_once __DIR__.'/config.php'; // db(), uuidv4(), session
// ---fin du bloc A1 ---


// ---debut du bloc A2 : Constantes de routes (ajuste si besoin) ---
if (!defined('LOGIN_URL'))      define('LOGIN_URL', 'connexion.php'); // ta page de connexion
if (!defined('AFTER_LOGIN_URL')) define('AFTER_LOGIN_URL', 'lobby.php');
// ---fin du bloc A2 ---


// ---debut du bloc A3 : Helpers session/auth de base ---
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
  }
}

if (!function_exists('current_user_id')) {
  function current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
  }
}

/**
 * Redirige vers la page de login si non connecté.
 * Utilise un fallback HTML si des headers ont déjà été envoyés (évite un 500).
 */
if (!function_exists('require_login')) {
  function require_login(): void {
    if (is_logged_in()) return;

    $redirect = $_SERVER['REQUEST_URI'] ?? AFTER_LOGIN_URL;
    $target = LOGIN_URL.(strpos(LOGIN_URL,'?')===false ? '?' : '&').'redirect='.rawurlencode($redirect);

    if (!headers_sent()) {
      header('Location: '.$target, true, 302);
    } else {
      // Fallback si des headers ont déjà été envoyés (ex. écho accidentel)
      echo '<!doctype html><meta http-equiv="refresh" content="0;url='.htmlspecialchars($target,ENT_QUOTES).'">';
      echo '<p>Redirection… <a href="'.htmlspecialchars($target,ENT_QUOTES).'">Clique ici</a></p>';
    }
    exit;
  }
}
// ---fin du bloc A3 ---


// ---debut du bloc A4 : Bridge "player" (facultatif, mais pratique) ---
/**
 * get_player_for_user(PDO $pdo, int $userId): array|null
 * Retourne le profil joueur (players.*). Le crée si absent (1:1 users→players).
 * NB: si tu as déjà ta propre version, on ne la redéfinit pas.
 */
if (!function_exists('get_player_for_user')) {
  function get_player_for_user(PDO $pdo, int $userId): ?array {
    // 1) Existe ?
    $q = $pdo->prepare("SELECT uid, user_id, name, color FROM players WHERE user_id=:u LIMIT 1");
    $q->execute([':u'=>$userId]);
    $row = $q->fetch();
    if ($row) return $row;

    // 2) Sinon, on fabrique un profil par défaut
    //    Récupère un nom d’affichage depuis users si dispo
    $name = 'Joueur '.$userId;
    try {
      $s = $pdo->prepare("SELECT COALESCE(display_name, email) AS n FROM users WHERE id=:u LIMIT 1");
      $s->execute([':u'=>$userId]);
      $u = $s->fetch();
      if ($u && trim((string)$u['n']) !== '') $name = (string)$u['n'];
    } catch (Throwable $e) {
      // silencieux : on garde le fallback
    }

    // Insert sans uid => le trigger MySQL (si créé) mettra UUID()
    // sinon on force un uid via PHP (uuidv4) si tu n’as pas le trigger.
    $hasTrigger = true;
    try {
      $ins = $pdo->prepare("INSERT INTO players (user_id, name, color) VALUES (:u,:n,:c)");
      $ins->execute([':u'=>$userId, ':n'=>$name, ':c'=>'#06b6d4']);
    } catch (Throwable $e) {
      $hasTrigger = false;
    }

    if (!$hasTrigger) {
      // fallback : on insère avec uid généré en PHP
      $uid = uuidv4();
      $ins2 = $pdo->prepare("INSERT IGNORE INTO players (uid, user_id, name, color) VALUES (:uid,:u,:n,:c)");
      $ins2->execute([':uid'=>$uid, ':u'=>$userId, ':n'=>$name, ':c'=>'#06b6d4']);
    }

    // Relit
    $q->execute([':u'=>$userId]);
    $row = $q->fetch();
    return $row ?: null;
  }
}
// ---fin du bloc A4 ---
