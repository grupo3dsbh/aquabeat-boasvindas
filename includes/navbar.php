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

    // Buscar lista detalhada de atendimentos que precisam de follow-up (limite 10)
    $atendimentos_followup = [];
    try {
        $stmt_followup = $db_nav->prepare("
            SELECT
                bv.id,
                bv.numero_titulo,
                bv.nome_cliente,
                bv.status,
                bv.resultado_ultimo_contato,
                bv.data_ultimo_contato,
                bv.proxima_tentativa,
                TIMESTAMPDIFF(HOUR, COALESCE(bv.data_ultimo_contato, bv.criado_em), NOW()) as horas_desde_ultima
            FROM boas_vindas bv
            WHERE bv.status IN ('pendente', 'em_andamento')
            AND (
                bv.resultado_ultimo_contato IN ('nao_atendeu', 'ocupado', 'caixa_postal')
                OR (bv.resultado_ultimo_contato IS NULL AND DATEDIFF(NOW(), bv.criado_em) > 0)
                OR DATE(bv.proxima_tentativa) <= CURDATE()
            )
            AND (bv.usuario_id = :usuario_id OR :is_admin = 1)
            ORDER BY
                CASE WHEN DATE(bv.proxima_tentativa) = CURDATE() THEN 0 ELSE 1 END,
                COALESCE(bv.data_ultimo_contato, bv.criado_em) ASC
            LIMIT 10
        ");
        $stmt_followup->execute([
            ':usuario_id' => Auth::getUserId(),
            ':is_admin' => Auth::isAdmin() ? 1 : 0
        ]);
        $atendimentos_followup = $stmt_followup->fetchAll();
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
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 380px; max-height: 500px; overflow-y: auto;">
                        <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notificações</span>
                            <?php if ($total_notificacoes > 0): ?>
                            <span class="badge bg-primary"><?= $total_notificacoes ?></span>
                            <?php endif; ?>
                        </h6></li>

                        <!-- Resumo por categoria -->
                        <?php if ($total_notificacoes > 0): ?>
                        <li class="px-3 py-2 bg-light border-bottom">
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($notificacoes['vendas_pendentes'] > 0): ?>
                                <a href="<?= $nav_base ?>/index?filtro=pendentes" class="badge bg-warning text-decoration-none" title="Vendas pendentes (+1 dia)">
                                    <i class="bi bi-clock"></i> <?= $notificacoes['vendas_pendentes'] ?> pendentes
                                </a>
                                <?php endif; ?>
                                <?php if ($notificacoes['retornos'] > 0): ?>
                                <a href="<?= $nav_base ?>/index?filtro=retornos" class="badge bg-danger text-decoration-none" title="Não atenderam">
                                    <i class="bi bi-telephone-x"></i> <?= $notificacoes['retornos'] ?> retornos
                                </a>
                                <?php endif; ?>
                                <?php if ($notificacoes['agendados'] > 0): ?>
                                <a href="<?= $nav_base ?>/index?filtro=agendados" class="badge bg-info text-decoration-none" title="Agendados para hoje">
                                    <i class="bi bi-calendar-check"></i> <?= $notificacoes['agendados'] ?> hoje
                                </a>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endif; ?>

                        <!-- Lista detalhada de atendimentos -->
                        <?php if (empty($atendimentos_followup)): ?>
                        <li><span class="dropdown-item-text text-muted text-center py-3">
                            <i class="bi bi-check-circle text-success"></i><br>
                            Nenhum follow-up pendente
                        </span></li>
                        <?php else: ?>
                        <li><small class="dropdown-header text-uppercase">Atendimentos para contato</small></li>
                        <?php foreach ($atendimentos_followup as $atend):
                            // Calcular tempo formatado
                            $horas = intval($atend['horas_desde_ultima']);
                            if ($horas < 24) {
                                $tempo_str = $horas . 'h';
                            } else {
                                $dias = floor($horas / 24);
                                $tempo_str = $dias . ' dia' . ($dias > 1 ? 's' : '');
                            }

                            // Determinar motivo/tipo
                            $motivo = 'Aguardando contato';
                            $badge_class = 'bg-secondary';
                            $icon = 'bi-clock';

                            if ($atend['proxima_tentativa'] && date('Y-m-d', strtotime($atend['proxima_tentativa'])) == date('Y-m-d')) {
                                $motivo = 'Agendado para hoje';
                                $badge_class = 'bg-info';
                                $icon = 'bi-calendar-check';
                            } elseif ($atend['resultado_ultimo_contato'] == 'nao_atendeu') {
                                $motivo = 'Não atendeu';
                                $badge_class = 'bg-danger';
                                $icon = 'bi-telephone-x';
                            } elseif ($atend['resultado_ultimo_contato'] == 'ocupado') {
                                $motivo = 'Ocupado';
                                $badge_class = 'bg-warning text-dark';
                                $icon = 'bi-telephone-minus';
                            } elseif ($atend['resultado_ultimo_contato'] == 'caixa_postal') {
                                $motivo = 'Caixa postal';
                                $badge_class = 'bg-secondary';
                                $icon = 'bi-voicemail';
                            }

                            $nome_curto = mb_strlen($atend['nome_cliente']) > 25
                                ? mb_substr($atend['nome_cliente'], 0, 22) . '...'
                                : $atend['nome_cliente'];
                        ?>
                        <li>
                            <a class="dropdown-item notification-item py-2" href="<?= $nav_base ?>/titulo?id=<?= urlencode($atend['numero_titulo']) ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($atend['numero_titulo']) ?>
                                        </div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($nome_curto) ?></small>
                                    </div>
                                    <div class="text-end ms-2">
                                        <span class="badge <?= $badge_class ?> mb-1">
                                            <i class="bi <?= $icon ?>"></i> <?= $motivo ?>
                                        </span>
                                        <small class="text-muted d-block"><?= $tempo_str ?> atrás</small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>

                        <!-- Link carregar mais -->
                        <?php if (count($atendimentos_followup) >= 10): ?>
                        <li class="border-top">
                            <a class="dropdown-item text-center text-primary py-2" href="<?= $nav_base ?>/index?filtro=followup">
                                <i class="bi bi-arrow-down-circle"></i> Ver todos os pendentes
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
