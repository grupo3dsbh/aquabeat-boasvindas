<?php
/**
 * NAVBAR CENTRALIZADO
 * Include este arquivo em todas as páginas após carregar config.php
 *
 * Uso:
 * $pagina_atual = 'dashboard'; // ou 'usuarios', 'relatorios', 'configuracoes', 'perfil'
 * include 'includes/navbar.php';
 */

// Definir página atual se não foi definida
if (!isset($pagina_atual)) {
    $pagina_atual = '';
}

// Buscar foto do usuário se existir
$foto_usuario = null;
try {
    $db_nav = Database::getConnectionBV();
    $stmt_foto = $db_nav->prepare("SELECT foto FROM usuarios WHERE id = :id");
    $stmt_foto->execute([':id' => Auth::getUserId()]);
    $result_foto = $stmt_foto->fetch();
    if ($result_foto && !empty($result_foto['foto'])) {
        $foto_usuario = $result_foto['foto'];
    }
} catch (Exception $e) {
    // Silenciar erro
}
?>
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
                    <a class="nav-link <?= $pagina_atual === 'dashboard' ? 'active' : '' ?>" href="index">
                        <i class="bi bi-house-fill"></i> Dashboard
                    </a>
                </li>
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'usuarios' ? 'active' : '' ?>" href="usuarios">
                        <i class="bi bi-people-fill"></i> Usuários
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'relatorios' ? 'active' : '' ?>" href="relatorios">
                        <i class="bi bi-graph-up"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_atual === 'configuracoes' ? 'active' : '' ?>" href="configuracoes">
                        <i class="bi bi-gear-fill"></i> Configurações
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $pagina_atual === 'perfil' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <?php if ($foto_usuario && file_exists(__DIR__ . '/../' . $foto_usuario)): ?>
                        <img src="<?= htmlspecialchars($foto_usuario) ?>" alt="Foto"
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
                            <a class="dropdown-item" href="perfil">
                                <i class="bi bi-person"></i> Meu Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
