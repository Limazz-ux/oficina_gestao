<?php
require __DIR__ . '/../src/bootstrap.php';
$total = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($total > 0) { http_response_code(403); exit('Cadastro inicial já realizado.'); }
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkPost();
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
        $erro = 'Informe nome, e-mail válido e senha com pelo menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, 'admin')"
        );
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeiro administrador</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="login">
        <h1>Primeiro administrador</h1>
        <?php if ($erro): ?><p class="erro"><?= h($erro) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label for="nome">Nome</label>
            <input id="nome" name="nome" required>
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" required>
            <label for="senha">Senha</label>
            <input id="senha" type="password" name="senha" minlength="8" required>
            <button type="submit">Criar administrador</button>
        </form>
    </main>
</body>
</html>
