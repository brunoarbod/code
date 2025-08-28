# Carca-like v6

> JS côté client (rendu + interactions), **traitements 100% en PHP**.  
> Style V4 des tuiles conservé (grille 3×3, types `.route/.ville/.champ/.village`, overlay SVG).  
> Convention de commentaires : `//---debut du bloc …` / `//--- fin du bloc …`.

---

## Sommaire

- [Aperçu](#aperçu)
- [Fonctionnalités](#fonctionnalités)
- [Architecture & fichiers](#architecture--fichiers)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Lancer en local](#lancer-en-local)
- [API (contrats)](#api-contrats)
  - [Lobby (`lobby_api.php`)](#lobby-lobby_apiphp)
  - [Jeu/turns (`game_api.php`)](#jeuturns-game_apiphp)
  - [Pose tuile (`pose_tuile.php`)](#pose-tuile-pose_tuilephp)
- [Format des données](#format-des-données)
- [Pages](#pages)
- [Style V4 des tuiles](#st)
# architecture
.
├── auth.php # (inchangé) Auth/session si besoin
├── config.php # Helpers + ENV + JSON IO + constantes
├── config_models.php # Définition des modèles de tuile ($MODELES)
├── game_api.php # API état de jeu (phases/lock/version)
├── lobby.php # Page Lobby (JS intégré)
├── lobby_api.php # API Lobby (host/join/ready/start/...)
├── jeu.php # Page Jeu (JS intégré + style V4 tuiles)
├── pose_tuile.php # Traitement de pose (compat bords + DSU + score)
├── style.css # Style global + classes V4 tuiles
├── game_state.json # État partie (version/turn/players/...)
├── plateau.json # Plateau (tuiles/features/scores)
├── lobby_state.json # État lobby (parties, version)
└── lecture_plateau.php # (optionnel) Outil de diagnostic/exports


**Convention blocs** dans les fichiers :  
`//---debut du bloc X : nom ---` … `//--- fin du bloc X ---`
