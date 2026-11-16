<?php
require __DIR__ . '/../src/bootstrap.php';
requireLogin();

$isAdmin = me()['perfil'] === 'admin';
$clientes = all($pdo, 'SELECT * FROM clientes ORDER BY nome');
$veiculos = all($pdo, 'SELECT v.*, c.nome AS cliente FROM veiculos v JOIN clientes c ON c.id = v.cliente_id ORDER BY v.modelo');
$mecanicos = all($pdo, "SELECT id, nome FROM usuarios WHERE perfil = 'mecanico' ORDER BY nome");

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$limite = 8;
$nome = trim($_GET['cliente'] ?? '');
$data = trim($_GET['data'] ?? '');
$where = [];
$args = [];
if ($nome !== '') { $where[] = 'c.nome LIKE ?'; $args[] = "%$nome%"; }
if ($data !== '') { $where[] = 'DATE(o.agendamento) = ?'; $args[] = $data; }
if (!$isAdmin) { $where[] = 'o.mecanico_id = ?'; $args[] = me()['id']; }
$filtro = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$base = ' FROM ordens o JOIN veiculos v ON v.id = o.veiculo_id
          JOIN clientes c ON c.id = v.cliente_id JOIN usuarios u ON u.id = o.mecanico_id';
$stmt = $pdo->prepare('SELECT COUNT(*)' . $base . $filtro);
$stmt->execute($args);
$total = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / $limite));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $limite;
$stmt = $pdo->prepare(
    'SELECT o.*, v.modelo, v.placa, c.nome AS cliente, u.nome AS mecanico'
    . $base . $filtro . ' ORDER BY o.agendamento DESC LIMIT ' . $limite . ' OFFSET ' . $offset
);
$stmt->execute($args);
$ordens = $stmt->fetchAll();
$situacoes = ['agendada' => 'Agendada', 'em_andamento' => 'Em andamento',
              'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel da Oficina</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div>
        <h1>🔧 Oficina Gestão</h1>
        <small>Olá, <?= h(me()['nome']) ?> · <?= h(me()['perfil']) ?></small>
    </div>
    <a href="logout.php">Sair</a>
</header>
<main>
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alerta"><?= h($_SESSION['flash']) ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <section class="hero">
        <h2>Controle de atendimentos</h2>
        <p>Clientes, veículos e ordens de serviço em um só lugar.</p>
        <div class="metrics">
            <span><b><?= count($clientes) ?></b> clientes</span>
            <span><b><?= count($veiculos) ?></b> veículos</span>
            <span><b><?= $total ?></b> ordens filtradas</span>
        </div>
    </section>

    <?php if ($isAdmin): ?>
    <div class="grid">
        <section class="card">
            <h3>Novo cliente</h3>
            <form action="acao.php" method="post">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="tipo" value="cliente">
                <label for="cliente-nome">Nome</label>
                <input id="cliente-nome" name="nome" required>
                <label for="cliente-telefone">Telefone</label>
                <input id="cliente-telefone" name="telefone">
                <button type="submit">Cadastrar cliente</button>
            </form>
        </section>
        <section class="card">
            <h3>Novo veículo</h3>
            <form action="acao.php" method="post">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="tipo" value="veiculo">
                <label for="veiculo-cliente">Proprietário</label>
                <select id="veiculo-cliente" name="cliente_id" required>
                    <option value="">Selecione o proprietário</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= h($c['id']) ?>"><?= h($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="placa">Placa</label>
                <input id="placa" name="placa" required>
                <label for="modelo">Modelo</label>
                <input id="modelo" name="modelo" required>
                <button type="submit">Cadastrar veículo</button>
            </form>
        </section>
        <section class="card">
            <h3>Novo mecânico ou administrador</h3>
            <form action="acao.php" method="post">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="tipo" value="usuario">
                <label for="usuario-nome">Nome</label>
                <input id="usuario-nome" name="nome" required>
                <label for="usuario-email">E-mail</label>
                <input id="usuario-email" type="email" name="email" required>
                <label for="usuario-senha">Senha</label>
                <input id="usuario-senha" type="password" name="senha" minlength="8" required>
                <label for="perfil">Perfil</label>
                <select id="perfil" name="perfil">
                    <option value="mecanico">Mecânico</option>
                    <option value="admin">Administrador</option>
                </select>
                <button type="submit">Cadastrar usuário</button>
            </form>
        </section>
        <section class="card">
            <h3>Nova ordem de serviço</h3>
            <form action="acao.php" method="post">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="tipo" value="ordem">
                <label for="veiculo">Veículo</label>
                <select id="veiculo" name="veiculo_id" required>
                    <option value="">Selecione o veículo</option>
                    <?php foreach ($veiculos as $v): ?>
                        <option value="<?= h($v['id']) ?>"><?= h($v['modelo'] . ' · ' . $v['placa']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="mecanico">Mecânico</label>
                <select id="mecanico" name="mecanico_id" required>
                    <option value="">Selecione o mecânico</option>
                    <?php foreach ($mecanicos as $m): ?>
                        <option value="<?= h($m['id']) ?>"><?= h($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="descricao">Descrição do serviço</label>
                <textarea id="descricao" name="descricao" required></textarea>
                <label for="agendamento">Agendamento</label>
                <input id="agendamento" type="datetime-local" name="agendamento" required>
                <button type="submit">Agendar serviço</button>
            </form>
        </section>
    </div>
    <?php endif; ?>

    <section class="card">
        <h2>Ordens de serviço</h2>
        <form method="get" class="filters">
            <label for="filtro-cliente">Cliente</label>
            <input id="filtro-cliente" name="cliente" value="<?= h($nome) ?>" placeholder="Filtrar por cliente">
            <label for="filtro-data">Data</label>
            <input id="filtro-data" type="date" name="data" value="<?= h($data) ?>">
            <button type="submit">Filtrar</button>
            <a href="painel.php">Limpar</a>
        </form>
        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Nº</th><th>Cliente</th><th>Veículo</th><th>Serviço</th>
                        <th>Mecânico</th><th>Agendamento</th><th>Situação / ações</th>
                    </tr>
                </thead>
                <tbody>

<?php foreach ($ordens as $o): ?>
    <tr>
        <td>#<?= h($o['id']) ?></td>
        <td><?= h($o['cliente']) ?></td>
        <td><?= h($o['modelo'] . ' · ' . $o['placa']) ?></td>
        <td><?= h($o['descricao']) ?></td>
        <td><?= h($o['mecanico']) ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime($o['agendamento']))) ?></td>

        <td>
            <form action="acao.php" method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="tipo" value="ordem">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" value="<?= h($o['id']) ?>">
                <input type="hidden" name="veiculo_id" value="<?= h($o['veiculo_id']) ?>">
                <input type="hidden" name="mecanico_id" value="<?= h($o['mecanico_id']) ?>">
                <input type="hidden" name="descricao" value="<?= h($o['descricao']) ?>">
                <input
                    type="hidden"
                    name="agendamento"
                    value="<?= h(date('Y-m-d\TH:i', strtotime($o['agendamento']))) ?>"
                >

                <select name="situacao" aria-label="Situação da ordem">
                    <?php foreach ($situacoes as $valor => $rotulo): ?>
                        <option
                            value="<?= h($valor) ?>"
                            <?= $o['situacao'] === $valor ? 'selected' : '' ?>
                        >
                            <?= h($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Salvar</button>
            </form>

            <?php if ($isAdmin): ?>
                <details>
                    <summary>Editar agendamento</summary>

                    <form action="acao.php" method="post">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="tipo" value="ordem">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" value="<?= h($o['id']) ?>">

                        <label>
                            Veículo

                            <select name="veiculo_id" required>
                                <?php foreach ($veiculos as $v): ?>
                                    <option
                                        value="<?= h($v['id']) ?>"
                                        <?= $v['id'] == $o['veiculo_id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($v['modelo'] . ' · ' . $v['placa']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Mecânico

                            <select name="mecanico_id" required>
                                <?php foreach ($mecanicos as $m): ?>
                                    <option
                                        value="<?= h($m['id']) ?>"
                                        <?= $m['id'] == $o['mecanico_id'] ? 'selected' : '' ?>
                                    >
                                        <?= h($m['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Descrição

                            <textarea name="descricao" required><?= h($o['descricao']) ?></textarea>
                        </label>

                        <label>
                            Agendamento

                            <input
                                type="datetime-local"
                                name="agendamento"
                                value="<?= h(date('Y-m-d\TH:i', strtotime($o['agendamento']))) ?>"
                                required
                            >
                        </label>

                        <input
                            type="hidden"
                            name="situacao"
                            value="<?= h($o['situacao']) ?>"
                        >

                        <button type="submit">Atualizar ordem</button>
                    </form>
                </details>

                <form
                    action="acao.php"
                    method="post"
                    onsubmit="return confirm('Excluir esta ordem?')"
                >
                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="tipo" value="ordem">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= h($o['id']) ?>">

                    <button type="submit" class="danger">
                        Excluir
                    </button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <?php if (!$ordens): ?>
            <p>Nenhuma ordem encontrada.</p>
        <?php endif; ?>

        <nav class="pages" aria-label="Paginação">
            <?php for ($i = 1; $i <= $paginas; $i++): ?>
                <a
                    class="<?= $i === $pagina ? 'current' : '' ?>"
                    href="?<?= h(http_build_query([
                        'cliente' => $nome,
                        'data' => $data,
                        'pagina' => $i
                    ])) ?>"
                >
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </nav>
    </section>

    <div class="grid">
        <section class="card">
            <h2>Clientes</h2>

            <?php foreach ($clientes as $c): ?>
                <p>
                    <?= h($c['nome']) ?> · <?= h($c['telefone']) ?>
                </p>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2>Veículos</h2>

            <?php foreach ($veiculos as $v): ?>
                <p>
                    <?= h($v['modelo'] . ' · ' . $v['placa'] . ' · ' . $v['cliente']) ?>
                </p>
            <?php endforeach; ?>
        </section>
    </div>
</main>
</body>
</html>

