<?php
//--- debut du bloc 1 : bootstrap
require __DIR__ . '/config.php';
//--- fin du bloc 1

//--- debut du bloc 2 : CSRF helper
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function check_csrf($t){return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? '');}
//--- fin du bloc 2

//--- debut du bloc 3 : si déjà connecté -> rediriger
if (!empty($_SESSION['user'])) {
  header('Location: lobby.php'); // adapte vers ta page après connexion
  exit;
}
//--- fin du bloc 3

//--- debut du bloc 4 : traitement
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? '')) {
    $errors[] = "Jeton CSRF invalide.";
  } else {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($login === '' || $pass === '') {
      $errors[] = "Identifiants requis.";
    } else {
      // login par email OU pseudo
      $stmt = $pdo->prepare("SELECT id, email, username, password_hash FROM users WHERE email = ? OR username = ? LIMIT 1");
      $stmt->execute([$login, $login]);
      $user = $stmt->fetch();
      if (!$user || !password_verify($pass, $user['password_hash'])) {
        $errors[] = "Email/pseudo ou mot de passe incorrect.";
      } else {
        // connexion OK
        $_SESSION['user'] = [
          'id'       => $user['id'],
          'email'    => $user['email'],
          'username' => $user['username'],
        ];
        // protection fixation de session
        session_regenerate_id(true);
        header('Location: lobby.php'); // adapte si besoin
        exit;
      }
    }
  }
}
//--- fin du bloc 4
?>
<!doctype html>
<html lang="fr">
<head>
  <!----- debut du bloc 5 : head -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Connexion</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;max-width:560px;margin:2rem auto;padding:0 1rem}
    form{display:grid;gap:.75rem}
    input,button{padding:.6rem .8rem;font-size:1rem}
    .errors{background:#ffe8e8;border:1px solid #f5b5b5;padding:.75rem}
    a{color:#0a58ca;text-decoration:none}
  </style>
  <!----- fin du bloc 5 -->
</head>
<body>
  <!----- debut du bloc 6 : UI -->
  <h1>Connexion</h1>

  <?php if ($errors): ?>
    <div class="errors">
      <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
    <label>Email ou Pseudo<br><input type="text" name="login" required></label>
    <label>Mot de passe<br><input type="password" name="password" required></label>
    <button type="submit">Se connecter</button>
    <p>Pas encore de compte ? <a href="inscription.php">Inscription</a></p>
  </form>
  <!----- fin du bloc 6 -->
</body>
</html>
