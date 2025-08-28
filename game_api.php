<?php
// ---debut du bloc G0 : game_api.php v6 — Endpoints JSON tour-par-tour ---
declare(strict_types=1);

require __DIR__ . '/config.php';

// ---debut du bloc G1 : Boot & helpers IO ---
header('Content-Type: application/json; charset=utf-8');

/** Lecture JSON body + fusion avec $_POST (fallbacks) */
function read_json_body(): array {
  $raw  = file_get_contents('php://input');
  $data = [];
  if ($raw) { $j = json_decode($raw, true); if (is_array($j)) $data = $j; }
  foreach (['action','playerId','ifVersion','x','y','modele','rotation'] as $k) {
    if (isset($_POST[$k]) && !isset($data[$k])) $data[$k] = $_POST[$k];
  }
  foreach (['action'] as $k) if (!isset($data[$k]) && isset($_GET[$k])) $data[$k] = $_GET[$k];
  return $data;
}

function load_game(): array {
  $g = json_read(GAME_FILE, []);
  if (!is_array($g)) $g = [];
  // normalisation minimale
  $g['version'] = (int)($g['version'] ?? 0);
  $g['status']  = (string)($g['status'] ?? 'running'); // running|ended|paused
  $g['players'] = is_array($g['players'] ?? null) ? $g['players'] : []; // [{id,ready?}, ...]
  $g['turn']    = is_array($g['turn'] ?? null) ? $g['turn'] : [];
  $g['turn']['order'] = array_values(array_map('strval', $g['turn']['order'] ?? []));
  $g['turn']['index'] = (int)($g['turn']['index'] ?? 0);
  $g['turn']['phase'] = (string)($g['turn']['phase'] ?? 'DRAW'); // DRAW|PLACE|END
  $g['turn']['lock']  = is_array($g['turn']['lock'] ?? null) ? $g['turn']['lock'] : null; // {holder, at}
  $g['lastUpdate'] = (int)($g['lastUpdate'] ?? time());

  // Si aucun ordre mais des joueurs connus → fabrique un ordre par défaut
  if (empty($g['turn']['order']) && !empty($g['players'])) {
    $g['turn']['order'] = array_values(array_map(fn($p)=> (string)($p['id'] ?? ''), $g['players']));
    $g['turn']['order'] = array_values(array_filter($g['turn']['order'], fn($s)=> $s!==''));
    $g['turn']['index'] = 0;
  }
  // bornes index
  $n = count($g['turn']['order']);
  if ($n > 0) $g['turn']['index'] = max(0, min($g['turn']['index'], $n-1));
  else $g['turn']['index'] = 0;

  // purge lock expiré (TTL)
  if (is_array($g['turn']['lock'] ?? null)) {
    $at = (int)($g['turn']['lock']['at'] ?? 0);
    if ($at && (time() - $at) > (int)TURN_LOCK_TTL) $g['turn']['lock'] = null;
  }
  return $g;
}

