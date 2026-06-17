<?php
session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php');

$itens_por_pagina = 25;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$filtro_setor = isset($_GET['setor']) ? $conn->quote($_GET['setor']) : '';
$filtro_status = isset($_GET['status']) ? $conn->quote($_GET['status']) : '';

$where_conditions = [];
if ($filtro_setor) $where_conditions[] = "setor_destino = $filtro_setor";
if ($filtro_status) {
    if ($filtro_status === "'ABERTO'") {
        $where_conditions[] = "(status = 'ABERTO' OR status = 'NOVO')";
    } else {
        $where_conditions[] = "status = $filtro_status";
    }
}
$where_clause_geral = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql_inclusao = "SELECT id, 'INCLUSÃO' as tipo, nome_colaborador, nome_solicitante, email_solicitante, setor_solicitante, departamento_inclusao as setor_destino, data_solicitacao as data_hora, status, atendente_id, fechado_por, 'inclusao_colaborador' as tabela FROM inclusao_colaborador";
$sql_exclusao = "SELECT id, 'EXCLUSÃO' as tipo, nome_colaborador, nome_solicitante, email_solicitante, setor_solicitante, setor_colaborador as setor_destino, data_solicitacao as data_hora, status, atendente_id, fechado_por, 'exclusao_colaborador' as tabela FROM exclusao_colaborador";

// Contagem total
$sql_count = "SELECT COUNT(*) as total FROM ( ($sql_inclusao) UNION ALL ($sql_exclusao) ) as chamados_rh $where_clause_geral";
$result_count = $conn->query($sql_count);
if ($result_count) {
    $row = $result_count->fetch(PDO::FETCH_ASSOC);
    $total_chamados = $row['total'] ?? 0;
} else {
    $total_chamados = 0;
}
$total_paginas = ceil($total_chamados / $itens_por_pagina);

// Lista de setores
$sql_setores = "SELECT DISTINCT setor_destino FROM ( ($sql_inclusao) UNION ALL ($sql_exclusao) ) as setores_rh WHERE setor_destino IS NOT NULL AND setor_destino != '' ORDER BY setor_destino";
$result_setores = $conn->query($sql_setores);
$setores = [];
if ($result_setores) {
    while ($row = $result_setores->fetch(PDO::FETCH_ASSOC)) {
        $setores[] = $row['setor_destino'];
    }
}

// Construção da cláusula WHERE final
$where_conditions_final = [];
if ($filtro_setor) $where_conditions_final[] = "c.setor_destino = $filtro_setor";
if ($filtro_status) {
    if ($filtro_status === "'ABERTO'") {
        $where_conditions_final[] = "(c.status = 'ABERTO' OR c.status = 'NOVO')";
    } else {
        $where_conditions_final[] = "c.status = $filtro_status";
    }
}
$where_clause_final = count($where_conditions_final) > 0 ? "WHERE " . implode(" AND ", $where_conditions_final) : "";

// Consulta principal com LIMIT/OFFSET corrigido
$sql_final = "
    SELECT c.*, a.nome AS atendente_nome, f.nome AS fechado_por_nome
    FROM ( ($sql_inclusao) UNION ALL ($sql_exclusao) ) as c
    LEFT JOIN users a ON c.atendente_id = a.id
    LEFT JOIN users f ON c.fechado_por = f.id
    $where_clause_final
    ORDER BY c.data_hora DESC
    LIMIT $itens_por_pagina OFFSET $offset";
$result = $conn->query($sql_final);
$chamados_rh = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

function countChamadosRhByStatus($conn, $status_param, $filtro_setor = '') {
    if ($status_param === 'ABERTO') {
        $status_condition = "(status = 'ABERTO' OR status = 'NOVO')";
    } else {
        $status_condition = "status = " . $conn->quote($status_param);
    }
    $where_status = "WHERE " . $status_condition;
    if ($filtro_setor) {
        $where_status .= " AND setor_destino = " . $conn->quote($filtro_setor);
    }
    $sql = "SELECT COUNT(*) as total FROM (
                (SELECT status, departamento_inclusao as setor_destino FROM inclusao_colaborador) 
                UNION ALL 
                (SELECT status, setor_colaborador as setor_destino FROM exclusao_colaborador)
            ) as chamados_rh $where_status";
    $result_conn = $conn->query($sql);
    if ($result_conn) {
        $row = $result_conn->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }
    return 0;
}

