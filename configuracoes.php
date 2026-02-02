<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Página de Configurações do Sistema
 */

require_once 'config.php';
Auth::requireAdmin();

$db = Database::getConnectionBV();
$mensagem = '';
$tipo_mensagem = '';

// Processar formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $chave => $valor) {
            if (strpos($chave, 'config_') === 0) {
                $chave_real = str_replace('config_', '', $chave);
                $stmt = $db->prepare("UPDATE configuracoes SET valor = :valor, atualizado_em = NOW() WHERE chave = :chave");
                $stmt->execute([':valor' => $valor, ':chave' => $chave_real]);
            }
        }

        Logger::log(null, 'configuracoes_alteradas', 'Alterou as configurações do sistema');
        $mensagem = 'Configurações salvas com sucesso!';
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar configurações: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Buscar configurações
$stmt = $db->query("SELECT * FROM configuracoes ORDER BY id");
$configuracoes = [];
while ($row = $stmt->fetch()) {
    $configuracoes[$row['chave']] = $row;
}

// Testar conexão com banco da API
$conexao_api_ok = false;
$conexao_api_msg = '';
try {
    $db_api = Database::getConnectionAPI();
    $conexao_api_ok = true;
    $conexao_api_msg = 'Conexão OK';
} catch (Exception $e) {
    $conexao_api_msg = 'Erro: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Boas-Vindas Aquabeat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .config-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .config-item label {
            font-weight: 600;
            color: #333;
        }
        .config-item small {
            color: #6c757d;
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index">
                <i class="bi bi-hand-thumbs-up-fill"></i> Boas-Vindas Aquabeat
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index"><i class="bi bi-house-fill"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="usuarios"><i class="bi bi-people-fill"></i> Usuários</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="relatorios"><i class="bi bi-graph-up"></i> Relatórios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="configuracoes"><i class="bi bi-gear-fill"></i> Configurações</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(Auth::getUserName()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil"><i class="bi bi-person"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show">
            <i class="bi bi-<?= $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Status das Conexões -->
            <div class="col-lg-4">
                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-database"></i> Status das Conexões</h5>

                    <div class="mb-4">
                        <h6>Banco de Dados Principal</h6>
                        <div class="d-flex align-items-center justify-content-between">
                            <span>mcaq_uaboasvindas</span>
                            <span class="status-badge bg-success text-white">
                                <i class="bi bi-check-circle"></i> Conectado
                            </span>
                        </div>
                        <small class="text-muted">Host: <?= BV_DB_HOST ?></small>
                    </div>

                    <div class="mb-4">
                        <h6>Banco de Dados API (Cotas)</h6>
                        <div class="d-flex align-items-center justify-content-between">
                            <span><?= API_DB_NAME ?></span>
                            <span class="status-badge bg-<?= $conexao_api_ok ? 'success' : 'danger' ?> text-white">
                                <i class="bi bi-<?= $conexao_api_ok ? 'check-circle' : 'x-circle' ?>"></i>
                                <?= $conexao_api_ok ? 'Conectado' : 'Erro' ?>
                            </span>
                        </div>
                        <small class="text-muted">Host: <?= API_DB_HOST ?></small>
                        <?php if (!$conexao_api_ok): ?>
                        <div class="alert alert-danger mt-2 mb-0 py-2">
                            <small><?= htmlspecialchars($conexao_api_msg) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <h6>Informações do Sistema</h6>
                    <table class="table table-sm">
                        <tr><td>PHP Version</td><td><?= phpversion() ?></td></tr>
                        <tr><td>Timezone</td><td><?= TIMEZONE ?></td></tr>
                        <tr><td>Modo</td><td><?= DESENVOLVIMENTO ? 'Desenvolvimento' : 'Produção' ?></td></tr>
                    </table>
                </div>

                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-info-circle"></i> Configuração do Banco API</h5>
                    <p class="text-muted small">
                        Para alterar as credenciais de conexão com o banco de dados das cotas,
                        edite o arquivo <code>config.php</code> no servidor.
                    </p>
                    <div class="bg-light p-3 rounded">
                        <code>
                            API_DB_HOST: <?= API_DB_HOST ?><br>
                            API_DB_NAME: <?= API_DB_NAME ?><br>
                            API_DB_USER: <?= API_DB_USER ?>
                        </code>
                    </div>
                </div>
            </div>

            <!-- Configurações do Sistema -->
            <div class="col-lg-8">
                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-gear-fill"></i> Configurações do Sistema</h5>

                    <form method="POST">
                        <?php foreach ($configuracoes as $chave => $config): ?>
                        <div class="config-item">
                            <label for="config_<?= $chave ?>" class="form-label">
                                <?= htmlspecialchars($config['descricao']) ?>
                            </label>

                            <?php if ($config['tipo'] === 'numero'): ?>
                            <input type="number" class="form-control" id="config_<?= $chave ?>"
                                   name="config_<?= $chave ?>" value="<?= htmlspecialchars($config['valor']) ?>">
                            <?php elseif ($config['tipo'] === 'booleano'): ?>
                            <select class="form-select" id="config_<?= $chave ?>" name="config_<?= $chave ?>">
                                <option value="1" <?= $config['valor'] == '1' ? 'selected' : '' ?>>Sim</option>
                                <option value="0" <?= $config['valor'] == '0' ? 'selected' : '' ?>>Não</option>
                            </select>
                            <?php elseif ($config['tipo'] === 'textarea'): ?>
                            <textarea class="form-control" id="config_<?= $chave ?>"
                                      name="config_<?= $chave ?>" rows="3"><?= htmlspecialchars($config['valor']) ?></textarea>
                            <?php else: ?>
                            <input type="text" class="form-control" id="config_<?= $chave ?>"
                                   name="config_<?= $chave ?>" value="<?= htmlspecialchars($config['valor']) ?>">
                            <?php endif; ?>

                            <small>Chave: <code><?= $chave ?></code> | Última atualização: <?= formatarData($config['atualizado_em']) ?></small>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($configuracoes)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Nenhuma configuração encontrada na tabela.
                        </div>
                        <?php else: ?>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar Configurações
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
