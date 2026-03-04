<?php
require_once 'config.php';
Auth::requireAdmin();

$pagina_atual = 'usuarios';
$db = Database::getConnectionBV();

// Buscar todos os usuários
$stmt = $db->query("
    SELECT u.*,
           COUNT(bv.id) as total_atendimentos,
           SUM(CASE WHEN bv.status = 'concluido' THEN 1 ELSE 0 END) as concluidos
    FROM usuarios u
    LEFT JOIN boas_vindas bv ON u.id = bv.usuario_id
    GROUP BY u.id
    ORDER BY u.tipo DESC, u.nome ASC
");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Boas-Vindas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .user-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .user-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="content-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="bi bi-people-fill"></i> Gestão de Usuários</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="novoUsuario()">
                    <i class="bi bi-plus-circle"></i> Novo Usuário
                </button>
            </div>

            <!-- Cards de Usuários -->
            <div class="row">
                <?php foreach ($usuarios as $usuario): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card <?= $usuario['ativo'] ? '' : 'inactive' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <i class="bi bi-person-circle"></i>
                                    <?= htmlspecialchars($usuario['nome']) ?>
                                </h5>
                                <small class="text-muted"><?= htmlspecialchars($usuario['email']) ?></small>
                            </div>
                            <span class="badge bg-<?= $usuario['tipo'] === 'admin' ? 'danger' : 'primary' ?>">
                                <?= $usuario['tipo'] === 'admin' ? 'Admin' : 'Atendente' ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="fs-4 fw-bold text-primary"><?= $usuario['total_atendimentos'] ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="fs-4 fw-bold text-success"><?= $usuario['concluidos'] ?></div>
                                        <small class="text-muted">Concluídos</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-primary flex-fill" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <?php if ($usuario['total_atendimentos'] > 0): ?>
                            <button class="btn btn-sm btn-outline-info" onclick="abrirMigracao(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>')" title="Migrar atendimentos">
                                <i class="bi bi-arrow-left-right"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($usuario['ativo']): ?>
                                <button class="btn btn-sm btn-outline-warning" onclick="toggleAtivo(<?= $usuario['id'] ?>, 0)">
                                    <i class="bi bi-pause-circle"></i> Desativar
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-success" onclick="toggleAtivo(<?= $usuario['id'] ?>, 1)">
                                    <i class="bi bi-play-circle"></i> Ativar
                                </button>
                            <?php endif; ?>
                            <?php if ($usuario['id'] != Auth::getUserId()): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="excluirUsuario(<?= $usuario['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php
                        $perms = json_decode($usuario['permissoes'] ?? '[]', true) ?: [];
                        if ($usuario['tipo'] === 'atendente' && !empty($perms)):
                        ?>
                        <div class="mt-2">
                            <small class="text-muted d-block mb-1"><i class="bi bi-shield-check"></i> Permissões:</small>
                            <?php
                            $perm_labels = [
                                'editar_pagamento' => 'Pagamentos',
                                'ver_todos_titulos' => 'Ver Todos',
                                'concluir_atendimento' => 'Concluir',
                                'ver_relatorios' => 'Relatórios',
                                'exportar_dados' => 'Exportar'
                            ];
                            foreach ($perms as $p):
                                $label = $perm_labels[$p] ?? $p;
                            ?>
                            <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> Criado em: <?= formatarData($usuario['criado_em'], 'd/m/Y') ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Usuário -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioTitulo">Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUsuario">
                        <input type="hidden" id="usuario_id" name="id">

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="atendente">Atendente</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <div class="mb-3" id="campo-senha">
                            <label for="senha" class="form-label">Senha *</label>
                            <input type="password" class="form-control" id="senha" name="senha">
                            <small class="text-muted">Deixe em branco para manter a senha atual (ao editar)</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                                <label class="form-check-label" for="ativo">
                                    Usuário ativo
                                </label>
                            </div>
                        </div>

                        <!-- Seção de Permissões (só aparece para atendentes) -->
                        <div class="mb-3" id="secao-permissoes" style="display: none;">
                            <label class="form-label fw-bold">
                                <i class="bi bi-shield-check"></i> Permissões Especiais
                            </label>
                            <div class="alert alert-info py-2 mb-2">
                                <small><i class="bi bi-info-circle"></i> Administradores têm todas as permissões automaticamente.</small>
                            </div>
                            <div class="border rounded p-3 bg-light">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="perm_editar_pagamento" name="permissoes[]" value="editar_pagamento">
                                    <label class="form-check-label" for="perm_editar_pagamento">
                                        <strong>Registrar Pagamentos</strong>
                                        <br><small class="text-muted">Permite registrar pagamentos de parcelas nos títulos</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="perm_ver_todos_titulos" name="permissoes[]" value="ver_todos_titulos">
                                    <label class="form-check-label" for="perm_ver_todos_titulos">
                                        <strong>Ver Todos os Títulos</strong>
                                        <br><small class="text-muted">Permite visualizar títulos de todos os atendentes</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="perm_concluir_atendimento" name="permissoes[]" value="concluir_atendimento">
                                    <label class="form-check-label" for="perm_concluir_atendimento">
                                        <strong>Concluir Atendimentos</strong>
                                        <br><small class="text-muted">Permite marcar atendimentos como concluídos</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="perm_ver_relatorios" name="permissoes[]" value="ver_relatorios">
                                    <label class="form-check-label" for="perm_ver_relatorios">
                                        <strong>Ver Relatórios</strong>
                                        <br><small class="text-muted">Permite acessar página de relatórios</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_exportar_dados" name="permissoes[]" value="exportar_dados">
                                    <label class="form-check-label" for="perm_exportar_dados">
                                        <strong>Exportar Dados</strong>
                                        <br><small class="text-muted">Permite exportar dados para Excel/CSV</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarUsuario()">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Migração -->
    <div class="modal fade" id="modalMigracao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Migrar Atendimentos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="migracao_origem_id">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Transferir atendimentos de <strong id="migracao_origem_nome"></strong> para outro usuário.
                    </div>

                    <div class="mb-3">
                        <label for="migracao_destino" class="form-label">Transferir para *</label>
                        <select class="form-select" id="migracao_destino" required>
                            <option value="">Selecione o usuário destino...</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nome']) ?> (<?= $u['total_atendimentos'] ?> atendimentos)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">O que migrar?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_migracao" id="migracao_todos" value="todos" checked>
                            <label class="form-check-label" for="migracao_todos">
                                Todos os atendimentos
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_migracao" id="migracao_abertos" value="abertos">
                            <label class="form-check-label" for="migracao_abertos">
                                Apenas atendimentos em aberto (pendentes e em andamento)
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Atenção:</strong> Esta ação não pode ser desfeita facilmente!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="executarMigracao()">
                        <i class="bi bi-arrow-left-right"></i> Migrar Atendimentos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    let modalUsuario;
    let modoEdicao = false;

    $(document).ready(function() {
        modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));

        // Evento para mostrar/ocultar permissões baseado no tipo
        document.getElementById('tipo').addEventListener('change', togglePermissoes);
    });

    function novoUsuario() {
        modoEdicao = false;
        document.getElementById('formUsuario').reset();
        document.getElementById('usuario_id').value = '';
        document.getElementById('modalUsuarioTitulo').textContent = 'Novo Usuário';
        document.getElementById('senha').required = true;
        document.getElementById('ativo').checked = true;
        document.getElementById('tipo').value = 'atendente';
        togglePermissoes();
        limparPermissoes();
    }

    function togglePermissoes() {
        const tipo = document.getElementById('tipo').value;
        const secaoPermissoes = document.getElementById('secao-permissoes');
        secaoPermissoes.style.display = tipo === 'atendente' ? 'block' : 'none';
    }

    function limparPermissoes() {
        document.querySelectorAll('input[name="permissoes[]"]').forEach(cb => cb.checked = false);
    }

    function carregarPermissoes(permissoesJson) {
        limparPermissoes();
        if (!permissoesJson) return;

        let permissoes = [];
        try {
            permissoes = typeof permissoesJson === 'string' ? JSON.parse(permissoesJson) : permissoesJson;
        } catch(e) {
            permissoes = [];
        }

        if (Array.isArray(permissoes)) {
            permissoes.forEach(perm => {
                const cb = document.querySelector(`input[name="permissoes[]"][value="${perm}"]`);
                if (cb) cb.checked = true;
            });
        }
    }

    function editarUsuario(id) {
        modoEdicao = true;

        $.ajax({
            url: 'api_usuario.php?id=' + id,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const user = response.data;
                    document.getElementById('usuario_id').value = user.id;
                    document.getElementById('nome').value = user.nome;
                    document.getElementById('email').value = user.email;
                    document.getElementById('tipo').value = user.tipo;
                    document.getElementById('ativo').checked = user.ativo == 1;
                    document.getElementById('senha').value = '';
                    document.getElementById('senha').required = false;

                    // Carregar permissões
                    togglePermissoes();
                    carregarPermissoes(user.permissoes);

                    document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuário';
                    modalUsuario.show();
                }
            }
        });
    }

    function salvarUsuario() {
        const formData = new FormData(document.getElementById('formUsuario'));
        
        $.ajax({
            url: 'api_salvar_usuario.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    modalUsuario.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + response.error);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    }

    function toggleAtivo(id, ativo) {
        const acao = ativo ? 'ativar' : 'desativar';
        
        if (!confirm(`Tem certeza que deseja ${acao} este usuário?`)) {
            return;
        }
        
        $.ajax({
            url: 'api_toggle_usuario.php',
            method: 'POST',
            data: { id: id, ativo: ativo },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + response.error);
                }
            }
        });
    }

    function excluirUsuario(id) {
        if (!confirm('Tem certeza que deseja EXCLUIR este usuário?\n\nATENÇÃO: Esta ação não pode ser desfeita!')) {
            return;
        }

        if (!confirm('Confirma novamente a exclusão?')) {
            return;
        }

        $.ajax({
            url: 'api_excluir_usuario.php',
            method: 'POST',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    alert('Usuário excluído com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + response.error);
                }
            }
        });
    }

    // Migração de atendimentos
    let modalMigracao;

    function abrirMigracao(usuarioId, nomeUsuario) {
        document.getElementById('migracao_origem_id').value = usuarioId;
        document.getElementById('migracao_origem_nome').textContent = nomeUsuario;

        // Esconder opção do mesmo usuário no destino
        const selectDestino = document.getElementById('migracao_destino');
        for (let opt of selectDestino.options) {
            opt.hidden = opt.value == usuarioId;
        }
        selectDestino.value = '';

        if (!modalMigracao) {
            modalMigracao = new bootstrap.Modal(document.getElementById('modalMigracao'));
        }
        modalMigracao.show();
    }

    function executarMigracao() {
        const origemId = document.getElementById('migracao_origem_id').value;
        const destinoId = document.getElementById('migracao_destino').value;
        const tipoMigracao = document.querySelector('input[name="tipo_migracao"]:checked').value;

        if (!destinoId) {
            alert('Selecione o usuário destino!');
            return;
        }

        if (!confirm('Confirma a migração dos atendimentos?\n\nEsta ação transferirá os atendimentos para o novo usuário.')) {
            return;
        }

        $.ajax({
            url: 'api_migrar_atendimentos.php',
            method: 'POST',
            data: {
                origem_id: origemId,
                destino_id: destinoId,
                tipo: tipoMigracao
            },
            success: function(response) {
                if (response.success) {
                    alert('Migração realizada com sucesso!\n\n' + response.migrados + ' atendimentos foram transferidos.');
                    modalMigracao.hide();
                    location.reload();
                } else {
                    alert('Erro: ' + response.error);
                }
            },
            error: function() {
                alert('Erro na requisição');
            }
        });
    }
    </script>
</body>
</html>
