<?php
// ---debut du bloc LA0 : lobby_api.php v6 — API JSON Lobby ---
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// ---debut du bloc LA1 : Helpers I/O & structure ---
/** Charge l'état lobby (ou structure par défaut). */
function lobby_load(): array {
  $L = json_read(LOBBY_FILE, null);
  if (!is_array($L)) $L = [];
  $L['version']   = (int)($L['version'] ?? 0);
  $L['createdAt'] = (int)($L['createdAt'] ?? time());
  $L['updatedAt'] = (int)($L['updatedAt'] ?? time());
  // jeux indexés par ID => normaliser en tableau associatif
  $L['games'] = is_array($L['games'] ?? null) ? $L['games'] : [];
  // annuaire optionnel des noms
  $L['names'] = is_array($L['names'] ?? null) ? $L['names'] : [];
  return $L;
}

/** Sauve l'état lobby, avec incrément optionnel de version. */
function lobby_save(array $L, bool $bumpVersion = true): array {
  if ($bumpVersion) $L['version'] = (int)($L['version'] ?? 0) + 1;
  $L['updatedAt'] = time();
  if (!json_write_atomic(LOBBY_FILE, $L)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erreur'=>'Écriture lobby échouée'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  return $L;
}

/** Lecture JSON body + merge basique POST/GET. */
function in_json(): array {
  $raw = file_get_contents('php://input'); $data = [];
  if ($raw) { $j = json_decode($raw, true); if (is_array($j)) $data = $j; }
  foreach (['action','playerId','game_id','name','ready'] as $k) {
    if (isset($_POST[$k]) && !isset($data[$k])) $data[$k] = $_POST[$k];
  }
  if (!isset($data['action']) && isset($_GET['action'])) $data['action'] = $_GET['action'];
  if (!isset($data['since'])  && isset($_GET['since']))  $data['since']  = $_GET['since'];
  return $data;
}

/** Génère le prochain ID de partie (int). */
function next_game_id(array $L): int {
  $max = 0;
  foreach ($L['games'] as $gid => $_) { $max = max($max, (int)$gid); }
  return $max + 1;
}

/** Trouve l'ID de partie d'un joueur (ou null). */
function find_player_game(array $L, string $pid): ?int {
  foreach ($L['games'] as $gid => $g) {
    $players = $g['players'] ?? [];
    foreach ($players as $p) if (($p['id'] ?? '') === $pid) return (int)$gid;
  }
  return null;
}

/** Retourne par valeur un jeu (ou null). */
function get_game(array $L, int $gid): ?array {
  return isset($L['games'][$gid]) && is_array($L['games'][$gid]) ? $L['games'][$gid] : null;
}

/** Écrit un jeu (par référence dans $L). */
function put_game(array &$L, array $G): void {
  $gid = (int)($G['id'] ?? 0);
  if ($gid <= 0) return;
  $G['updatedAt'] = time();
  $L['games'][$gid] = $G;
}

/** Supprime un jeu. */
function del_game(array &$L, int $gid): void {
  unset($L['games'][$gid]);
}

/** Ajoute le joueur à la partie (si absent). */
function game_add_player(array $G, string $pid, ?string $name=null): array {
  $players = is_array($G['players'] ?? null) ? $G['players'] : [];
  foreach ($players as $p) if (($p['id'] ?? '') === $pid) return $G; // déjà présent
  $players[] = [
    'id'       => $pid,
    'name'     => (string)($name ?? ''),
    'ready'    => false,
    'lastPing' => time(),
  ];
  $G['players'] = array_values($players);
  return $G;
}

/** Retire le joueur de la partie (si présent). */
function game_remove_player(array $G, string $pid): array {
  $players = is_array($G['players'] ?? null) ? $G['players'] : [];
  $players = array_values(array_filter($players, fn($p)=> ($p['id'] ?? '') !== $pid));
  $G['players'] = $players;
  // si l'hôte est parti -> transférer au 1er joueur, sinon null si vide
  if (($G['host'] ?? '') === $pid) {
    $G['host'] = $players[0]['id'] ?? null;
  }
  return $G;
}

/** Met à jour ready/ping/name pour un joueur. */
function game_touch_player(array $G, string $pid, ?bool $ready=null, ?string $name=null, bool $ping=true): array {
  $players = is_array($G['players'] ?? null) ? $G['players'] : [];
  foreach ($players as &$p) {
    if (($p['id'] ?? '') !== $pid) continue;
    if ($ready !== null) $p['ready'] = (bool)$ready;
    if ($name  !== null) $p['name']  = (string)$name;
    if ($ping) $p['lastPing'] = time();
  }
  unset($p);
  $G['players'] = $players;
  return $G;
}

/** Nettoie les parties vides. */
function cleanup_empty_games(array &$L): void {
  foreach (array_keys($L['games']) as $gid) {
    $G = $L['games'][$gid];
    $players = is_array($G['players'] ?? null) ? $G['players'] : [];
    if (count($players) === 0) unset($L['games'][$gid]);
  }
}

/** Vue "publique" (liste) d'un jeu pour l'UI. */
function game_public_view(array $G): array {
  $players = [];
  foreach (($G['players'] ?? []) as $p) {
    $players[] = [
      'id'    => (string)($p['id'] ?? ''),
      'name'  => (string)($p['name'] ?? ''),
      'ready' => (bool)($p['ready'] ?? false),
    ];
  }
  return [
    'id'      => (int)($G['id'] ?? 0),
    'host'    => (string)($G['host'] ?? ''),
    'status'  => (string)($G['status'] ?? 'open'), // open|started
    'players' => $players,
    'updated' => (int)($G['updatedAt'] ?? time()),
  ];
}
// ---fin du bloc LA1 ---


// ---debut du bloc LA2 : Action=state (GET) ---
if (($_GET['action'] ?? '') === 'state') {
  try {
    $meId = isset($_SESSION['player_id']) ? (string)$_SESSION['player_id'] : null;
    $since = isset($_GET['since']) ? (int)$_GET['since'] : -1;

    $L = lobby_load();

    if ($since >= 0 && $since === (int)$L['version']) {
      echo json_encode([
        'ok'          => true,
        'notModified' => true,
        'version'     => $L['version'],
        'ts'          => $L['updatedAt'],
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // Vue publique pour toutes les parties
    $games = [];
    foreach ($L['games'] as $gid => $G) $games[] = game_public_view($G);

    // Si session connue, renvoyer aussi "ma" partie
    $myGame = null;
    if ($meId) {
      $gid = find_player_game($L, $meId);
      if ($gid !== null && isset($L['games'][$gid])) {
        $myGame = game_public_view($L['games'][$gid]);
      }
    }

    echo json_encode([
      'ok'      => true,
      'version' => $L['version'],
      'games'   => $games,
      'myGame'  => $myGame,
      'ts'      => $L['updatedAt'],
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;

  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erreur'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
// ---fin du bloc LA2 ---


// ---debut du bloc LA3 : Router POST (mutations) ---
try {
  $in = in_json();
  $action   = (string)($in['action'] ?? '');
  $playerId = isset($in['playerId']) ? (string)$in['playerId'] : null;

  $L = lobby_load();

  // Switch actions
  switch ($action) {

    // ---debut du bloc A1 : host (créer une partie) ---
    case 'host': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }

      // Quitter d'éventuelles parties existantes
      $cur = find_player_game($L, $playerId);
      if ($cur !== null && isset($L['games'][$cur])) {
        $G = game_remove_player($L['games'][$cur], $playerId);
        if (count($G['players']) === 0) del_game($L, $cur);
        else put_game($L, $G);
      }

      $gid = next_game_id($L);
      $G = [
        'id'        => $gid,
        'host'      => $playerId,
        'status'    => 'open', // open|started
        'players'   => [],
        'createdAt' => time(),
        'updatedAt' => time(),
      ];
      // Ajoute l'hôte comme 1er joueur
      $name = (string)($L['names'][$playerId] ?? '');
      $G = game_add_player($G, $playerId, $name);
      put_game($L, $G);

      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true,'game_id'=>$gid], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A1 ---

    // ---debut du bloc A2 : join (rejoindre une partie) ---
    case 'join': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $gid = isset($in['game_id']) ? (int)$in['game_id'] : 0;
      if ($gid <= 0 || !isset($L['games'][$gid])) { http_response_code(404); echo json_encode(['ok'=>false,'erreur'=>'Partie introuvable']); exit; }

      // Quitte l'ancienne partie, si besoin
      $cur = find_player_game($L, $playerId);
      if ($cur !== null && isset($L['games'][$cur])) {
        $G0 = game_remove_player($L['games'][$cur], $playerId);
        if (count($G0['players']) === 0) del_game($L, $cur);
        else put_game($L, $G0);
      }

      // Ajoute à la nouvelle
      $G = $L['games'][$gid];
      $name = (string)($L['names'][$playerId] ?? '');
      $G = game_add_player($G, $playerId, $name);
      put_game($L, $G);

      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true,'game_id'=>$gid], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A2 ---

    // ---debut du bloc A3 : leave (quitter sa partie) ---
    case 'leave': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $gid = find_player_game($L, $playerId);
      if ($gid === null || !isset($L['games'][$gid])) { echo json_encode(['ok'=>true]); exit; }

      $G = game_remove_player($L['games'][$gid], $playerId);
      if (count($G['players']) === 0) del_game($L, $gid);
      else put_game($L, $G);

      cleanup_empty_games($L);
      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A3 ---

    // ---debut du bloc A4 : ready / unready ---
    case 'ready':
    case 'unready': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $gid = find_player_game($L, $playerId);
      if ($gid === null || !isset($L['games'][$gid])) { http_response_code(404); echo json_encode(['ok'=>false,'erreur'=>'Pas dans une partie']); exit; }

      $want = ($action === 'ready') ? true : false;
      $G = $L['games'][$gid];
      $G = game_touch_player($G, $playerId, $want, null, true);
      put_game($L, $G);

      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A4 ---

    // ---debut du bloc A5 : start (host uniquement) ---
    case 'start': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $gid = find_player_game($L, $playerId);
      if ($gid === null || !isset($L['games'][$gid])) { http_response_code(404); echo json_encode(['ok'=>false,'erreur'=>'Pas dans une partie']); exit; }

      $G = $L['games'][$gid];
      if (($G['host'] ?? '') !== $playerId) { http_response_code(403); echo json_encode(['ok'=>false,'erreur'=>'Seul l’hôte peut démarrer']); exit; }

      // (Option) Vérifier que tous "ready" → ici on tolère si pas tous prêts.
      $G['status'] = 'started';
      put_game($L, $G);

      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true, 'game_id'=>$gid], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A5 ---

    // ---debut du bloc A6 : ping (présence, ne bump PAS la version) ---
    case 'ping': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $gid = find_player_game($L, $playerId);
      if ($gid !== null && isset($L['games'][$gid])) {
        $G = $L['games'][$gid];
        $G = game_touch_player($G, $playerId, null, null, true);
        put_game($L, $G);
        // Sauvegarde sans bump pour éviter de spammer la version
        $L = lobby_save($L, false);
      } else {
        // pas dans une partie -> juste note du nom s'il y en a un
        $L = lobby_save($L, false);
      }
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A6 ---

    // ---debut du bloc A7 : rename (met à jour le nom du joueur partout) ---
    case 'rename': {
      if (!$playerId) { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'playerId requis']); exit; }
      $name = isset($in['name']) ? trim((string)$in['name']) : '';
      if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'erreur'=>'Nom vide']); exit; }

      // met à jour annuaire global
      $L['names'][$playerId] = $name;

      // met à jour dans toutes les parties où il est présent
      foreach ($L['games'] as $gid => $G) {
        $L['games'][$gid] = game_touch_player($G, $playerId, null, $name, false);
      }

      $L = lobby_save($L, true);
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ---fin du bloc A7 ---

    // ---debut du bloc A9 : default → state ---
    case 'state':
    default: {
      // fallback : renvoyer l'état (équivalent GET /state)
      $meId = isset($_SESSION['player_id']) ? (string)$_SESSION['player_id'] : null;
      $games = [];
      foreach ($L['games'] as $gid => $G) $games[] = game_public_view($G);

      $myGame = null;
      if ($meId) {
        $gid = find_player_game($L, $meId);
        if ($gid !== null && isset($L['games'][$gid])) $myGame = game_public_view($L['games'][$gid]);
      }
      echo json_encode([
        'ok'=>true,
        'version'=>$L['version'],
        'games'=>$games,
        'myGame'=>$myGame,
        'ts'=>$L['updatedAt']
      ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
      exit;
    }
    // ---fin du bloc A9 ---
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'erreur'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
// ---fin du bloc LA3 ---

// ---fin du bloc LA0 ----------------------------------------------------------