$total_notificacoes = countChamadosRhByStatus($conn, 'ABERTO', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="./imgs/icone.png" type="image/x-icon">
    <link rel="stylesheet" href="anima.css">
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        /* Mantenha o mesmo CSS que você já tem */
        :root {
            --primary-blue: #0d6efd;
            --bg-body: #f4f7f6;
            --bg-card: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --rh-primary-color: #42a5f5; 
            --rh-secondary-color: #6a1b9a;
        }
        [data-theme="dark"] {
            --bg-body: #0b0e14;
            --bg-card: #151921;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.05);
            --primary-blue: #60a5fa;
        }
        .card-gerenciador { 
            background: var(--bg-card);
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); 
            border: 1px solid var(--border-color); 
            overflow: hidden;
        }
        [data-theme="dark"] .card-gerenciador { 
            box-shadow: 0 10px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05); 
            border: none;
        }
        .card-header-gerenciador { 
            background: linear-gradient(135deg, var(--rh-primary-color), var(--rh-secondary-color)); 
            color: white; 
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .status-info { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
        .gerenciador-rh .content h2 { color: var(--text-main); }
        .gerenciador-rh .pagination .page-item.active .page-link { background-color: var(--rh-primary-color); border-color: var(--rh-primary-color); }
        .gerenciador-rh .pagination .page-link { color: var(--rh-primary-color); }
        .badge-tipo-inclusao { background-color: #0d6efd; color: white; } 
        .badge-tipo-exclusao { background-color: #6c757d; color: white; }
        .actions-cell .btn { margin-right: 5px; }
        .card-header-gerenciador .input-group-text { 
            background-color: rgba(255,255,255,0.1); 
            border: 1px solid rgba(255,255,255,0.2); 
            color: white; 
            font-weight: 600; 
            font-size: 0.85rem;
        }
        .card-header-gerenciador .form-select { 
            background-color: rgba(255,255,255,0.95); 
            border: 1px solid rgba(255,255,255,0.2); 
            color: #1e293b; 
            font-size: 0.9rem;
            font-weight: 500;
        }
        [data-theme="dark"] .card-header-gerenciador .form-select {
            background-color: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
            border-color: rgba(255,255,255,0.1);
        }
        .status-btn { 
            cursor: pointer; 
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); 
            font-size: 0.8rem; 
            padding: 0.6em 1em; 
            border-radius: 20px;
            border: 1px solid transparent;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        .status-btn:hover { transform: translateY(-2px); filter: brightness(1.1); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .status-btn.active-filter { box-shadow: 0 0 0 2px var(--bg-body), 0 0 0 4px var(--rh-primary-color); transform: scale(1.05); }
        [data-theme="dark"] .status-btn { border: 1px solid rgba(255,255,255,0.1); }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; padding: 12px 16px; }
        .toast-whatsapp {
            background: linear-gradient(135deg, #128C7E, #dc3545) !important;
            color: white;
        }
    </style>
</head>
<body class="gerenciador gerenciador-rh">
    <audio id="notification-sound" src="./sounds/new-notification-022-370046.mp3" preload="auto"></audio>

    <div class="d-flex">
        <?php include 'menu.php'; ?>

        <div class="content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button id="sidebarToggleBtn" class="btn btn-sm btn-outline-secondary" style="border-radius: 30px;">
                    <i class="fas fa-bars"></i> <span class="d-none d-md-inline">Recolher Menu</span>
                </button>
                <div class="status-summary d-flex gap-2 align-items-center">
                     <a href="?status=ABERTO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none">
                         <span class="badge bg-success status-btn <?= $filtro_status == 'ABERTO' ? 'active-filter' : '' ?>">
                             Abertos: <?= countChamadosRhByStatus($conn, 'ABERTO', $filtro_setor) ?>
                         </span>
                     </a>
                     <a href="?status=EM+ATENDIMENTO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none">
                         <span class="badge bg-warning text-dark status-btn <?= $filtro_status == 'EM ATENDIMENTO' ? 'active-filter' : '' ?>">
                             Em Atendimento: <?= countChamadosRhByStatus($conn, 'EM ATENDIMENTO', $filtro_setor) ?>
                         </span>
                     </a>
                     <a href="?status=FECHADO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none">
                         <span class="badge bg-danger status-btn <?= $filtro_status == 'FECHADO' ? 'active-filter' : '' ?>">
                             Fechados: <?= countChamadosRhByStatus($conn, 'FECHADO', $filtro_setor) ?>
                         </span>
                     </a>
                     <?php if($filtro_status): ?>
                         <a href="?<?= $filtro_setor ? 'setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none ms-1">
                             <span class="badge bg-secondary status-btn"><i class="fas fa-times me-1"></i>Limpar</span>
                         </a>
                     <?php endif; ?>
                </div>
            </div>

            <div class="card card-gerenciador">
                <div class="card-header-gerenciador d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <h5 class="mb-0">Lista de Solicitações</h5>
                    <div class="d-flex align-items-center gap-3 mt-2 mt-md-0">
                        <div class="input-group">
                            <label class="input-group-text" for="setor">Setor:</label>
                            <select class="form-select" id="setor" onchange="location = this.value ? 'gerenciar_rh.php?setor='+encodeURIComponent(this.value)+'<?= $filtro_status ? '&status='.urlencode($filtro_status) : '' ?>' : 'gerenciar_rh.php<?= $filtro_status ? '?status='.urlencode($filtro_status) : '' ?>'">
                                <option value="" <?php if (empty($filtro_setor)) echo 'selected'; ?>>Todos os setores</option>
                                <?php foreach($setores as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?php if ($filtro_setor == $s) echo 'selected'; ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($filtro_setor): ?>
                                <a href="gerenciar_rh.php<?= $filtro_status ? '?status='.urlencode($filtro_status) : '' ?>" class="btn btn-light" title="Remover filtro de setor"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info text-white">
                            <span class="me-2">Página <?= $pagina_atual ?> de <?= $total_paginas ?: 1 ?></span>
                            <span>Total: <?= $total_chamados ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>N°</th><th>Tipo</th><th>Colaborador</th><th>Solicitante</th><th>Setor</th><th>Data/Hora</th><th>Status</th>
                                    <th>Ações</th>
                                    <?php if($_SESSION['PERFIL'] == "MASTER"): ?><th>Admin</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($chamados_rh)): ?>
                                    <?php foreach ($chamados_rh as $chamado): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $chamado['id'] ?> </td>
                                        <td><span class="badge <?= $chamado['tipo'] == 'INCLUSÃO' ? 'badge-tipo-inclusao' : 'badge-tipo-exclusao' ?>"><?= $chamado['tipo'] ?></span></td>
                                        <td><?= htmlspecialchars($chamado['nome_colaborador']) ?></td>
                                        <td><div class="d-flex flex-column"><span class="fw-bold"><?= htmlspecialchars($chamado['nome_solicitante']) ?></span><small class="text-muted"><?= htmlspecialchars($chamado['email_solicitante']) ?></small></div></td>
                                        <td><?= htmlspecialchars($chamado['setor_destino']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($chamado['data_hora'])) ?></td>
                                        <td>
                                            <?php
                                            $status_original = $chamado['status'];
                                            $status_logico = ($status_original === 'NOVO') ? 'ABERTO' : $status_original;
                                            $status_class = ['ABERTO' => 'bg-success', 'EM ATENDIMENTO' => 'bg-warning text-dark', 'FECHADO' => 'bg-danger'][$status_logico];
                                            $status_info = '';
                                            if ($status_logico == 'EM ATENDIMENTO' && !empty($chamado['atendente_nome'])) { $status_info = 'Atendente: ' . $chamado['atendente_nome']; }
                                            elseif ($status_logico == 'FECHADO' && !empty($chamado['fechado_por_nome'])) { $status_info = 'Fechado por: ' . $chamado['fechado_por_nome']; }
                                            ?>
                                            <div class="status-badge" data-bs-toggle="tooltip" title="<?= $status_info ?>">
                                                <span class="badge <?= $status_class ?>"><?= $status_original ?></span>
                                                <?php if($status_info): ?><div class="status-info d-none d-md-block"><small><?= $status_info ?></small></div><?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="carregarDetalhes(<?= $chamado['id'] ?>, '<?= $chamado['tabela'] ?>')" data-bs-toggle="modal" data-bs-target="#detalhesModal" title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php
                                            $btn_text_map = ['ABERTO' => 'Atender', 'EM ATENDIMENTO' => 'Fechar', 'FECHADO' => 'Reabrir'];
                                            $btn_class_map = ['ABERTO' => 'btn-primary', 'EM ATENDIMENTO' => 'btn-danger', 'FECHADO' => 'btn-success'];
                                            ?>
                                            <a href="update_status_rh.php?ID=<?= $chamado['id'] ?>&tabela=<?= $chamado['tabela'] ?>&pagina=<?= $pagina_atual ?>&setor=<?= urlencode($filtro_setor) ?><?= $filtro_status ? '&status='.urlencode($filtro_status) : '' ?>"
                                               class="btn btn-sm <?= $btn_class_map[$status_logico] ?> btn-action">
                                                <?= $btn_text_map[$status_logico] ?>
                                            </a>
                                        </td>
                                        <?php if($_SESSION['PERFIL'] == "MASTER"): ?>
                                        <td><a href="excluir_rh.php?ID=<?= $chamado['id'] ?>&tabela=<?= $chamado['tabela'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?= $_SESSION['PERFIL'] == 'MASTER' ? 9 : 8 ?>" class="text-center py-4 text-muted">Nenhuma solicitação encontrada</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if($total_paginas > 1): ?>
                <div class="card-footer bg-light border-top">
                    <nav><ul class="pagination justify-content-center mb-0">
                        <?php if($pagina_atual > 1): ?><li class="page-item"><a class="page-link" href="?pagina=1&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&laquo;&laquo;</a></li><li class="page-item"><a class="page-link" href="?pagina=<?= $pagina_atual-1 ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&laquo;</a></li><?php endif; ?>
                        <?php $inicio = max(1, $pagina_atual - 2); $fim = min($total_paginas, $pagina_atual + 2); for($i = $inicio; $i <= $fim; $i++): ?><li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>"><a class="page-link" href="?pagina=<?= $i ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>"><?= $i ?></a></li><?php endfor; ?>
                        <?php if($pagina_atual < $total_paginas): ?><li class="page-item"><a class="page-link" href="?pagina=<?= $pagina_atual+1 ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&raquo;</a></li><li class="page-item"><a class="page-link" href="?pagina=<?= $total_paginas ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&raquo;&raquo;</a></li><?php endif; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detalhesModal" tabindex="-1" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesModalLabel">Detalhes da Solicitação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBodyContent">
                    <div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        const sidebar = document.querySelector('.sidebar');
        const body = document.body;
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        if (!sidebar || !toggleBtn) return;
        const STORAGE_KEY = 'sidebarCollapsed';
        function setSidebarState(collapsed) {
            if (collapsed) {
                sidebar.classList.add('collapsed');
                body.classList.add('sidebar-toggled');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i> <span class="d-none d-md-inline">Expandir Menu</span>';
            } else {
                sidebar.classList.remove('collapsed');
                body.classList.remove('sidebar-toggled');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i> <span class="d-none d-md-inline">Recolher Menu</span>';
            }
            void sidebar.offsetHeight;
        }
        const savedState = localStorage.getItem(STORAGE_KEY);
        if (savedState === 'true') setSidebarState(true);
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const willBeCollapsed = !sidebar.classList.contains('collapsed');
            setSidebarState(willBeCollapsed);
            localStorage.setItem(STORAGE_KEY, willBeCollapsed);
        });
    })();

    function toggleTheme() {
        const doc = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        const newTheme = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        doc.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        if (newTheme === 'dark') {
            if (icon) icon.classList.replace('fa-moon', 'fa-sun');
            if (text) text.innerText = 'Modo Claro';
        } else {
            if (icon) icon.classList.replace('fa-sun', 'fa-moon');
            if (text) text.innerText = 'Modo Escuro';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            const icon = document.getElementById('theme-icon');
            const text = document.getElementById('theme-text');
            if (icon) icon.classList.replace('fa-moon', 'fa-sun');
            if (text) text.innerText = 'Modo Claro';
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    });

    function carregarDetalhes(id, tabela) {
        const modalBody = document.getElementById('modalBodyContent');
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
        fetch(`detalhes_rh.php?id=${id}&tabela=${tabela}`)
            .then(response => response.text())
            .then(data => { modalBody.innerHTML = data; })
            .catch(error => { modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes.</div>'; });
    }
    </script>
</body>
</html>
<?php $conn = null; ?>