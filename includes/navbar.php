<?php
/**
 * NAVBAR CENTRALIZADO
 * Include este arquivo em todas as páginas após carregar config.php
 */

// Base URL absoluta para links
$nav_base = '/boasvindas';

// Definir página atual se não foi definida
if (!isset($pagina_atual)) {
    $pagina_atual = '';
}

// Buscar foto do usuário e notificações
$foto_usuario = null;
$notificacoes = [
    'vendas_pendentes' => 0,
    'retornos' => 0,
    'agendados' => 0
];

try {
    $db_nav = Database::getConnectionBV();

    // Foto do usuário
    $stmt_foto = $db_nav->prepare("SELECT foto FROM usuarios WHERE id = :id");
    $stmt_foto->execute([':id' => Auth::getUserId()]);
    $result_foto = $stmt_foto->fetch();
    if ($result_foto && !empty($result_foto['foto'])) {
        $foto_usuario = $result_foto['foto'];
    }

    // Notificações - Vendas pendentes (mais de 1 dia)
    $stmt_pendentes = $db_nav->prepare("
        SELECT COUNT(*) as total FROM boas_vindas
        WHERE status = 'pendente'
        AND DATEDIFF(NOW(), data_venda) > 1
        AND (usuario_id = :usuario_id OR :is_admin = 1)
    ");
    $stmt_pendentes->execute([
        ':usuario_id' => Auth::getUserId(),
        ':is_admin' => Auth::isAdmin() ? 1 : 0
    ]);
    $notificacoes['vendas_pendentes'] = $stmt_pendentes->fetch()['total'] ?? 0;

    // Notificações - Clientes que não atenderam
    try {
        $stmt_retornos = $db_nav->prepare("
            SELECT COUNT(*) as total FROM boas_vindas
            WHERE status IN ('pendente', 'em_andamento')
            AND resultado_ultimo_contato = 'nao_atendeu'
            AND (usuario_id = :usuario_id OR :is_admin = 1)
        ");
        $stmt_retornos->execute([
            ':usuario_id' => Auth::getUserId(),
            ':is_admin' => Auth::isAdmin() ? 1 : 0
        ]);
        $notificacoes['retornos'] = $stmt_retornos->fetch()['total'] ?? 0;
    } catch (Exception $e) {}

    // Notificações - Retornos agendados para hoje
    try {
        $stmt_agendados = $db_nav->prepare("
            SELECT COUNT(*) as total FROM boas_vindas
            WHERE status IN ('pendente', 'em_andamento')
            AND DATE(proxima_tentativa) = CURDATE()
            AND (usuario_id = :usuario_id OR :is_admin = 1)
        ");
        $stmt_agendados->execute([
            ':usuario_id' => Auth::getUserId(),
            ':is_admin' => Auth::isAdmin() ? 1 : 0
        ]);
        $notificacoes['agendados'] = $stmt_agendados->fetch()['total'] ?? 0;
    } catch (Exception $e) {}

} catch (Exception $e) {}

$total_notificacoes = $notificacoes['vendas_pendentes'] + $notificacoes['retornos'] + $notificacoes['agendados'];
?>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $nav_base ?>/index">
            <i class="bi bi-hand-thumbs-up-fill"></i> Boas-Vindas Aquabeat
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'dashboard' ? 'active' : '' ?>" href="<?= $nav_base ?>/index">
                        <i class="bi bi-house-fill"></i> Dashboard
                    </a>
                </li>
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'usuarios' ? 'active' : '' ?>" href="<?= $nav_base ?>/usuarios">
                        <i class="bi bi-people-fill"></i> Usuários
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'relatorios' ? 'active' : '' ?>" href="<?= $nav_base ?>/relatorios">
                        <i class="bi bi-graph-up"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'configuracoes' ? 'active' : '' ?>" href="<?= $nav_base ?>/configuracoes">
                        <i class="bi bi-gear-fill"></i> Configurações
                    </a>
                </li>
                <?php endif; ?>
                <!-- Notificações -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" title="Notificações">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($total_notificacoes > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">
                            <?= $total_notificacoes > 99 ? '99+' : $total_notificacoes ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                        <li><h6 class="dropdown-header">Notificações</h6></li>
                        <?php if ($total_notificacoes === 0): ?>
                        <li><span class="dropdown-item-text text-muted">Nenhuma notificação</span></li>
                        <?php else: ?>
                            <?php if ($notificacoes['vendas_pendentes'] > 0): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="<?= $nav_base ?>/index?filtro=pendentes">
                                    <span class="badge bg-warning me-2"><?= $notificacoes['vendas_pendentes'] ?></span>
                                    <div>
                                        <strong>Vendas Pendentes</strong>
                                        <small class="d-block text-muted">Mais de 1 dia sem contato</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($notificacoes['retornos'] > 0): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="<?= $nav_base ?>/index?filtro=retornos">
                                    <span class="badge bg-danger me-2"><?= $notificacoes['retornos'] ?></span>
                                    <div>
                                        <strong>Retornos Pendentes</strong>
                                        <small class="d-block text-muted">Cliente não atendeu</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($notificacoes['agendados'] > 0): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="<?= $nav_base ?>/index?filtro=agendados">
                                    <span class="badge bg-info me-2"><?= $notificacoes['agendados'] ?></span>
                                    <div>
                                        <strong>Agendados Hoje</strong>
                                        <small class="d-block text-muted">Retornos programados</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $pagina_atual === 'perfil' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <?php if ($foto_usuario && file_exists(__DIR__ . '/../' . $foto_usuario)): ?>
                        <img src="<?= $nav_base ?>/<?= htmlspecialchars($foto_usuario) ?>" alt="Foto"
                             style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; margin-right: 5px;">
                        <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars(Auth::getUserName()) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <?= htmlspecialchars(Auth::getUserEmail()) ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= $nav_base ?>/perfil">
                                <i class="bi bi-person"></i> Meu Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $nav_base ?>/logout">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
