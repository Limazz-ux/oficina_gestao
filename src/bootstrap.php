<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../config/database.php';

function me() { return $_SESSION['usuario'] ?? null; }
function requireLogin() {
    if (!me()) { header('Location: index.php'); exit; }
}
function admin() {
    if (!me() || me()['perfil'] !== 'admin') {
        http_response_code(403);
        exit('Acesso negado.');
    }
}
function csrf() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function checkPost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !is_string($_POST['csrf'] ?? null) ||
        !hash_equals(csrf(), $_POST['csrf'])) {
        http_response_code(403);
        exit('Requisição inválida.');
    }
}
function h($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
function audit(PDO $pdo, string $acao, string $entidade, int $id) {
    $stmt = $pdo->prepare(
        'INSERT INTO auditoria (usuario_id, acao, entidade, registro_id)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([me()['id'], $acao, $entidade, $id]);
}
function redirect(string $mensagem) {
    $_SESSION['flash'] = $mensagem;
    header('Location: painel.php');
    exit;
}
function all(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
