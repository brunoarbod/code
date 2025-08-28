<?php
// ---debut du bloc 1 : Déclaration / export ---
declare(strict_types=1);

/**
 * Chaque entrée de $MODELES :
 * - 'cotes' : NORD/EST/SUD/OUEST/CENTRE => 'CHAMP' | 'ROUTE' | 'VILLE' | 'VILLAGE' | 'RIEN'
 * - 'liaisons' : liste de groupes connectés, ex:
 *    [ ['type'=>'ROUTE','groupe'=>['EST','SUD']],
 *      ['type'=>'VILLAGE','groupe'=>['CENTRE']] ]
 * - 'flags' (optionnel) : ex. ['bouclier'=>true]
 */
$MODELES = [
// ---debut du bloc 2 : 🏰 Villes simples ---

  // (3) Ville complète (bouclier) — 100% ville
  'V3_VILLE_4_B' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'VILLE','OUEST'=>'VILLE','CENTRE'=>'VILLE'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','SUD','OUEST','CENTRE']],
    ],
    'flags' => ['bouclier'=>true],
  ],

  // (4) Ville 1 côté — N ville, autres champ
  'V4_VILLE_1_N' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'CHAMP','SUD'=>'CHAMP','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD']], // un seul côté de ville
    ],
  ],

  // (5) Ville 1 côté (bouclier)
  'V5_VILLE_1_N_B' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'CHAMP','SUD'=>'CHAMP','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD']],
    ],
    'flags' => ['bouclier'=>true],
  ],

// --- fin du bloc 2 ---

// ---debut du bloc 3 : 🏰 Villes doubles ---

 // (6) Ville 2 côtés opposés — N et S NON reliés
'V6_VILLE_2_NS' => [
  'cotes' => ['NORD'=>'VILLE','EST'=>'CHAMP','SUD'=>'VILLE','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
  'liaisons' => [
    ['type'=>'VILLE','groupe'=>['NORD']], // segment 1
    ['type'=>'VILLE','groupe'=>['SUD']],  // segment 2 (distinct)
  ],
],

  // (7) Ville 2 côtés adjacents (angle) — N et E connectés
  'V7_VILLE_2_NE' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'CHAMP','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST']],
    ],
  ],

  // (8) Ville 2 côtés adjacents (angle, bouclier)
  'V8_VILLE_2_NE_B' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'CHAMP','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST']],
    ],
    'flags' => ['bouclier'=>true],
  ],

// --- fin du bloc 3 ---

// ---debut du bloc 4 : 🏰 Villes triples ---

  // (9) Ville 3 côtés (en U) — N, E, O connectés
  'V9_VILLE_3_NEO' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'CHAMP','OUEST'=>'VILLE','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','OUEST']],
    ],
  ],

  // (10) Ville 3 côtés (en U, bouclier)
  'V10_VILLE_3_NEO_B' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'CHAMP','OUEST'=>'VILLE','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','OUEST']],
    ],
    'flags' => ['bouclier'=>true],
  ],

  // (11) Ville 3 côtés + route — N,E,O ville connectés ; route sort côté S
  'V11_VILLE_3_NEO_ROUTE_S' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'ROUTE','OUEST'=>'VILLE','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','OUEST']],
      ['type'=>'ROUTE','groupe'=>['SUD']], // route “pion” seule, ouverte
    ],
  ],

// --- fin du bloc 4 ---

// ---debut du bloc 5 : 🏰 Villes quadruples ---

  // (12) Ville 4 côtés (place centrale) — 100% ville reliée
  'V12_VILLE_4_PLACE' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'VILLE','OUEST'=>'VILLE','CENTRE'=>'VILLE'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','SUD','OUEST','CENTRE']],
    ],
  ],

  // (13) Ville 4 côtés (place centrale, bouclier)
  'V13_VILLE_4_PLACE_B' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'VILLE','SUD'=>'VILLE','OUEST'=>'VILLE','CENTRE'=>'VILLE'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD','EST','SUD','OUEST','CENTRE']],
    ],
    'flags' => ['bouclier'=>true],
  ],

// --- fin du bloc 5 ---

