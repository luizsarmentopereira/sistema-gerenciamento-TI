<?php
session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php');

// Configuração de paginação
$itens_por_pagina = 25;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Filtros (usando PDO::quote para segurança)
$filtro_setor = isset($_GET['setor']) ? $conn->quote($_GET['setor']) : '';
$filtro_status = isset($_GET['status']) ? $conn->quote($_GET['status']) : '';

// Construção da cláusula WHERE
$where_conditions = [];
if ($filtro_setor) $where_conditions[] = "c.setor = $filtro_setor";
if ($filtro_status) $where_conditions[] = "c.status = $filtro_status";
$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// WHERE para contagem
$where_conditions_count = [];
if ($filtro_setor) $where_conditions_count[] = "setor = $filtro_setor";
if ($filtro_status) $where_conditions_count[] = "status = $filtro_status";
$where_clause_count = count($where_conditions_count) > 0 ? "WHERE " . implode(" AND ", $where_conditions_count) : "";

// Consulta para contar o total de chamados
$sql_count = "SELECT COUNT(*) as total FROM chamado $where_clause_count";
$result_count = $conn->query($sql_count);
if ($result_count) {
    $row_count = $result_count->fetch(PDO::FETCH_ASSOC);
    $total_chamados = $row_count['total'] ?? 0;
} else {
    $total_chamados = 0;
}
$total_paginas = ceil($total_chamados / $itens_por_pagina);

// Consulta para obter todos os setores distintos
$sql_setores = "SELECT DISTINCT setor FROM chamado ORDER BY setor";
$result_setores = $conn->query($sql_setores);
$setores = [];
if ($result_setores) {
    while ($row = $result_setores->fetch(PDO::FETCH_ASSOC)) {
        $setores[] = $row['setor'];
    }
}

// Consulta principal com paginação e filtro
// CORREÇÃO: PostgreSQL usa LIMIT X OFFSET Y em vez de LIMIT X,Y
$sql = "SELECT c.*, a.nome AS atendente_nome, f.nome AS fechado_por_nome 
        FROM chamado c 
        LEFT JOIN users a ON c.atendente_id = a.id 
        LEFT JOIN users f ON c.fechado_por = f.id 
        $where_clause
        ORDER BY c.id DESC 
        LIMIT $itens_por_pagina OFFSET $offset";
$result = $conn->query($sql);
$chamados = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
$last_id = !empty($chamados) ? $chamados[0]['id'] : 0;

/**
 * Conta chamados por status (com filtro opcional de setor)
 */
function countChamadosByStatus($conn, $status, $filtro_setor = '') {
    $where = "WHERE status = " . $conn->quote($status);
    if ($filtro_setor) {
        $where .= " AND setor = " . $conn->quote($filtro_setor);
    }
    $sql = "SELECT COUNT(*) as total FROM chamado $where";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return (int)$row['total'];
    }
    return 0;
}

/**
 * Trunca texto para exibição na tabela
 */
