<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Página de Configurações do Sistema
 */

require_once 'config.php';
Auth::requireAdmin();

$pagina_atual = 'configuracoes';
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

// Buscar configurações (excluindo api_endpoint que não é usado)
$stmt = $db->query("SELECT * FROM configuracoes WHERE chave != 'api_endpoint' ORDER BY id");
$configuracoes = [];
while ($row = $stmt->fetch()) {
    $configuracoes[$row['chave']] = $row;
}

// Testar conexão com banco da API e buscar tabelas
$conexao_api_ok = false;
$conexao_api_msg = '';
$tabelas_api = [];
$tabelas_bv = [];

try {
    $db_api = Database::getConnectionAPI();
    $conexao_api_ok = true;
    $conexao_api_msg = 'Conexão OK';

    // Buscar tabelas do banco API
    $stmt_tables = $db_api->query("SHOW TABLES");
    $tabelas_api = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $conexao_api_msg = 'Erro: ' . $e->getMessage();
}

// Buscar tabelas do banco BV
try {
    $stmt_tables_bv = $db->query("SHOW TABLES");
    $tabelas_bv = $stmt_tables_bv->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Silenciar
}

// Contar registros principais
$total_usuarios = 0;
$total_boasvindas = 0;
$total_logs = 0;

try {
    $total_usuarios = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $total_boasvindas = $db->query("SELECT COUNT(*) FROM boas_vindas")->fetchColumn();
    $total_logs = $db->query("SELECT COUNT(*) FROM logs_atividades")->fetchColumn();
} catch (Exception $e) {
    // Silenciar
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
        .config-item label { font-weight: 600; color: #333; }
        .config-item small { color: #6c757d; }
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        .table-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .table-item {
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 4px;
            margin: 3px 0;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

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

                    <!-- Banco Principal -->
                    <div class="mb-4">
                        <h6>Banco de Dados Principal</h6>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span><strong><?= BV_DB_NAME ?></strong></span>
                            <span class="status-badge bg-success text-white">
                                <i class="bi bi-check-circle"></i> Conectado
                            </span>
                        </div>
                        <small class="text-muted d-block">Host: <?= BV_DB_HOST ?> | User: <?= BV_DB_USER ?></small>

                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-people"></i> <?= $total_usuarios ?> usuários |
                                <i class="bi bi-clipboard-check"></i> <?= $total_boasvindas ?> boas-vindas |
                                <i class="bi bi-journal-text"></i> <?= $total_logs ?> logs
                            </small>
                        </div>

                        <details class="mt-2">
                            <summary class="text-primary" style="cursor:pointer">Ver tabelas (<?= count($tabelas_bv) ?>)</summary>
                            <div class="table-list mt-2">
                                <?php foreach ($tabelas_bv as $tabela): ?>
                                <div class="table-item"><i class="bi bi-table"></i> <?= htmlspecialchars($tabela) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>

                    <hr>

                    <!-- Banco API -->
                    <div class="mb-4">
                        <h6>Banco de Dados API (Vendas/Cotas)</h6>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span><strong><?= API_DB_NAME ?></strong></span>
                            <span class="status-badge bg-<?= $conexao_api_ok ? 'success' : 'danger' ?> text-white">
                                <i class="bi bi-<?= $conexao_api_ok ? 'check-circle' : 'x-circle' ?>"></i>
                                <?= $conexao_api_ok ? 'Conectado' : 'Erro' ?>
                            </span>
                        </div>
                        <small class="text-muted d-block">Host: <?= API_DB_HOST ?> | User: <?= API_DB_USER ?></small>

                        <?php if (!$conexao_api_ok): ?>
                        <div class="alert alert-danger mt-2 mb-0 py-2">
                            <small><?= htmlspecialchars($conexao_api_msg) ?></small>
                        </div>
                        <?php elseif (count($tabelas_api) > 0): ?>
                        <details class="mt-2">
                            <summary class="text-primary" style="cursor:pointer">Ver tabelas (<?= count($tabelas_api) ?>)</summary>
                            <div class="table-list mt-2">
                                <?php foreach ($tabelas_api as $tabela): ?>
                                <div class="table-item">
                                    <i class="bi bi-table"></i> <?= htmlspecialchars($tabela) ?>
                                    <?php if ($tabela === 'titulos_analise'): ?>
                                    <span class="badge bg-success ms-1">usada</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <h6>Informações do Sistema</h6>
                    <table class="table table-sm mb-0">
                        <tr><td>PHP Version</td><td><?= phpversion() ?></td></tr>
                        <tr><td>Timezone</td><td><?= TIMEZONE ?></td></tr>
                        <tr><td>Modo</td><td>
                            <span class="badge bg-<?= DESENVOLVIMENTO ? 'warning' : 'success' ?>">
                                <?= DESENVOLVIMENTO ? 'Desenvolvimento' : 'Produção' ?>
                            </span>
                        </td></tr>
                    </table>
                </div>

                <div class="content-section">
                    <h5 class="mb-3"><i class="bi bi-info-circle"></i> Credenciais do Banco API</h5>
                    <p class="text-muted small">
                        Para alterar, edite o arquivo <code>config.php</code> no servidor.
                    </p>
                    <div class="bg-light p-3 rounded">
                        <code class="d-block">API_DB_HOST: <?= API_DB_HOST ?></code>
                        <code class="d-block">API_DB_NAME: <?= API_DB_NAME ?></code>
                        <code class="d-block">API_DB_USER: <?= API_DB_USER ?></code>
                    </div>
                </div>
            </div>

            <!-- Configurações do Sistema -->
            <div class="col-lg-8">
                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-gear-fill"></i> Configurações do Sistema</h5>

                    <?php
                    // Labels amigáveis para as configurações
                    $labels = [
                        'empresa_nome' => 'Nome da Empresa',
                        'empresa_email' => 'E-mail da Empresa',
                        'empresa_telefone' => 'Telefone da Empresa',
                        'empresa_whatsapp' => 'WhatsApp da Empresa',
                        'filtro_data_padrao' => 'Período Padrão no Dashboard',
                        'filtro_data_inicio' => 'Data Início (personalizado)',
                        'filtro_data_fim' => 'Data Fim (personalizado)',
                        'mostrar_selector_periodo' => 'Mostrar seletor de período no Dashboard',
                        'portal_url' => 'URL do Portal do Cliente',
                        'whatsapp_numero' => 'WhatsApp para Scripts',
                        'telefone_atendimento' => 'Telefone para Scripts',
                        'email_atendimento' => 'E-mail para Scripts'
                    ];

                    // Opções para filtro_data_padrao
                    $opcoes_periodo = [
                        'mes_atual' => 'Mês Atual',
                        'mes_anterior' => 'Mês Anterior',
                        'ultimos_30_dias' => 'Últimos 30 dias',
                        'ultimos_60_dias' => 'Últimos 60 dias',
                        'intervalo_personalizado' => 'Intervalo Personalizado'
                    ];
                    ?>
                    <form method="POST">
                        <?php foreach ($configuracoes as $chave => $config):
                            $valor = $config['valor'] ?? '';
                            $label = $labels[$chave] ?? $config['descricao'];
                        ?>
                        <div class="config-item">
                            <label for="config_<?= $chave ?>" class="form-label">
                                <?= htmlspecialchars($label) ?>
                            </label>

                            <?php if ($chave === 'filtro_data_padrao'): ?>
                            <!-- Dropdown para período -->
                            <select class="form-select" id="config_<?= $chave ?>" name="config_<?= $chave ?>">
                                <?php foreach ($opcoes_periodo as $opt_val => $opt_label): ?>
                                <option value="<?= $opt_val ?>" <?= $valor === $opt_val ? 'selected' : '' ?>><?= $opt_label ?></option>
                                <?php endforeach; ?>
                            </select>

                            <?php elseif ($chave === 'mostrar_selector_periodo' || $config['tipo'] === 'boolean' || $config['tipo'] === 'booleano'): ?>
                            <!-- Toggle para boolean -->
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="config_<?= $chave ?>" name="config_<?= $chave ?>"
                                       value="1" <?= $valor == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="config_<?= $chave ?>">
                                    <?= $valor == '1' ? 'Ativado' : 'Desativado' ?>
                                </label>
                            </div>

                            <?php elseif (strpos($chave, 'data_') !== false && strpos($chave, 'filtro_') !== false): ?>
                            <!-- Date picker para datas -->
                            <input type="date" class="form-control" id="config_<?= $chave ?>"
                                   name="config_<?= $chave ?>" value="<?= htmlspecialchars($valor) ?>">
                            <small class="text-muted">Usado apenas quando período = "Intervalo Personalizado"</small>

                            <?php elseif ($config['tipo'] === 'numero'): ?>
                            <input type="number" class="form-control" id="config_<?= $chave ?>"
                                   name="config_<?= $chave ?>" value="<?= htmlspecialchars($valor) ?>">

                            <?php elseif ($config['tipo'] === 'textarea'): ?>
                            <textarea class="form-control" id="config_<?= $chave ?>"
                                      name="config_<?= $chave ?>" rows="3"><?= htmlspecialchars($valor) ?></textarea>

                            <?php else: ?>
                            <input type="text" class="form-control" id="config_<?= $chave ?>"
                                   name="config_<?= $chave ?>" value="<?= htmlspecialchars($valor) ?>">
                            <?php endif; ?>

                            <small class="text-muted">Atualizado: <?= formatarData($config['atualizado_em'] ?? '') ?></small>
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
