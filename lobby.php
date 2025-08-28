<?php
// ---debut du bloc L0 : lobby.php v6 — Document complet (HTML + JS intégré) ---
declare(strict_types=1);

require __DIR__ . '/config.php';

// ---debut du bloc L1 : Session & bootstrap joueur ---
/**
 * On s’assure d’avoir un identifiant joueur côté session.
 * Si absent, on en génère un (UUID court) — à adapter si tu as déjà un auth flow.
 */
if (empty($_SESSION['player_id'])) {
  $uid = substr(str_replace('-', '', uuidv4()), 0, 8);
  $_SESSION['player_id'] = 'P'.$uid;
}
$playerId = (string)$_SESSION['player_id'];
// ---fin du bloc L1 ---
?>
<!doctype html>
<html lang="fr">
<head>
  <!-- ---debut du bloc L2 : Head & styles --- -->
  <meta charset="utf-8">
  <title>Carca-like — Lobby (v6)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/style.css" rel="stylesheet">
  <style>
    /* ---debut du bloc L2a : Ajustements lobby --- */
    :root{ --border:#2b3557; --panel:#0f1324; }
    .wrap{max-width:1000px;margin:16px auto;padding:0 14px}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .row-between{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .list{display:grid;gap:8px}
    .game-item{display:flex;align-items:center;justify-content:space-between;padding:10px;border:1px solid var(--border);border-radius:12px;background:var(--panel)}
    .mut{color:#9aa4c3}
    .field{display:flex;gap:8px;align-items:center}
    input[type="text"]{background:#0e1430;color:#fff;border:1px solid var(--border);border-radius:10px;padding:8px}
    .btn{cursor:pointer}
    .tag{background:#0e1430;border:1px solid var(--border);padding:2px 8px;border-radius:999px}
    .state-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#a1a1aa}
    .state-dot.on{background:#22c55e}
    .copy{cursor:pointer}
    /* ---fin du bloc L2a --- */
  </style>
  <!-- ---fin du bloc L2 --- -->
</head>
<body>
  <!-- ---debut du bloc L3 : Bootstrap côté client --- -->
  <script>
    window.bootstrap = Object.freeze({
      playerId: <?= json_encode($playerId, JSON_UNESCAPED_UNICODE) ?>,
      endpoints: {
        lobby: 'lobby_api.php',        // POST {action: ...}
        state: 'lobby_api.php?action=state' // GET long-poll or pull
      }
    });
  </script>
  <!-- ---fin du bloc L3 --- -->

  <div class="wrap">
    <!-- ---debut du bloc L4 : Header --- -->
    <header class="card">
      <div class="row-between">
        <div class="row">
          <h1 style="margin:0">Carca-like — Lobby</h1>
          <span class="tag">v6</span>
        </div>
        <div class="row">
          <span class="mut">Joueur :</span>
          <b id="meId"><?= htmlspecialchars($playerId, ENT_QUOTES,'UTF-8') ?></b>
          <div class="field">
            <input id="inpName" type="text" placeholder="Changer de nom (optionnel)" maxlength="24">
            <button id="btnRename" class="btn small">Mettre à jour</button>
          </div>
        </div>
      </div>
    </header>
    <!-- ---fin du bloc L4 --- -->

    <!-- ---debut du bloc L5 : Actions globales --- -->
    <section class="card">
      <div class="row-between" style="margin-bottom:8px">
        <h2 style="margin:0">Créer / Rejoindre</h2>
        <div class="row">
          <button id="btnHost" class="btn primary">Créer une partie</button>
          <div class="field">
            <input id="inpJoinId" type="text" placeholder="ID partie…" style="width:140px">
            <button id="btnJoin" class="btn">Rejoindre</button>
          </div>
          <button id="btnRefresh" class="btn alt">Rafraîchir</button>
        </div>
      </div>
      <div class="mut">Astuce : clique sur “Créer une partie”, puis copie le lien d’invitation pour tes amis.</div>
    </section>
    <!-- ---fin du bloc L5 --- -->

    <!-- ---debut du bloc L6 : Liste des parties --- -->
    <section class="card">
      <div class="row-between" style="margin-bottom:8px">
        <h2 style="margin:0">Parties disponibles</h2>
        <div class="row"><span class="mut">MàJ: <b id="hudVersion">—</b></span></div>
      </div>
      <div id="gamesList" class="list">
        <div class="empty">Chargement…</div>
      </div>
    </section>
    <!-- ---fin du bloc L6 --- -->

    <!-- ---debut du bloc L7 : Détails de ma partie (si rejoint/host) --- -->
    <section class="card" id="myGameCard" style="display:none">
      <div class="row-between" style="margin-bottom:8px">
        <h2 style="margin:0">Ma partie</h2>
        <div class="row">
          <button id="btnLeave" class="btn alt">Quitter</button>
          <button id="btnReady" class="btn small">Je suis prêt</button>
          <button id="btnUnready" class="btn small">Pas prêt</button>
          <button id="btnStart" class="btn primary">Démarrer</button>
        </div>
      </div>
      <div class="row" style="gap:16px">
        <div>Partie #<b id="myGameId">—</b></div>
        <div>Hôte : <b id="myGameHost">—</b></div>
        <div><span class="mut">Inviter :</span> <code id="inviteUrl">—</code> <span id="btnCopy" class="copy tag">Copier</span></div>
      </div>
      <div style="margin-top:10px">
        <h3 style="margin:0 0 6px 0">Joueurs</h3>
        <div id="myPlayers" class="list"></div>
      </div>
    </section>
    <!-- ---fin du bloc L7 --- -->
  </div>

  <!-- ---debut du bloc L8 : JS client v6 intégré --- -->
  <script type="module">
  // ---debut du bloc S0 : Bootstrap & helpers ---
  const BOOT = window.bootstrap || { playerId:null, endpoints:{} };
  const EPTS = BOOT.endpoints || {};
  const meId = BOOT.playerId;
  function qs(s,root=document){ return root.querySelector(s); }
  function ce(tag,cls){ const el=document.createElement(tag); if(cls) el.className=cls; return el; }
  function sleep(ms){ return new Promise(r=>setTimeout(r,ms)); }
  function setText(el,txt){ if(el) el.textContent = String(txt); }
  // ---fin du bloc S0 ---

  // ---debut du bloc S1 : Refs DOM ---
  const btnHost   = qs('#btnHost');
  const btnJoin   = qs('#btnJoin');
  const btnRefresh= qs('#btnRefresh');
  const inpJoinId = qs('#inpJoinId');
  const inpName   = qs('#inpName');
  const btnRename = qs('#btnRename');

  const gamesList = qs('#gamesList');
  const hudVer    = qs('#hudVersion');

  const cardMine  = qs('#myGameCard');
  const myGameId  = qs('#myGameId');
  const myGameHost= qs('#myGameHost');
  const myPlayers = qs('#myPlayers');
  const inviteUrl = qs('#inviteUrl');
  const btnCopy   = qs('#btnCopy');
  const btnLeave  = qs('#btnLeave');
  const btnReady  = qs('#btnReady');
  const btnUnready= qs('#btnUnready');
  const btnStart  = qs('#btnStart');
  // ---fin du bloc S1 ---

  // ---debut du bloc S2 : Etat client ---
  let LOBBY = { version:0, games:[], me:{}, myGame:null };
  let stopPoll = false;
  // ---fin du bloc S2 ---

  // ---debut du bloc S3 : API HTTP (lobby_api.php) ---
  async function apiLobby(action, payload={}) {
    const r = await fetch(EPTS.lobby, {
      method:'POST',
      headers:{'Content-Type':'application/json; charset=utf-8'},
      body: JSON.stringify({ action, ...payload })
    });
    return r.json();
  }
  async function apiStateSince(since=0) {
    const r = await fetch(`${EPTS.state}&since=${since}`, { cache:'no-store' });
    return r.json();
  }
  // ---fin du bloc S3 ---

  // ---debut du bloc R0 : rendu liste parties ---
  function renderGames(games=[]) {
    if (!gamesList) return;
    gamesList.innerHTML = '';
    if (!games.length) { gamesList.innerHTML = '<div class="empty">Aucune partie visible</div>'; return; }

    games.forEach(g=>{
      const row = ce('div','game-item');
      const left = ce('div','row');
      const right= ce('div','row');

      const dot = ce('span','state-dot'); dot.classList.toggle('on', g.status==='open');
      const title = ce('div'); title.innerHTML = `<b>#${g.id}</b> — <span class="mut">${g.status||'?'}</span>`;
      const players = ce('div','mut'); players.textContent = `Joueurs: ${Array.isArray(g.players)? g.players.length : 0}`;
      left.appendChild(dot); left.appendChild(title); left.appendChild(players);

      const btnJ = ce('button','btn small'); btnJ.textContent = 'Rejoindre';
      btnJ.addEventListener('click', async ()=>{
        const j = await apiLobby('join', { playerId: meId, game_id: g.id });
        if (!j.ok) { alert(j.erreur || 'join'); return; }
        await hardRefresh();
      });

      right.appendChild(btnJ);
      row.appendChild(left);
      row.appendChild(right);
      gamesList.appendChild(row);
    });
  }
  // ---fin du bloc R0 ---

  // ---debut du bloc R1 : rendu "ma partie" ---
  function renderMyGame(my) {
    if (!cardMine) return;
    if (!my) { cardMine.style.display = 'none'; return; }

    cardMine.style.display = '';
    setText(myGameId, my.id ?? '—');
    setText(myGameHost, my.host ?? '—');

    // joueurs
    myPlayers.innerHTML = '';
    (my.players || []).forEach(p=>{
      const row = ce('div','row-between');
      row.innerHTML = `<div class="badge">${p.id||'?'}</div>
                       <div class="mut">${p.ready ? 'prêt' : '...'}</div>`;
      myPlayers.appendChild(row);
    });

    // lien d’invite (GET ?game_id=)
    const url = new URL(location.href);
    url.searchParams.set('game_id', my.id);
    inviteUrl.textContent = url.toString();
    btnCopy?.addEventListener('click', ()=> {
      navigator.clipboard?.writeText(url.toString()).then(()=> {
        btnCopy.textContent = 'Copié !'; setTimeout(()=> btnCopy.textContent='Copier', 900);
      }).catch(()=>{ /* noop */ });
    });

    // permissions : si je suis l’hôte, je peux démarrer
    const isHost = (String(my.host||'') === String(meId||''));
    btnStart?.toggleAttribute('disabled', !isHost);
  }
  // ---fin du bloc R1 ---

  // ---debut du bloc U0 : petites mises à jour UI ---
  function renderHUD(){ if (hudVer) hudVer.textContent = String(LOBBY.version ?? '—'); }
  async function hardRefresh(){
    try{
      const r = await fetch(EPTS.state, { cache:'no-store' });
      const j = await r.json();
      if (j && j.ok){
        LOBBY = j;
        renderHUD();
        renderGames(j.games || []);
        renderMyGame(j.myGame || null);
      }
    }catch(_){}
  }
  // ---fin du bloc U0 ---

  // ---debut du bloc F0 : actions utilisateur ---
  btnHost?.addEventListener('click', async ()=>{
    const j = await apiLobby('host', { playerId: meId });
    if (!j.ok){ alert(j.erreur||'host'); return; }
    await hardRefresh();
  });

  btnJoin?.addEventListener('click', async ()=>{
    const id = (inpJoinId?.value||'').trim();
    if (!id){ alert('Saisis un ID de partie.'); return; }
    const j = await apiLobby('join', { playerId: meId, game_id: id });
    if (!j.ok){ alert(j.erreur||'join'); return; }
    await hardRefresh();
  });

  btnLeave?.addEventListener('click', async ()=>{
    const j = await apiLobby('leave', { playerId: meId });
    if (!j.ok){ alert(j.erreur||'leave'); return; }
    await hardRefresh();
  });

  btnReady?.addEventListener('click', async ()=>{
    const j = await apiLobby('ready', { playerId: meId, ready:true });
    if (!j.ok){ alert(j.erreur||'ready'); return; }
    await hardRefresh();
  });

  btnUnready?.addEventListener('click', async ()=>{
    const j = await apiLobby('unready', { playerId: meId, ready:false });
    if (!j.ok){ alert(j.erreur||'unready'); return; }
    await hardRefresh();
  });

  btnStart?.addEventListener('click', async ()=>{
    const j = await apiLobby('start', { playerId: meId });
    if (!j.ok){ alert(j.erreur||'start'); return; }
    // Si l’API renvoie une redirection ou un game_id, on peut ouvrir jeu.php:
    if (j.game_id){ location.href = `jeu.php?game_id=${encodeURIComponent(j.game_id)}`; }
    else { await hardRefresh(); }
  });

  btnRename?.addEventListener('click', async ()=>{
    const name = (inpName?.value||'').trim();
    if (!name){ alert('Entre un nom.'); return; }
    const j = await apiLobby('rename', { playerId: meId, name });
    if (!j.ok){ alert(j.erreur||'rename'); return; }
    inpName.value = '';
    await hardRefresh();
  });

  btnRefresh?.addEventListener('click', hardRefresh);
  // ---fin du bloc F0 ---

  // ---debut du bloc P0 : bootstrap & long-poll ---
  async function pollLoop(){
    while(!stopPoll){
      try{
        const since = LOBBY.version || 0;
        const j = await apiStateSince(since);
        if (j && j.ok){
          if (j.notModified === true) {
            // pas de changement
          } else if ((j.version||0) > since) {
            LOBBY = j;
            renderHUD();
            renderGames(j.games || []);
            renderMyGame(j.myGame || null);
          }
        }
      }catch(_){}
      await sleep(800);
    }
  }

  async function boot(){
    await hardRefresh();

    // Auto-join si ?game_id= dans l’URL (pratique via lien d’invitation)
    const params = new URLSearchParams(location.search);
    const gid = params.get('game_id');
    if (gid){
      const j = await apiLobby('join', { playerId: meId, game_id: gid });
      if (j && !j.ok && j.erreur) {
        console.warn('join via URL:', j.erreur);
      }
      await hardRefresh();
    }

    // ping régulier (présence)
    setInterval(()=> apiLobby('ping', { playerId: meId }).catch(()=>{}), 8000);

    pollLoop();
  }
  document.addEventListener('DOMContentLoaded', boot);
  // ---fin du bloc P0 ---
  </script>
  <!-- ---fin du bloc L8 --- -->
</body>
</html>
<?php
// ---fin du bloc L0 ---
