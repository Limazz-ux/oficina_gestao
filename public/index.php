<?php
require __DIR__ . '/../src/bootstrap.php';
if (me()) { header('Location: painel.php'); exit; }
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkPost();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([trim($_POST['email'] ?? '')]);
    $usuario = $stmt->fetch();
    if ($usuario && password_verify($_POST['senha'] ?? '', $usuario['senha'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'perfil' => $usuario['perfil']
        ];
        header('Location: painel.php');
        exit;
    }
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oficina | Entrar</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="login">
        <h1>🔧 Oficina Gestão</h1>
        <p>Entre para gerenciar os atendimentos.</p>
        <?php if ($erro): ?><p class="erro"><?= h($erro) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" autocomplete="username" required>
            <label for="senha">Senha</label>
            <input id="senha" type="password" name="senha" autocomplete="current-password" required>
            <button type="submit">Entrar</button>
        </form>
            <a class ="criarConta"href="criar_admin.php">Não possui uma conta? Crie uma</a>
    </main>
</body>
</html>