function truncateText($text, $length) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="./imgs/icone.png" type="image/x-icon">
    <link rel="stylesheet" href="anima.css">

    <!-- Aplica o tema salvo ANTES do render para evitar flash de cores -->
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <style>
        :root {
            --primary-blue: #0d6efd;
            --bg-body: #f4f7f6;
            --bg-card: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
        }

        [data-theme="dark"] {
            --bg-body: #0b0e14;
            --bg-card: #151921;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.05);
            --primary-blue: #60a5fa;
        }

        /* ============================================
           COMPONENTES GERAIS (específicos desta página)
        ============================================ */
        .card-gerenciador { 
            background: var(--bg-card);
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); 
            border: 1px solid var(--border-color); 
            overflow: hidden;
            transition: background-color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }
        [data-theme="dark"] .card-gerenciador { 
            box-shadow: 0 10px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05); 
            border: none;
        }

        .card-header-gerenciador { 
            background: linear-gradient(135deg, #0d6efd, #0b5ed7); 
            color: white; 
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        [data-theme="dark"] .card-header-gerenciador { 
            background: linear-gradient(135deg, #2563eb, #3b82f6); 
            border-bottom-color: rgba(255,255,255,0.05);
        }

        .status-info { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
        
        /* Filtros no Header */
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

        .card-header-gerenciador .nav-pills .nav-link { 
            color: rgba(255,255,255,0.85); 
            font-weight: 600; 
            font-size: 0.85rem;
            padding: 0.6rem 1.2rem;
            transition: all 0.2s; 
            border-radius: 8px;
        }
        .card-header-gerenciador .nav-pills .nav-link:hover { 
            color: #fff; 
            background-color: rgba(255,255,255,0.1); 
        }
        .card-header-gerenciador .nav-pills .nav-link.active { 
            background-color: #fff !important; 
            color: #2563eb !important; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
        }
        [data-theme="dark"] .card-header-gerenciador .nav-pills .nav-link.active {
            background-color: #f1f5f9 !important;
            color: #1d4ed8 !important;
        }
        
        .user-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

        /* Badges de status clicáveis */
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
        .status-btn:hover { 
            transform: translateY(-2px); 
            filter: brightness(1.1); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .status-btn.active-filter { 
            box-shadow: 0 0 0 2px var(--bg-body), 0 0 0 4px var(--primary-blue); 
            transform: scale(1.05);
        }

        [data-theme="dark"] .status-btn {
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            padding: 12px 16px;
        }

        /* Correção do texto das tooltips no modo escuro */
        .tooltip-inner {
            color: #ffffff !important;
        }
    </style>
</head>

<body class="gerenciador">
    <audio id="notification-sound" src="./sounds/new-notification-022-370046.mp3" preload="auto"></audio>
    
    <div class="d-flex">
        <?php include 'menu.php'; ?>
        
        <div class="content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-3">
                    <h2 class="mb-0 fw-bold header-title-clean text-dark">
                        <i class="fas fa-ticket-alt text-primary me-2"></i>Gerenciador de Chamados
                    </h2>
                    <button id="test-notification-btn" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm fw-semibold" style="transition: all 0.2s;" title="Testar se as notificações estão funcionando">
                        <i class="fas fa-satellite-dish me-1"></i> Testar Notificação
                    </button>
                </div>

                <div class="status-summary d-flex gap-2 align-items-center">
                    <a href="?status=ABERTO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none" title="Filtrar por Abertos">
                        <span class="badge bg-success status-btn <?= $filtro_status == 'ABERTO' ? 'active-filter' : '' ?>">
                            Abertos: <?= countChamadosByStatus($conn, 'ABERTO', $filtro_setor) ?>
                        </span>
                    </a>
                    <a href="?status=EM+ATENDIMENTO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none" title="Filtrar por Em Atendimento">
                        <span class="badge bg-warning text-dark status-btn <?= $filtro_status == 'EM ATENDIMENTO' ? 'active-filter' : '' ?>">
                            Em Atendimento: <?= countChamadosByStatus($conn, 'EM ATENDIMENTO', $filtro_setor) ?>
                        </span>
                    </a>
                    <a href="?status=FECHADO<?= $filtro_setor ? '&setor='.urlencode($filtro_setor) : '' ?>" class="text-decoration-none" title="Filtrar por Fechados">
                        <span class="badge bg-danger status-btn <?= $filtro_status == 'FECHADO' ? 'active-filter' : '' ?>">
                            Fechados: <?= countChamadosByStatus($conn, 'FECHADO', $filtro_setor) ?>
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
                    <h5 class="mb-0">Painel de Controle</h5>
                    <ul class="nav nav-pills card-header-pills" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pills-chamados-tab" data-bs-toggle="pill" data-bs-target="#pills-chamados" type="button" role="tab" aria-controls="pills-chamados" aria-selected="true">
                                <i class="fas fa-list-ul me-2"></i>Lista de Chamados
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-chats-tab" data-bs-toggle="pill" data-bs-target="#pills-chats" type="button" role="tab" aria-controls="pills-chats" aria-selected="false">
                                <i class="fas fa-comments me-2"></i>Monitor de Conversas
                            </button>
                        </li>
                    </ul>
                    
                    <div class="d-flex align-items-center gap-3 mt-2 mt-md-0">
                        <div class="input-group">
                            <label class="input-group-text" for="setor">Setor:</label>
                            <select class="form-select" id="setor" onchange="location = this.value ? 'gerenciar.php?setor='+encodeURIComponent(this.value)+'<?= $filtro_status ? '&status='.urlencode($filtro_status) : '' ?>' : 'gerenciar.php<?= $filtro_status ? '?status='.urlencode($filtro_status) : '' ?>'">
                                <option value="" <?php if (empty($filtro_setor)) echo 'selected'; ?>>Todos os setores</option>
                                <?php foreach($setores as $setor): ?>
                                    <option value="<?= htmlspecialchars($setor) ?>" <?php if ($filtro_setor == $setor) echo 'selected'; ?>><?= htmlspecialchars($setor) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($filtro_setor): ?>
                                <a href="gerenciar.php<?= $filtro_status ? '?status='.urlencode($filtro_status) : '' ?>" class="btn btn-light" title="Remover filtro de setor"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info text-white">
                            <span class="me-2">Página <?= $pagina_atual ?> de <?= $total_paginas ?: 1 ?></span>
                            <span>Total: <?= $total_chamados ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="tab-content" id="pills-tabContent">
                        <!-- Aba 1: Tabela Tradicional -->
                        <div class="tab-pane fade show active" id="pills-chamados" role="tabpanel" aria-labelledby="pills-chamados-tab" tabindex="0">
                            <div class="table-responsive">
                                <?php
                                $sql_last_msg = "SELECT MAX(id) as max_id FROM mensagens_chamado";
                                $res_last_msg = $conn->query($sql_last_msg);
                                $row_msg = $res_last_msg ? $res_last_msg->fetch(PDO::FETCH_ASSOC) : null;
                                $last_msg_id = $row_msg['max_id'] ?? 0;
                                ?>
                                <table id="tabela-chamados" class="table table-hover mb-0 align-middle" data-last-id="<?= $last_id ?>" data-last-msg-id="<?= $last_msg_id ?>">
                                    <thead class="table-light">
                                        <tr>
                                            <th>N°</th>
                                            <th>Requisitante</th>
                                            <th>Setor</th>
                                            <th>Descrição</th>
                                            <th>Anexo</th>
                                            <th>Data/Hora</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                            <?php if($_SESSION['PERFIL'] == "MASTER"): ?><th>Admin</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($chamados)): ?>
                                            <?php foreach ($chamados as $chamado): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= $chamado['id'] ?></td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="fw-bold"><?= htmlspecialchars($chamado['nome']) ?></span>
                                                            <small class="text-muted"><?= htmlspecialchars($chamado['email']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($chamado['setor']) ?></td>
                                                    <td><?= nl2br(stripcslashes(truncateText(htmlspecialchars($chamado['descricao']), 50))) ?></td>
                                                    <td>
                                                        <?php if(!empty($chamado['path'])): ?>
                                                            <a href="<?= htmlspecialchars($chamado['path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-paperclip"></i></a>
                                                        <?php else: ?>
                                                            <span class="text-muted small">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= date('d/m/Y H:i', strtotime($chamado['data_hora'])) ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = ['ABERTO' => 'bg-success', 'EM ATENDIMENTO' => 'bg-warning text-dark', 'FECHADO' => 'bg-danger'][$chamado['status']];
                                                        $status_info = '';
                                                        if ($chamado['status'] == 'EM ATENDIMENTO' && !empty($chamado['atendente_nome'])) {
                                                            $status_info = 'Atendente: ' . htmlspecialchars($chamado['atendente_nome']);
                                                        } elseif ($chamado['status'] == 'FECHADO' && !empty($chamado['fechado_por_nome'])) {
                                                            $status_info = 'Fechado por: ' . htmlspecialchars($chamado['fechado_por_nome']);
                                                        }
                                                        ?>
                                                        <div class="status-badge" data-bs-toggle="tooltip" title="<?= $status_info ?>">
                                                            <span class="badge <?= $status_class ?>"><?= htmlspecialchars($chamado['status']) ?></span>
                                                            <?php if($status_info): ?>
                                                                <div class="status-info d-none d-md-block"><small><?= $status_info ?></small></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="carregarDetalhes(<?= $chamado['id'] ?>)"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#detalhesModalChamado"
                                                                    title="Ver Detalhes">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <a href="acompanhar_chamado.php?id=<?= $chamado['id'] ?>"
                                                               target="_blank"
                                                               class="btn btn-sm btn-outline-info"
                                                               title="Abrir Chat do Chamado">
                                                                <i class="fas fa-comments"></i>
                                                            </a>
                                                            <?php
                                                            $btn_text  = ['ABERTO' => 'Atender', 'EM ATENDIMENTO' => 'Fechar', 'FECHADO' => 'Reabrir'][$chamado['status']];
                                                            $btn_class = ['ABERTO' => 'btn-primary', 'EM ATENDIMENTO' => 'btn-danger', 'FECHADO' => 'btn-success'][$chamado['status']];
                                                            ?>
                                                            <a href="update_status.php?ID=<?= $chamado['id'] ?>&pagina=<?= $pagina_atual ?>&setor=<?= urlencode($filtro_setor) ?><?= $filtro_status ? '&status='.urlencode($filtro_status) : '' ?>"
                                                               class="btn btn-sm <?= $btn_class ?> btn-action">
                                                                <?= $btn_text ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <?php if($_SESSION['PERFIL'] == "MASTER"): ?>
                                                    <td>
                                                        <a href="excluir.php?ID=<?= $chamado['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?= $_SESSION['PERFIL'] == 'MASTER' ? 9 : 8 ?>" class="text-center py-4 text-muted">
                                                    Nenhum chamado encontrado para os filtros selecionados.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if($total_paginas > 1): ?>
                            <div class="card-footer bg-light border-top">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if($pagina_atual > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?pagina=1&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&laquo;&laquo;</a></li>
                                            <li class="page-item"><a class="page-link" href="?pagina=<?= $pagina_atual-1 ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&laquo;</a></li>
                                        <?php endif; ?>
                                        <?php
                                        $inicio = max(1, $pagina_atual - 2);
                                        $fim    = min($total_paginas, $pagina_atual + 2);
                                        for($i = $inicio; $i <= $fim; $i++): ?>
                                            <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
                                                <a class="page-link" href="?pagina=<?= $i ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if($pagina_atual < $total_paginas): ?>
                                            <li class="page-item"><a class="page-link" href="?pagina=<?= $pagina_atual+1 ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&raquo;</a></li>
                                            <li class="page-item"><a class="page-link" href="?pagina=<?= $total_paginas ?>&setor=<?= urlencode($filtro_setor) ?>&status=<?= urlencode($filtro_status) ?>">&raquo;&raquo;</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Aba 2: Lista de Conversas Focada -->
                        <div class="tab-pane fade" id="pills-chats" role="tabpanel" aria-labelledby="pills-chats-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Chamado</th>
                                            <th>Solicitante</th>
                                            <th>Última Interação</th>
                                            <th>Status Atual</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_chats = "SELECT c.id, c.nome, c.status, MAX(m.criado_em) as ultima_msg 
                                                      FROM chamado c 
                                                      INNER JOIN mensagens_chamado m ON c.id = m.chamado_id 
                                                      GROUP BY c.id 
                                                      ORDER BY ultima_msg DESC 
                                                      LIMIT 20";
                                        $res_chats = $conn->query($sql_chats);
                                        if ($res_chats && $res_chats->rowCount() > 0):
                                            $chats = $res_chats->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($chats as $chat_row):
                                                $st_class = ['ABERTO' => 'bg-success', 'EM ATENDIMENTO' => 'bg-warning text-dark', 'FECHADO' => 'bg-danger'][$chat_row['status']];
                                        ?>
                                            <tr>
                                                <td class="fw-bold">#<?= $chat_row['id'] ?></td>
                                                <td><?= htmlspecialchars($chat_row['nome']) ?></td>
                                                <td><small class="text-muted"><?= date('d/m H:i', strtotime($chat_row['ultima_msg'])) ?></small></td>
                                                <td><span class="badge <?= $st_class ?>"><?= $chat_row['status'] ?></span></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="acompanhar_chamado.php?id=<?= $chat_row['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white">
                                                            <i class="fas fa-comment-dots me-1"></i> Abrir Chat
                                                        </a>
                                                        <?php if($_SESSION['PERFIL'] == "MASTER"): ?>
                                                        <a href="excluir.php?ID=<?= $chat_row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja excluir este chamado e todo o histórico deste chat PERMANENTEMENTE?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="fas fa-comment-slash fa-2x mb-3"></i><br>
                                                    Nenhuma conversa iniciada ainda.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModalChamado" tabindex="-1" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesModalLabel">Detalhes do Chamado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBodyContentChamado">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function toggleTheme() {
        const doc  = document.documentElement;
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
        // Sincroniza ícone/texto com o tema já aplicado pelo script inline no <head>
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            const icon = document.getElementById('theme-icon');
            const text = document.getElementById('theme-text');
            if (icon) icon.classList.replace('fa-moon', 'fa-sun');
            if (text) text.innerText = 'Modo Claro';
        }

        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        let lastId        = parseInt(document.getElementById('tabela-chamados').getAttribute('data-last-id')) || 0;
        let lastMsgId     = parseInt(document.getElementById('tabela-chamados').getAttribute('data-last-msg-id')) || 0;
        const setorSelect = document.getElementById('setor');
        const audio       = document.getElementById('notification-sound');

        function showBrowserNotification(title, body) {
            if (!('Notification' in window)) return;
            if (Notification.permission === 'granted') {
                new Notification(title, { body, icon: './imgs/icone.png' });
                if (audio) audio.play().catch(() => {});
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(p => {
                    if (p === 'granted') {
                        new Notification(title, { body, icon: './imgs/icone.png' });
                        if (audio) audio.play().catch(() => {});
                    }
                });
            }
        }

        const btnTeste = document.getElementById('test-notification-btn');
        if (btnTeste) {
            btnTeste.addEventListener('click', () => {
                showBrowserNotification('Notificação de Teste ⚙️', 'Se você está vendo isso, as permissões estão funcionando!');
            });
        }

        async function checkNewChamados() {
            if (!setorSelect) return;
            const filtroSetor  = setorSelect.value;
            const filtroStatus = new URLSearchParams(window.location.search).get('status') || '';
            const url = `check_new_chamados.php?last_id=${lastId}&last_msg_id=${lastMsgId}&setor=${encodeURIComponent(filtroSetor)}&status=${encodeURIComponent(filtroStatus)}`;
            try {
                const response = await fetch(url);
                if (!response.ok) return;
                const data = await response.json();
                
                // Novo Chamado
                if (data.has_new) {
                    showBrowserNotification('Novo Chamado!', 'Um novo chamado foi aberto ou a fila atualizou.');
                    lastId = data.new_id;
                }
                
                // Nova Mensagem em Chat Existente
                if (data.has_new_msg) {
                    showBrowserNotification('Nova Mensagem!', 'Há novas interações nos chats de atendimento.');
                    lastMsgId = data.new_msg_id;
                }
            } catch (e) {
                console.error('Erro ao verificar atualizações:', e);
            }
        }

        setInterval(checkNewChamados, 10000);
    });

    function carregarDetalhes(id) {
        const modalBody = document.getElementById('modalBodyContentChamado');
        if (!modalBody) return;
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
        fetch(`detalhes_chamado.php?id=${id}`)
            .then(r => { if (!r.ok) throw new Error('Erro de rede.'); return r.text(); })
            .then(data => { modalBody.innerHTML = data; })
            .catch(() => { modalBody.innerHTML = '<div class="alert alert-danger">Não foi possível carregar os detalhes.</div>'; });
    }
    </script>
</body>
</html>
<?php $conn = null; ?>