// ---debut du bloc 6 : 🛣 Villes + routes ---

  // (14) Ville 1 côté + route traversante (E–S)
  'V14_VILLE_N_ROUTE_ES' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'ROUTE','SUD'=>'ROUTE','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD']],
      ['type'=>'ROUTE','groupe'=>['EST','SUD']],
    ],
  ],

  // (15) Ville 1 côté + route en courbe (O–S)
  'V15_VILLE_N_ROUTE_OS' => [
    'cotes' => ['NORD'=>'VILLE','EST'=>'CHAMP','SUD'=>'ROUTE','OUEST'=>'ROUTE','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'VILLE','groupe'=>['NORD']],
      ['type'=>'ROUTE','groupe'=>['OUEST','SUD']],
    ],
  ],

 // ---debut : (16) Ville 1 côté + 3 routes borgnes (le village arrête chaque route) ---
'V16_VILLE_N_T_VILLAGE' => [
  'cotes' => [
    'NORD'   => 'VILLE',
    'EST'    => 'ROUTE',
    'SUD'    => 'ROUTE',
    'OUEST'  => 'ROUTE',
    'CENTRE' => 'VILLAGE',
  ],
  'liaisons' => [
    // la ville en haut est indépendante
    ['type'=>'VILLE','groupe'=>['NORD']],

    // trois routes SÉPARÉES (pas reliées entre elles, pas de CENTRE)
    ['type'=>'ROUTE','groupe'=>['EST']],
    ['type'=>'ROUTE','groupe'=>['SUD']],
    ['type'=>'ROUTE','groupe'=>['OUEST']],

    // le village central est sa propre feature (et sert d'arrêt logique)
    ['type'=>'VILLAGE','groupe'=>['CENTRE']],
  ],
],

// --- fin du bloc 6 ---

// ---debut du bloc 7 : 🛣 Routes seules ---

  // (17) Route droite N–S
  'R17_ROUTE_NS' => [
    'cotes' => ['NORD'=>'ROUTE','EST'=>'CHAMP','SUD'=>'ROUTE','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'ROUTE','groupe'=>['NORD','SUD']],
    ],
  ],

  // (18) Route courbe N–E
  'R18_ROUTE_NE' => [
    'cotes' => ['NORD'=>'ROUTE','EST'=>'ROUTE','SUD'=>'CHAMP','OUEST'=>'CHAMP','CENTRE'=>'RIEN'],
    'liaisons' => [
      ['type'=>'ROUTE','groupe'=>['NORD','EST']],
    ],
  ],

// ---debut : (19) Route en T (village) — 3 routes borgnes (le village arrête chaque route) ---
'R19_ROUTE_T_VILLAGE' => [
  'cotes' => [
    'NORD'   => 'ROUTE',
    'EST'    => 'ROUTE',
    'SUD'    => 'ROUTE',
    'OUEST'  => 'CHAMP',
    'CENTRE' => 'VILLAGE',
  ],
  'liaisons' => [
    // trois routes DISTINCTES (pas de CENTRE dans les groupes)
    ['type'=>'ROUTE','groupe'=>['NORD']],
    ['type'=>'ROUTE','groupe'=>['EST']],
    ['type'=>'ROUTE','groupe'=>['SUD']],

    // le village est sa propre feature
    ['type'=>'VILLAGE','groupe'=>['CENTRE']],
  ],
],

  // ---debut : (20) Carrefour 4 routes (village) — 4 routes borgnes (le village coupe tout) ---
'R20_ROUTE_4_VILLAGE' => [
  'cotes' => [
    'NORD'   => 'ROUTE',
    'EST'    => 'ROUTE',
    'SUD'    => 'ROUTE',
    'OUEST'  => 'ROUTE',
    'CENTRE' => 'VILLAGE',
  ],
  'liaisons' => [
    // ⚠️ quatre routes DISTINCTES (pas de CENTRE dans les groupes ROUTE)
    ['type'=>'ROUTE','groupe'=>['NORD']],
    ['type'=>'ROUTE','groupe'=>['EST']],
    ['type'=>'ROUTE','groupe'=>['SUD']],
    ['type'=>'ROUTE','groupe'=>['OUEST']],

    // le village est sa propre feature (et sert d'arrêt)
    ['type'=>'VILLAGE','groupe'=>['CENTRE']],
  ],
],
// --- fin ---


// --- fin du bloc 7 ---
];

// Fournit aussi une fonction d’accès si besoin
if (!function_exists('get_models')) {
  function get_models(): array { global $MODELES; return $MODELES; }
}
