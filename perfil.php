<?php
/**
 * SISTEMA DE BOAS-VINDAS AQUABEAT
 * Página de Perfil do Usuário
 */

require_once 'config.php';
Auth::requireLogin();

$db = Database::getConnectionBV();
$mensagem = '';
$tipo_mensagem = '';

// Buscar dados do usuário
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => Auth::getUserId()]);
$usuario = $stmt->fetch();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'dados') {
            // Atualizar dados básicos
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios');
            }

            // Verificar se email já existe (de outro usuário)
            $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmt_check->execute([':email' => $email, ':id' => Auth::getUserId()]);
            if ($stmt_check->fetch()) {
                throw new Exception('Este email já está em uso por outro usuário');
            }

            $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id");
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':id' => Auth::getUserId()
            ]);

            // Atualizar sessão
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_email'] = $email;

            Logger::log(null, 'perfil_atualizado', 'Atualizou seus dados de perfil');
            $mensagem = 'Dados atualizados com sucesso!';
            $tipo_mensagem = 'success';

            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => Auth::getUserId()]);
            $usuario = $stmt->fetch();

        } elseif ($acao === 'senha') {
            // Alterar senha
            $senha_atual = $_POST['senha_atual'] ?? '';
            $senha_nova = $_POST['senha_nova'] ?? '';
            $senha_confirmar = $_POST['senha_confirmar'] ?? '';

            if (empty($senha_atual) || empty($senha_nova) || empty($senha_confirmar)) {
                throw new Exception('Todos os campos de senha são obrigatórios');
            }

            if ($senha_nova !== $senha_confirmar) {
                throw new Exception('A nova senha e confirmação não conferem');
            }

            if (strlen($senha_nova) < 6) {
                throw new Exception('A nova senha deve ter pelo menos 6 caracteres');
            }

            // Verificar senha atual
            $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND senha = MD5(:senha)");
            $stmt_check->execute([':id' => Auth::getUserId(), ':senha' => $senha_atual]);
            if (!$stmt_check->fetch()) {
                throw new Exception('Senha atual incorreta');
            }

            $stmt = $db->prepare("UPDATE usuarios SET senha = MD5(:senha) WHERE id = :id");
            $stmt->execute([
                ':senha' => $senha_nova,
                ':id' => Auth::getUserId()
            ]);

            Logger::log(null, 'senha_alterada', 'Alterou sua senha');
            $mensagem = 'Senha alterada com sucesso!';
            $tipo_mensagem = 'success';

        } elseif ($acao === 'foto') {
            // Upload de foto
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhuma foto enviada ou erro no upload');
            }

            $arquivo = $_FILES['foto'];
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($extensao, $extensoes_permitidas)) {
                throw new Exception('Formato de imagem não permitido. Use: ' . implode(', ', $extensoes_permitidas));
            }

            if ($arquivo['size'] > 2 * 1024 * 1024) {
                throw new Exception('A imagem deve ter no máximo 2MB');
            }

            // Criar pasta de uploads se não existir
            $pasta_uploads = __DIR__ . '/uploads/fotos';
            if (!is_dir($pasta_uploads)) {
                mkdir($pasta_uploads, 0755, true);
            }

            // Gerar nome único
            $nome_arquivo = 'user_' . Auth::getUserId() . '_' . time() . '.' . $extensao;
            $caminho_completo = $pasta_uploads . '/' . $nome_arquivo;

            if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                throw new Exception('Erro ao salvar a imagem');
            }

            // Remover foto antiga se existir
            if (!empty($usuario['foto']) && file_exists(__DIR__ . '/' . $usuario['foto'])) {
                @unlink(__DIR__ . '/' . $usuario['foto']);
            }

            // Salvar caminho no banco
            $caminho_relativo = 'uploads/fotos/' . $nome_arquivo;
            $stmt = $db->prepare("UPDATE usuarios SET foto = :foto WHERE id = :id");
            $stmt->execute([
                ':foto' => $caminho_relativo,
                ':id' => Auth::getUserId()
            ]);

            Logger::log(null, 'foto_atualizada', 'Atualizou sua foto de perfil');
            $mensagem = 'Foto atualizada com sucesso!';
            $tipo_mensagem = 'success';

            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => Auth::getUserId()]);
            $usuario = $stmt->fetch();
        }
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Estatísticas do usuário
$stmt_stats = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
    FROM boas_vindas WHERE usuario_id = :id
");
$stmt_stats->execute([':id' => Auth::getUserId()]);
$stats = $stmt_stats->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Boas-Vindas Aquabeat</title>
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
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px 10px 0 0;
            margin: -25px -25px 25px -25px;
            text-align: center;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
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
                    <?php if (Auth::isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="usuarios"><i class="bi bi-people-fill"></i> Usuários</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="relatorios"><i class="bi bi-graph-up"></i> Relatórios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracoes"><i class="bi bi-gear-fill"></i> Configurações</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(Auth::getUserName()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="perfil"><i class="bi bi-person"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show">
            <i class="bi bi-<?= $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Perfil e Foto -->
            <div class="col-lg-4">
                <div class="content-section">
                    <div class="profile-header">
                        <div class="mb-3">
                            <?php if (!empty($usuario['foto']) && file_exists(__DIR__ . '/' . $usuario['foto'])): ?>
                            <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="Foto" class="profile-avatar">
                            <?php else: ?>
                            <div class="avatar-placeholder mx-auto">
                                <i class="bi bi-person"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h4>
                        <p class="mb-0 opacity-75"><?= $usuario['tipo'] === 'admin' ? 'Administrador' : 'Atendente' ?></p>
                    </div>

                    <!-- Upload de Foto -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="acao" value="foto">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-camera"></i> Alterar Foto</label>
                            <input type="file" class="form-control" name="foto" accept="image/*" required>
                            <small class="text-muted">JPG, PNG ou GIF. Máximo 2MB.</small>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-upload"></i> Enviar Foto
                        </button>
                    </form>

                    <!-- Estatísticas -->
                    <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Minhas Estatísticas</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number text-success"><?= $stats['concluidos'] ?? 0 ?></div>
                                <small class="text-muted">Concluídos</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <p class="text-muted small mb-0">
                        <i class="bi bi-calendar"></i> Membro desde: <?= formatarData($usuario['criado_em'], 'd/m/Y') ?>
                    </p>
                </div>
            </div>

            <!-- Formulários -->
            <div class="col-lg-8">
                <!-- Dados Básicos -->
                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-person-vcard"></i> Dados Pessoais</h5>

                    <form method="POST">
                        <input type="hidden" name="acao" value="dados">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="nome" name="nome"
                                       value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($usuario['email']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Usuário</label>
                                <input type="text" class="form-control" value="<?= $usuario['tipo'] === 'admin' ? 'Administrador' : 'Atendente' ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="<?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>" disabled>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </form>
                </div>

                <!-- Alterar Senha -->
                <div class="content-section">
                    <h5 class="mb-4"><i class="bi bi-shield-lock"></i> Alterar Senha</h5>

                    <form method="POST">
                        <input type="hidden" name="acao" value="senha">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="senha_atual" class="form-label">Senha Atual *</label>
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="senha_nova" class="form-label">Nova Senha *</label>
                                <input type="password" class="form-control" id="senha_nova" name="senha_nova" required minlength="6">
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="senha_confirmar" class="form-label">Confirmar Nova Senha *</label>
                                <input type="password" class="form-control" id="senha_confirmar" name="senha_confirmar" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
