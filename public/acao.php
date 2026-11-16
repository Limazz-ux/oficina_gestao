<?php
require __DIR__ . '/../src/bootstrap.php';
requireLogin();
checkPost();

$tipo = $_POST['tipo'] ?? '';
$acao = $_POST['acao'] ?? 'criar';
$id = (int) ($_POST['id'] ?? 0);

try {
    if (!in_array($acao, ['criar', 'editar', 'excluir'], true)) {
        throw new Exception('Ação inválida.');
    }

    if ($tipo === 'usuario') {
        admin();
        if ($acao !== 'criar') { throw new Exception('Ação de usuário inválida.'); }
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? '';
        if (!$nome || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($senha) < 8 || !in_array($perfil, ['admin', 'mecanico'], true)) {
            throw new Exception('Dados de usuário inválidos.');
        }
        $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
        audit($pdo, 'criar', 'usuario', (int) $pdo->lastInsertId());

    } elseif ($tipo === 'cliente') {
        admin();
        if ($acao === 'excluir') {
            $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
        } else {
            $nome = trim($_POST['nome'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            if (!$nome) { throw new Exception('Informe o nome do cliente.'); }
            if ($acao === 'editar') {
                $pdo->prepare('UPDATE clientes SET nome = ?, telefone = ? WHERE id = ?')
                    ->execute([$nome, $telefone, $id]);
            } else {
                $pdo->prepare('INSERT INTO clientes (nome, telefone) VALUES (?, ?)')
                    ->execute([$nome, $telefone]);
                $id = (int) $pdo->lastInsertId();
            }
        }
        audit($pdo, $acao, 'cliente', $id);

    } elseif ($tipo === 'veiculo') {
        admin();
        if ($acao === 'excluir') {
            $pdo->prepare('DELETE FROM veiculos WHERE id = ?')->execute([$id]);
        } else {
            $cliente = (int) ($_POST['cliente_id'] ?? 0);
            $placa = strtoupper(trim($_POST['placa'] ?? ''));
            $modelo = trim($_POST['modelo'] ?? '');
            if (!$cliente || !$placa || !$modelo) {
                throw new Exception('Preencha os dados do veículo.');
            }
            if ($acao === 'editar') {
                $pdo->prepare('UPDATE veiculos SET cliente_id = ?, placa = ?, modelo = ? WHERE id = ?')
                    ->execute([$cliente, $placa, $modelo, $id]);
            } else {
                $pdo->prepare('INSERT INTO veiculos (cliente_id, placa, modelo) VALUES (?, ?, ?)')
                    ->execute([$cliente, $placa, $modelo]);
                $id = (int) $pdo->lastInsertId();
            }
        }
        audit($pdo, $acao, 'veiculo', $id);

    } elseif ($tipo === 'ordem') {
        if ($acao === 'excluir') {
            admin();
            $pdo->prepare('DELETE FROM ordens WHERE id = ?')->execute([$id]);
            audit($pdo, 'excluir', 'ordem', $id);
            redirect('Ordem excluída.');
        }

        $situacao = $_POST['situacao'] ?? 'agendada';
        $situacoes = ['agendada', 'em_andamento', 'concluida', 'cancelada'];
        if (!in_array($situacao, $situacoes, true)) {
            throw new Exception('Situação inválida.');
        }

        if (me()['perfil'] === 'mecanico') {
            if ($acao !== 'editar') { admin(); }
            $stmt = $pdo->prepare('SELECT mecanico_id FROM ordens WHERE id = ?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() !== (int) me()['id']) {
                throw new Exception('Você só pode atualizar suas próprias ordens.');
            }
            $pdo->prepare('UPDATE ordens SET situacao = ? WHERE id = ?')
                ->execute([$situacao, $id]);
            audit($pdo, 'atualizar', 'ordem', $id);
            redirect('Situação atualizada.');
        }

        admin();
        $veiculo = (int) ($_POST['veiculo_id'] ?? 0);
        $mecanico = (int) ($_POST['mecanico_id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $data = str_replace('T', ' ', trim($_POST['agendamento'] ?? ''));
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data);
        if (!$veiculo || !$mecanico || !$descricao || !$dt ||
            $dt->format('Y-m-d H:i') !== $data) {
            throw new Exception('Preencha os dados válidos da ordem.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND perfil = 'mecanico' FOR UPDATE");
        $stmt->execute([$mecanico]);
        if (!$stmt->fetch()) { throw new Exception('Selecione um mecânico válido.'); }

        $stmt = $pdo->prepare(
            "SELECT id FROM ordens WHERE mecanico_id = ? AND agendamento = ?
             AND id <> ? AND situacao <> 'cancelada' LIMIT 1"
        );
        $stmt->execute([$mecanico, $data, $id]);
        if ($situacao !== 'cancelada' && $stmt->fetch()) {
            throw new Exception('O mecânico já possui uma ordem nesse horário.');
        }

        if ($acao === 'editar') {
            $pdo->prepare(
                'UPDATE ordens SET veiculo_id = ?, mecanico_id = ?, descricao = ?,
                 agendamento = ?, situacao = ? WHERE id = ?'
            )->execute([$veiculo, $mecanico, $descricao, $data, $situacao, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO ordens (veiculo_id, mecanico_id, descricao, agendamento, situacao, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$veiculo, $mecanico, $descricao, $data, $situacao, me()['id']]);
            $id = (int) $pdo->lastInsertId();
        }
        audit($pdo, $acao, 'ordem', $id);
        $pdo->commit();

    } else {
        throw new Exception('Operação desconhecida.');
    }
    redirect('Operação realizada com sucesso.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($e->getMessage());
    redirect('Não foi possível concluir a operação: ' . $e->getMessage());
}