function save_game(array $g, bool $bumpVersion=true): array {
  if ($bumpVersion) $g['version'] = (int)($g['version'] ?? 0) + 1;
  $g['lastUpdate'] = time();
  if (!json_write_atomic(GAME_FILE, $g)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erreur'=>'Écriture état partie échouée'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  return $g;
}

function current_player_id(array $g): ?string {
  $ord = $g['turn']['order'] ?? [];
  $idx = (int)($g['turn']['index'] ?? 0);
  return isset($ord[$idx]) ? (string)$ord[$idx] : null;
}

function require_running(array $g): void {
  if (($g['status'] ?? 'running') !== 'running') {
    http_response_code(409);
    echo json_encode(['ok'=>false, 'erreur'=>'Partie non active'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

function ensure_player_in_order(array $g, string $pid): void {
  $ord = $g['turn']['order'] ?? [];
  if (!in_array($pid, $ord, true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'erreur'=>'Joueur non autorisé'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
// ---fin du bloc G1 ---


// ---debut du bloc G2 : Action=state (GET ou POST) ---
if (($_GET['action'] ?? '') === 'state') {
  try{
    $g = load_game();
    $since = isset($_GET['since']) ? (int)$_GET['since'] : -1;

    if ($since >= 0 && $since === (int)$g['version']) {
      echo json_encode([
        'ok'=>true,
        'notModified'=>true,
        'version'=>$g['version'],
        'ts'=>$g['lastUpdate']
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    echo json_encode([
      'ok'=>true,
      'status'=>$g['status'],
      'version'=>$g['version'],
      'players'=>$g['players'],
      'turn'=>$g['turn'],
      'ts'=>$g['lastUpdate']
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;

  }catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'erreur'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
// ---fin du bloc G2 ---


// ---debut du bloc G3 : Router POST (actions mutation) ---
try{
  $in = read_json_body();
  $action    = (string)($in['action'] ?? '');
  $playerId  = isset($in['playerId']) ? (string)$in['playerId'] : null;
  $ifVersion = isset($in['ifVersion']) ? (int)$in['ifVersion'] : null;

  $g = load_game();

  // Concurrence optimiste (si fourni)
  if ($ifVersion !== null && $ifVersion !== (int)$g['version']) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'erreur'=>'Version obsolète (recharger)'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  switch ($action) {
    // ---debut du bloc A1 : DRAW ---
    case 'draw': {
      require_running($g);
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      ensure_player_in_order($g, $playerId);

      $cur = current_player_id($g);
      if ($cur !== $playerId) { http_response_code(403); echo json_encode(['ok'=>false,'erreur'=>'Ce n’est pas ton tour']); exit; }

      $phase = (string)($g['turn']['phase'] ?? 'DRAW');
      if ($phase !== 'DRAW') { http_response_code(409); echo json_encode(['ok'=>false,'erreur'=>"Phase invalide ($phase) pour draw"]); exit; }

      // Ici tu peux piocher une tuile (si tu gères une pioche). On se contente de passer en PLACE.
      $g['turn']['phase'] = 'PLACE';
      $g = save_game($g, true);

      echo json_encode(['ok'=>true,'game'=>$g], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A1 ---

    // ---debut du bloc A2 : BEGIN_PLACE (lock le tour) ---
    case 'begin_place': {
      require_running($g);
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      ensure_player_in_order($g, $playerId);

      $cur = current_player_id($g);
      if ($cur !== $playerId) { http_response_code(403); echo json_encode(['ok'=>false,'erreur'=>'Ce n’est pas ton tour']); exit; }

      $phase = (string)($g['turn']['phase'] ?? 'DRAW');
      if ($phase !== 'PLACE' && $phase !== 'DRAW') { http_response_code(409); echo json_encode(['ok'=>false,'erreur'=>"Phase invalide ($phase) pour begin_place"]); exit; }

      // lock : si déjà pris par un autre → 423
      $lock = $g['turn']['lock'] ?? null;
      if (is_array($lock) && isset($lock['holder']) && $lock['holder'] !== $playerId) {
        http_response_code(423); echo json_encode(['ok'=>false,'erreur'=>'Tour verrouillé par un autre joueur']); exit;
      }
      // pose le lock
      $g['turn']['lock'] = ['holder'=>$playerId, 'at'=>time()];
      // garantit phase PLACE
      $g['turn']['phase'] = 'PLACE';
      $g = save_game($g, true);

      echo json_encode(['ok'=>true,'game'=>$g], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A2 ---

    // ---debut du bloc A3 : ABORT_PLACE (libère le lock) ---
    case 'abort_place': {
      require_running($g);
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      ensure_player_in_order($g, $playerId);

      $lock = $g['turn']['lock'] ?? null;
      if (is_array($lock) && isset($lock['holder']) && $lock['holder'] !== $playerId) {
        http_response_code(423); echo json_encode(['ok'=>false,'erreur'=>'Lock détenu par un autre joueur']); exit;
      }
      // libère le lock, phase reste PLACE (le joueur peut retenter)
      $g['turn']['lock'] = null;
      $g = save_game($g, true);

      echo json_encode(['ok'=>true,'game'=>$g], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A3 ---

    // ---debut du bloc A4 : AFTER_PLACE (valide la pose, passe en END) ---
    case 'after_place': {
      require_running($g);
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      ensure_player_in_order($g, $playerId);

      $cur = current_player_id($g);
      if ($cur !== $playerId) { http_response_code(403); echo json_encode(['ok'=>false,'erreur'=>'Ce n’est pas ton tour']); exit; }

      $phase = (string)($g['turn']['phase'] ?? 'DRAW');
      if ($phase !== 'PLACE') { http_response_code(409); echo json_encode(['ok'=>false,'erreur'=>"Phase invalide ($phase) pour after_place"]); exit; }

      $lock = $g['turn']['lock'] ?? null;
      if (!is_array($lock) || ($lock['holder'] ?? null) !== $playerId) {
        http_response_code(423); echo json_encode(['ok'=>false,'erreur'=>'Aucun lock actif pour ce joueur']); exit;
      }

      // Option : journaliser la dernière pose côté GAME (meta debug)
      $x = isset($in['x']) ? (int)$in['x'] : null;
      $y = isset($in['y']) ? (int)$in['y'] : null;
      $modele = isset($in['modele']) ? (string)$in['modele'] : null;
      $rotation = isset($in['rotation']) ? (int)$in['rotation'] : null;
      $g['lastPlacement'] = ['x'=>$x,'y'=>$y,'modele'=>$modele,'rotation'=>$rotation,'by'=>$playerId,'at'=>time()];

      // Phase -> END, lock libéré
      $g['turn']['phase'] = 'END';
      $g['turn']['lock']  = null;
      $g = save_game($g, true);

      echo json_encode(['ok'=>true,'game'=>$g], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A4 ---

    // ---debut du bloc A5 : END TURN (passe au joueur suivant, phase -> DRAW) ---
    case 'endTurn': {
      require_running($g);
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      ensure_player_in_order($g, $playerId);

      $cur = current_player_id($g);
      if ($cur !== $playerId) { http_response_code(403); echo json_encode(['ok'=>false,'erreur'=>'Ce n’est pas ton tour']); exit; }

      $phase = (string)($g['turn']['phase'] ?? 'DRAW');
      if ($phase !== 'END' && $phase !== 'PLACE') {
        http_response_code(409); echo json_encode(['ok'=>false,'erreur'=>"Phase invalide ($phase) pour fin de tour"]); exit;
      }

      // Avance l'index
      $ord = $g['turn']['order'] ?? [];
      $n   = count($ord);
      if ($n > 0) {
        $g['turn']['index'] = ((int)$g['turn']['index'] + 1) % $n;
      }
      // Phase -> DRAW, lock libéré
      $g['turn']['phase'] = 'DRAW';
      $g['turn']['lock']  = null;
      $g = save_game($g, true);

      echo json_encode(['ok'=>true,'game'=>$g], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A5 ---

    // ---debut du bloc A9 : default/echo state ---
    case 'state': // POST state (optionnel)
    default: {
      echo json_encode([
        'ok'=>true,
        'status'=>$g['status'],
        'version'=>$g['version'],
        'players'=>$g['players'],
        'turn'=>$g['turn'],
        'ts'=>$g['lastUpdate']
      ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
      exit;
    }
    // ---fin du bloc A9 ---
  }

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'erreur'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
// ---fin du bloc G3 ---

// ---fin du bloc G0 -----------------------------------------------------------
