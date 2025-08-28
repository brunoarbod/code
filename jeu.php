<?php
// ---debut du bloc J0 : jeu.php v6 (document complet, JS inclus) ---
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/config_models.php'; // $MODELES (cotes+liaisons par modèle)

// ---debut du bloc J1 : Contexte joueur & bootstrap ---
$playerId = isset($_SESSION['player_id']) ? (string)$_SESSION['player_id'] : null;
$GAME     = json_read(defined('GAME_FILE') ? GAME_FILE : (__DIR__.'/game_state.json'), []);
$gameVer  = (int)($GAME['version'] ?? 0);

// IDs des modèles pour la galerie
$modelIds = [];
if (isset($MODELES) && is_array($MODELES)) {
  $modelIds = array_keys($MODELES);
  sort($modelIds, SORT_NATURAL);
}
// ---fin du bloc J1 ---
?>
<!doctype html>
<html lang="fr">
<head>
  <!-- ---debut du bloc J2 : Head & styles --- -->
  <meta charset="utf-8">
  <title>Carca-like — Jeu (v6)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/style.css" rel="stylesheet">
  <style>
    /* ---debut du bloc J2a : ajustements légers pour s’aligner sur V4 --- */
    :root{
      --border:#2b3557; --card:#0f1324; --inset: inset 0 0 0 1px #2b355733;
    }
    .wrap{max-width:1200px;margin:14px auto;padding:0 14px}
    .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between}
    .gridwrap{overflow:auto;border:1px solid var(--border);border-radius:16px;padding:10px;background:#0a0f1d}
    #board{position:relative;min-height:60vh}
    .tile{position:absolute;width:110px;height:110px;border-radius:14px;display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(3,1fr);background:var(--card);border:1px solid var(--border);box-shadow:var(--inset)}
    .tile.preview{outline:2px dashed #93c5fd88}
    .models{display:flex;flex-wrap:wrap;gap:8px}
    .model-card{padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:#0e1430;cursor:pointer}
    .model-card.active{outline:2px solid #93c5fd88}
    .help{font-size:12px;color:#9aa4c3}
    .help.ok{color:#22c55e}.help.err{color:#ef4444}
    /* cellules V4 (grille 3x3): n,e,s,o,c */
    .cell{border-radius:6px;margin:2px}
    .cell.n{grid-area:1/2/2/3}.cell.e{grid-area:2/3/3/4}.cell.s{grid-area:3/2/4/3}.cell.o{grid-area:2/1/3/2}.cell.c{grid-area:2/2/3/3}
    /* types V4 — s’appuient sur ton style.css, on laisse générique ici */
    .route{background:linear-gradient(180deg,#ffd8a8 0,#e9b384 100%)}
    .ville{background:linear-gradient(180deg,#f87171 0,#dc2626 100%)}
    .champ{background:linear-gradient(180deg,#86efac 0,#16a34a 100%)}
    .village{background:linear-gradient(180deg,#a78bfa 0,#7c3aed 100%)}
    .rien{background:#0b1222}
    /* Overlay SVG (traits) */
    .overlay{position:absolute;left:0;top:0;pointer-events:none}
    .overlay path{stroke-width:6;fill:none;opacity:.9}
    .overlay path.route{stroke:#e09e55}
    .overlay path.ville{stroke:#ef4444}
    .overlay path.champ{stroke:#22c55e}
    .overlay path.village{stroke:#8b5cf6}
  </style>
  <!-- ---fin du bloc J2 --- -->
</head>
<body>
  <!-- ---debut du bloc J3 : Bootstrap côté client --- -->
  <script>
    window.bootstrap = Object.freeze({
      playerId: <?= $playerId ? json_encode($playerId, JSON_UNESCAPED_UNICODE) : 'null' ?>,
      gameVersion: <?= (int)$gameVer ?>,
      endpoints: {
        state: 'game_api.php?action=state',
        game:  'game_api.php',
        pose:  'pose_tuile.php'
      }
    });
    window.models = <?= json_encode($modelIds, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <!-- ---fin du bloc J3 --- -->

  <header class="wrap">
    <h1>Carca-like — Partie</h1>
    <div class="row" style="gap:12px">
      <span class="help">Version: <b id="hudVersion">—</b></span>
      <span class="help">Joueur: <b id="hudPlayer">—</b></span>
    </div>
  </header>

  <main class="wrap">
    <!-- ---debut du bloc J4 : Outils / actions --- -->
    <div class="card">
      <div class="toolbar">
        <div style="display:flex;gap:8px;align-items:center">
          <button id="btnRefresh" class="btn alt small">Rafraîchir</button>
          <button id="btnEndTurn" class="btn small">Fin du tour</button>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
          <button id="btnRotateLeft"  class="btn small" title="Rotation -90°">⟲</button>
          <button id="btnRotateRight" class="btn small" title="Rotation +90°">⟲⟲</button>
          <button id="btnPoser" class="btn primary">Poser la tuile</button>
          <span id="msg" class="help">Prêt.</span>
        </div>
      </div>
    </div>
    <!-- ---fin du bloc J4 --- -->

    <!-- ---debut du bloc J5 : Joueurs --- -->
    <div class="card">
      <div class="row-between" style="margin-bottom:8px"><h2 style="margin:0">Joueurs</h2></div>
      <div id="playersList"><div class="empty">Chargement…</div></div>
    </div>
    <!-- ---fin du bloc J5 --- -->

    <!-- ---debut du bloc J6 : Plateau --- -->
    <div class="card">
      <div class="row-between" style="margin-bottom:8px"><h2 style="margin:0">Plateau</h2></div>
      <div class="gridwrap"><div id="board"></div></div>
    </div>
    <!-- ---fin du bloc J6 --- -->

    <!-- ---debut du bloc J7 : Galerie modèles --- -->
    <div class="card">
      <div class="row-between" style="margin-bottom:8px">
        <h2 style="margin:0">Tuiles</h2>
        <div class="legend"><span class="tag">Sélectionne une tuile, déplace le curseur (flèches), tourne (⟲/⟲⟲)</span></div>
      </div>
      <div id="gallery" class="models"></div>
    </div>
    <!-- ---fin du bloc J7 --- -->
  </main>

  <!-- ---debut du bloc J8 : JS client v6 intégré --- -->
  <script type="module">
    // ---debut du bloc C0 : Bootstrap & utils DOM ---
    const BOOT = window.bootstrap || { playerId:null, gameVersion:0, endpoints:{} };
    const EPTS = BOOT.endpoints || {};
    const MODELS = Array.isArray(window.models) ? window.models : [];
    function qs(sel, root=document){ return root.querySelector(sel); }
    function ce(tag, cls){ const el=document.createElement(tag); if(cls) el.className=cls; return el; }
    function sleep(ms){ return new Promise(r=>setTimeout(r,ms)); }
    // ---fin du bloc C0 ---

    // ---debut du bloc C1 : refs DOM ---
    const btnRefresh     = qs('#btnRefresh');
    const btnEndTurn     = qs('#btnEndTurn');
    const btnRotateLeft  = qs('#btnRotateLeft');
    const btnRotateRight = qs('#btnRotateRight');
    const btnPoser       = qs('#btnPoser');
    const $msg           = qs('#msg');
    const $hudVer        = qs('#hudVersion');
    const $hudPlayer     = qs('#hudPlayer');
    const $players       = qs('#playersList');
    const $board         = qs('#board');
    const $gallery       = qs('#gallery');
    // ---fin du bloc C1 ---

    // ---debut du bloc C2 : état client ---
    let GAME = { version: BOOT.gameVersion||0, turn:null, players:[] };
    let stopPoll = false;
    let selectedModel = MODELS[0] || null;
    let rotation = 0;
    let cursor = { x:0, y:0 };
    const TILE_PX = 110;
    const GAP = 8;
    const offset = { x:50, y:50 }; // évite les négatifs visuels
    const boardMap = new Map(); // "x,y" -> pose
    function key(x,y){ return `${x},${y}`; }
    // ---fin du bloc C2 ---

    // ---debut du bloc C3 : API HTTP ---
    async function apiGame(action, payload={}) {
      const r = await fetch(EPTS.game, { method:'POST', headers:{'Content-Type':'application/json; charset=utf-8'}, body: JSON.stringify({ action, ...payload }) });
      return r.json();
    }
    async function apiStateSince(since=0) {
      const r = await fetch(`${EPTS.state}&since=${since}`, { cache:'no-store' });
      return r.json();
    }
    async function apiPoseTuile({ modele, rotation, x, y }) {
      const r = await fetch(EPTS.pose, {
        method:'POST',
        headers:{'Content-Type':'application/json; charset=utf-8'},
        body: JSON.stringify({ modele_id:String(modele), rotation:Math.trunc(rotation), x:Math.trunc(x), y:Math.trunc(y) })
      });
      return r.json();
    }
    // ---fin du bloc C3 ---

    // ---debut du bloc T0 : mapping types & directions (style V4) ---
    function v4TypeClass(raw) {
      const t = String(raw||'').toUpperCase();
      switch(t){case 'ROUTE':return 'route';case 'VILLE':return 'ville';case 'CHAMP':return 'champ';case 'VILLAGE':return 'village';case 'RIEN':return 'rien';default:return 'rien';}
    }
    function cellClassFor(dir) {
      switch(String(dir).toUpperCase()){case 'NORD':return 'n';case 'EST':return 'e';case 'SUD':return 's';case 'OUEST':return 'o';case 'CENTRE':return 'c';default:return null;}
    }
    // ---fin du bloc T0 ---

    // ---debut du bloc T1 : overlay SVG (liaisons) ---
    const PTS = { C:[TILE_PX/2,TILE_PX/2], N:[TILE_PX/2,12], E:[TILE_PX-12,TILE_PX/2], S:[TILE_PX/2,TILE_PX-12], O:[12,TILE_PX/2] };
    function ptOf(d){ switch(String(d).toUpperCase()){case 'NORD':return PTS.N;case 'EST':return PTS.E;case 'SUD':return PTS.S;case 'OUEST':return PTS.O;case 'CENTRE':return PTS.C;default:return null;} }
    function strokeClass(type){ return v4TypeClass(type); }
    function buildOverlaySVG(liaisons=[]) {
      const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
      svg.classList.add('overlay'); svg.setAttribute('viewBox',`0 0 ${TILE_PX} ${TILE_PX}`); svg.setAttribute('width',TILE_PX); svg.setAttribute('height',TILE_PX);
      for (const l of (Array.isArray(liaisons)? l : [])) {
        const cls = strokeClass(l.type); const grp = Array.isArray(l.groupe)? l.groupe : [];
        for (const p of grp) {
          const a = ptOf('CENTRE'), b = ptOf(p); if(!a||!b) continue;
          const path = document.createElementNS('http://www.w3.org/2000/svg','path');
          path.setAttribute('d',`M ${a[0]} ${a[1]} L ${b[0]} ${b[1]}`); path.setAttribute('vector-effect','non-scaling-stroke');
          path.classList.add(cls); svg.appendChild(path);
        }
      }
      return svg;
    }
    // ---fin du bloc T1 ---

    // ---debut du bloc T2 : fabrique DOM d’une tuile (V4) ---
    function tileDOMFromPose(pose) {
      const root = document.createElement('div'); root.className = 'tile';
      root.style.transform = `rotate(${pose.rotation||0}deg)`;
      for (const d of ['NORD','EST','SUD','OUEST','CENTRE']) {
        const cell = document.createElement('div');
        const cc = cellClassFor(d); if (cc) cell.classList.add('cell', cc);
        const rawType = pose?.cotes?.[d] ?? 'RIEN';
        cell.classList.add(v4TypeClass(rawType));
        root.appendChild(cell);
      }
      root.appendChild(buildOverlaySVG(pose.liaisons||[]));
      const badge = document.createElement('div'); badge.className='xy'; badge.style.position='absolute'; badge.style.right='6px'; badge.style.bottom='6px'; badge.textContent=pose.modele;
      root.appendChild(badge);
      return root;
    }
    // ---fin du bloc T2 ---

    // ---debut du bloc R1 : rendu HUD / joueurs / galerie / plateau ---
    function renderHUD(){ if($hudVer) $hudVer.textContent=String(GAME.version??'—'); if($hudPlayer) $hudPlayer.textContent=BOOT.playerId??'—'; }
    function renderPlayers(players=[]) {
      if(!$players) return; $players.innerHTML='';
      if(!players.length){ $players.innerHTML='<div class="empty">Aucun joueur</div>'; return; }
      players.forEach(p=>{ const row=ce('div','row-between'); row.innerHTML=`<div class="badge">${p.id||'?'}</div><div class="help">${p.ready?'prêt':'en jeu'}</div>`; $players.appendChild(row); });
    }
    function renderGallery(models=[]){
      if(!$gallery) return; $gallery.innerHTML='';
      if(!models.length){ $gallery.innerHTML='<div class="empty">Aucun modèle</div>'; return; }
      models.forEach(id=>{
        const card=ce('div','model-card'); card.innerHTML=`<div class="id">${id}</div>`; if(id===selectedModel) card.classList.add('active');
        card.addEventListener('click', ()=>{ selectedModel=id; [...$gallery.children].forEach(c=>c.classList.remove('active')); card.classList.add('active'); refreshPoseButton(); renderBoard(); });
        $gallery.appendChild(card);
      });
    }
    function putTileLocal(pose){
      boardMap.set(key(pose.x,pose.y), {
        x:pose.x, y:pose.y, modele:pose.modele, rotation:pose.rotation||0,
        cotes:pose.cotes||{}, liaisons:Array.isArray(pose.liaisons)? pose.liaisons : []
      });
    }
    function renderBoard(){
      if(!$board) return; $board.innerHTML='';
      // tuiles posées
      for(const [k,t] of boardMap){
        const [x,y]=k.split(',').map(Number); const el=tileDOMFromPose(t);
        el.style.left=`${(x+offset.x)*(TILE_PX+GAP)}px`; el.style.top=`${(y+offset.y)*(TILE_PX+GAP)}px`;
        $board.appendChild(el);
      }
      // preview
      const pv=document.createElement('div'); pv.className='tile preview';
      pv.style.left=`${(cursor.x+offset.x)*(TILE_PX+GAP)}px`; pv.style.top=`${(cursor.y+offset.y)*(TILE_PX+GAP)}px`; pv.style.transform=`rotate(${rotation}deg)`;
      for(const d of ['NORD','EST','SUD','OUEST','CENTRE']){ const c=document.createElement('div'); c.className=`cell ${cellClassFor(d)} rien`; pv.appendChild(c); }
      const badge=document.createElement('div'); badge.className='xy'; badge.style.position='absolute'; badge.style.right='6px'; badge.style.bottom='6px'; badge.textContent=selectedModel||'—'; pv.appendChild(badge);
      $board.appendChild(pv);
    }
    // ---fin du bloc R1 ---

    // ---debut du bloc F1 : logique de tour & pose ---
    function canPlayNow(){
      const order = GAME?.turn?.order || []; const idx = GAME?.turn?.index ?? 0; const curId = order[idx] || null; const phase = GAME?.turn?.phase || 'DRAW';
      return (BOOT.playerId && curId===BOOT.playerId && (phase==='PLACE'||phase==='DRAW'));
    }
    function refreshPoseButton(){ if(!btnPoser) return; btnPoser.disabled = !(selectedModel && canPlayNow()); }
    async function flowPose(){
      if(!selectedModel) return;
      if(!BOOT.playerId){ alert('Identifiant joueur manquant.'); return; }
      btnPoser.disabled=true; if($msg){ $msg.textContent='Pose en cours…'; $msg.className='help'; }
      try{
        if((GAME?.turn?.phase||'DRAW')==='DRAW'){
          const j = await apiGame('draw', { playerId: BOOT.playerId, ifVersion: GAME.version });
          if(!j.ok) throw new Error(j.erreur||'draw'); GAME = j.game || GAME;
        }
        { const j = await apiGame('begin_place', { playerId: BOOT.playerId, ifVersion: GAME.version }); if(!j.ok) throw new Error(j.erreur||'begin_place'); }
        const poseRes = await apiPoseTuile({ modele:selectedModel, rotation, x:cursor.x, y:cursor.y });
        if(!poseRes.ok) throw new Error(poseRes.erreur||'pose');
        const p = poseRes.pose || { x:cursor.x, y:cursor.y, modele:selectedModel, rotation };
        putTileLocal(p); renderBoard();
        const j = await apiGame('after_place', { playerId: BOOT.playerId, ifVersion: GAME.version, x:p.x, y:p.y, modele:p.modele, rotation:p.rotation });
        if(!j.ok) throw new Error(j.erreur||'after_place'); GAME=j.game||GAME;
        if($msg){ $msg.textContent='Tuile posée. Terminer le tour quand prêt.'; $msg.className='help ok'; }
      }catch(e){
        try{ await apiGame('abort_place', { playerId: BOOT.playerId }); }catch(_){}
        if($msg){ $msg.textContent='Erreur: '+(e?.message||e); $msg.className='help err'; }
      }finally{
        refreshPoseButton();
      }
    }
    // ---fin du bloc F1 ---

    // ---debut du bloc S1 : bootstrap & long-poll ---
    async function bootstrapState(){
      try{ const r = await fetch(EPTS.state, { cache:'no-store' }); const g = await r.json();
        if(g && g.ok){ GAME=g; renderHUD(); renderPlayers(g.players||[]); refreshPoseButton(); }
      }catch(_){}
    }
    async function waitLoop(){
      while(!stopPoll){
        try{
          const since = GAME.version || 0;
          const j = await apiStateSince(since);
          if(j && j.ok){
            if(j.notModified===true){ /* noop */ }
            else if((j.version||0)>since){ location.reload(); return; }
          }
        }catch(_){}
        await sleep(600);
      }
    }
    // ---fin du bloc S1 ---

    // ---debut du bloc W1 : wiring UI ---
    function wire(){
      renderHUD(); renderGallery(MODELS); renderBoard(); refreshPoseButton();

      document.addEventListener('keydown', (e)=>{
        let moved=false;
        if(e.key==='ArrowLeft'){ cursor.x--; moved=true; }
        if(e.key==='ArrowRight'){ cursor.x++; moved=true; }
        if(e.key==='ArrowUp'){ cursor.y--; moved=true; }
        if(e.key==='ArrowDown'){ cursor.y++; moved=true; }
        if(e.key==='r' || e.key==='R'){ rotation=(rotation+90)%360; moved=true; }
        if(moved){ renderBoard(); refreshPoseButton(); }
      });

      btnRotateLeft?.addEventListener('click', ()=>{ rotation=(rotation+270)%360; renderBoard(); });
      btnRotateRight?.addEventListener('click', ()=>{ rotation=(rotation+90)%360; renderBoard(); });
      btnPoser?.addEventListener('click', flowPose);
      btnRefresh?.addEventListener('click', ()=> location.reload());
      btnEndTurn?.addEventListener('click', async ()=>{
        if(!BOOT.playerId){ alert('ID joueur inconnu'); return; }
        btnEndTurn.disabled=true;
        try{ const j = await apiGame('endTurn', { playerId: BOOT.playerId, ifVersion: GAME.version }); if(!j.ok) throw new Error(j.erreur||'endTurn'); GAME=j.game||GAME; renderHUD();
        }catch(e){ alert('endTurn: '+(e?.message||e)); }
        finally{ btnEndTurn.disabled=false; }
      });

      bootstrapState().then(()=> waitLoop());
    }
    document.addEventListener('DOMContentLoaded', wire);
    // ---fin du bloc W1 ---
  </script>
  <!-- ---fin du bloc J8 --- -->
</body>
</html>
<?php
// ---fin du bloc J0 ---
