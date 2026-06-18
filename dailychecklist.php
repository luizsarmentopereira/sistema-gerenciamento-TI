<?php
session_start();

if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php'); 

$data_hoje = date('Y-m-d');
$dia_semana_hoje = date('N'); // 1 a 7
$dia_mes_hoje = date('j');    // 1 a 31

// Virada de dia automática (reseta conclusões e limpa dados de quem concluiu)
$stmt = $conn->prepare("SELECT id FROM checklist_tarefas WHERE data_modificacao < :hoje LIMIT 1");
$stmt->execute(['hoje' => $data_hoje]);
if ($stmt->rowCount() > 0) {
    $stmt = $conn->prepare("UPDATE checklist_tarefas 
                            SET concluida = false, 
                                concluido_por = NULL, 
                                concluido_em = NULL, 
                                data_modificacao = :hoje");
    $stmt->execute(['hoje' => $data_hoje]);
}

// Processamento de Requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'add') {
        $text = isset($_POST['text']) ? trim($_POST['text']) : '';
        $desc = isset($_POST['desc']) ? trim($_POST['desc']) : '';
        $freq = isset($_POST['freq']) ? trim($_POST['freq']) : 'Diário';
        
        $agendamento = 'todos';
        if ($freq === 'Semanal' && isset($_POST['dia_semana'])) {
            $agendamento = $_POST['dia_semana'];
        } elseif ($freq === 'Mensal' && isset($_POST['dia_mes'])) {
            $agendamento = 'm-' . (int)$_POST['dia_mes'];
        }

        $badge = 'badge-custom';
        if ($freq === 'Diário') $badge = 'badge-daily';
        if ($freq === 'Semanal') $badge = 'badge-weekly';

        if (!empty($text)) {
            $stmt = $conn->query("SELECT MAX(ordem) as ultima FROM checklist_tarefas");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $nova_ordem = ((int)$row['ultima']) + 1;

            $sql = "INSERT INTO checklist_tarefas (texto, descricao, frequencia, agendamento, badge, concluida, ordem, data_modificacao) 
                    VALUES (:text, :desc, :freq, :agend, :badge, false, :ordem, :hoje)";
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute([
                'text' => $text,
                'desc' => $desc,
                'freq' => $freq,
                'agend' => $agendamento,
                'badge' => $badge,
                'ordem' => $nova_ordem,
                'hoje' => $data_hoje
            ]);
            echo json_encode(['success' => $success]);
        }
        exit();
    }

    if ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $text = isset($_POST['text']) ? trim($_POST['text']) : '';
        $desc = isset($_POST['desc']) ? trim($_POST['desc']) : '';
        
        if ($id > 0 && !empty($text)) {
            $stmt = $conn->prepare("UPDATE checklist_tarefas SET texto = :text, descricao = :desc WHERE id = :id");
            $success = $stmt->execute(['text' => $text, 'desc' => $desc, 'id' => $id]);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        }
        exit();
    }

    if ($action === 'toggle') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $done = isset($_POST['done']) ? (int)$_POST['done'] : 0;
        $usuario_id = $_SESSION['ID'];
        
        if ($done == 1) {
            $stmt = $conn->prepare("UPDATE checklist_tarefas 
                                    SET concluida = true, 
                                        concluido_por = :usuario, 
                                        concluido_em = CURRENT_TIMESTAMP 
                                    WHERE id = :id");
            $success = $stmt->execute(['usuario' => $usuario_id, 'id' => $id]);
        } else {
            $stmt = $conn->prepare("UPDATE checklist_tarefas 
                                    SET concluida = false, 
                                        concluido_por = NULL, 
                                        concluido_em = NULL 
                                    WHERE id = :id");
            $success = $stmt->execute(['id' => $id]);
        }
        echo json_encode(['success' => $success]);
        exit();
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $stmt = $conn->prepare("DELETE FROM checklist_tarefas WHERE id = :id");
        $success = $stmt->execute(['id' => $id]);
        echo json_encode(['success' => $success]);
        exit();
    }

    if ($action === 'reorder') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (is_array($ids)) {
            foreach ($ids as $index => $id) {
                $id = (int)$id;
                $ordem = $index + 1;
                $stmt = $conn->prepare("UPDATE checklist_tarefas SET ordem = :ordem WHERE id = :id");
                $stmt->execute(['ordem' => $ordem, 'id' => $id]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    if ($action === 'reset_all') {
        $stmt = $conn->prepare("UPDATE checklist_tarefas 
                                SET concluida = false, 
                                    concluido_por = NULL, 
                                    concluido_em = NULL, 
                                    data_modificacao = :hoje 
                                WHERE agendamento = 'todos' 
                                   OR agendamento = :dia_semana 
                                   OR agendamento = :agenda_mes");
        $success = $stmt->execute([
            'hoje' => $data_hoje,
            'dia_semana' => $dia_semana_hoje,
            'agenda_mes' => 'm-' . $dia_mes_hoje
        ]);
        echo json_encode(['success' => $success]);
        exit();
    }
}

// Lógica de Seleção das Tarefas (com JOIN para obter nome do usuário)
$agenda_mes_alvo = 'm-' . $dia_mes_hoje;
$query_completa = "
    SELECT t.id, 
           t.texto as text, 
           t.descricao as desc, 
           t.frequencia as freq, 
           t.badge, 
           t.concluida as done, 
           t.agendamento,
           t.concluido_por,
           t.concluido_em,
           u.nome as nome_usuario
    FROM checklist_tarefas t
    LEFT JOIN users u ON t.concluido_por = u.id
    ORDER BY t.ordem ASC
";

$tarefas_ativas = [];
$tarefas_inativas = [];

$stmt = $conn->query($query_completa);
if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id'] = (int)$row['id'];
        $row['done'] = $row['done'] === 't' || $row['done'] === true || $row['done'] === 1;
        $row['concluido_por'] = $row['concluido_por'] ? (int)$row['concluido_por'] : null;
        $row['concluido_em'] = $row['concluido_em'];
        $row['nome_usuario'] = $row['nome_usuario'] ?? null;

        $eh_de_hoje = ($row['agendamento'] === 'todos' || $row['agendamento'] == $dia_semana_hoje || $row['agendamento'] === $agenda_mes_alvo);

        if ($eh_de_hoje) {
            $row['bloqueada'] = false;
            $tarefas_ativas[] = $row;
        } else {
            $row['bloqueada'] = true;
            $dias_traducao = ['1'=>'Segunda', '2'=>'Terça', '3'=>'Quarta', '4'=>'Quinta', '5'=>'Sexta', '6'=>'Sábado', '7'=>'Domingo'];
            if (isset($dias_traducao[$row['agendamento']])) {
                $row['freq'] = 'Programada: ' . $dias_traducao[$row['agendamento']];
            } else if (strpos($row['agendamento'], 'm-') === 0) {
                $row['freq'] = 'Todo dia ' . str_replace('m-', '', $row['agendamento']);
            }
            $tarefas_inativas[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Checklist - COREN-PE</title>
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
        .card-gerenciador { border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: none; }
        .card-header-gerenciador { background: linear-gradient(135deg, #0d6efd, rgb(108, 118, 121)); color: white; border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem; padding: 1rem 1.5rem; }
        .sidebar { display: flex !important; flex-direction: column !important; height: 100vh !important; }
        .sidebar .nav.flex-column { flex: 1; overflow-y: auto; }
        .sidebar-bottom { margin-top: auto; }

        [data-theme="dark"] body { background-color: #121212; color: #e0e0e0; }
        [data-theme="dark"] .content { background-color: #121212; }
        [data-theme="dark"] h2, [data-theme="dark"] h4, [data-theme="dark"] h5, [data-theme="dark"] h6 { color: #ffffff !important; }
        [data-theme="dark"] .sidebar { filter: brightness(0.85); }
        [data-theme="dark"] .card-gerenciador { background-color: #1e1e1e; color: #e0e0e0; }
        [data-theme="dark"] .form-control, [data-theme="dark"] .form-select { background-color: #2b2b2b; color: #e0e0e0; border-color: #444; }

        .list-group-item { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            border-radius: 8px !important; 
            margin-bottom: 6px; 
            border: 1px solid rgba(0,0,0,0.06); 
            padding: 10px 14px; 
            background-color: #fff;
        }
        
        .list-group-item:hover, 
        .list-group-item:focus-within { 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            transform: translateY(-2px); 
            border-color: rgba(13, 110, 253, 0.25);
        }

        .item-descricao { 
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            margin-top: 0;
            display: block;
            font-size: 0.8rem;
            font-weight: normal;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease, margin-top 0.3s ease;
        }
        
        .list-group-item:hover .item-descricao,
        .list-group-item:focus-within .item-descricao { 
            max-height: 150px; 
            opacity: 1;
            margin-top: 5px;
        }

        .item-bloqueado:hover .item-descricao,
        .item-bloqueado:focus-within .item-descricao {
            opacity: 0.65;
        }

        .form-check-input { cursor: pointer; transform: scale(1.2); margin-right: 12px; }
        .form-check-input:checked { background-color: #198754; border-color: #198754; }
        
        /* CORREÇÃO: RISCO APENAS NO TÍTULO E DESCRIÇÃO, NÃO NO "FEITO POR" */
        .item-concluido .txt-title { 
            text-decoration: line-through; 
            color: #6c757d; 
            opacity: 0.7; 
        }
        .item-concluido .item-descricao { 
            text-decoration: line-through; 
            opacity: 0.5; 
        }
        .item-concluido .concluido-por {
            text-decoration: none !important;
            opacity: 1 !important;
        }
        
        .btn-acao-item { opacity: 0; transition: all 0.2s; border: none; background: transparent; padding: 5px 8px; border-radius: 5px; cursor: pointer; }
        .list-group-item:hover .btn-acao-item,
        .list-group-item:focus-within .btn-acao-item { opacity: 1; }
        
        .btn-editar { color: #0d6efd; }
        .btn-editar:hover { background: rgba(13, 110, 253, 0.1) !important; transform: scale(1.1); }
        .btn-lixeira { color: #dc3545; }
        .btn-lixeira:hover { background: #dc3545 !important; color: white !important; transform: scale(1.1); }
        
        .form-control-sm, .form-select-sm { font-size: 0.85rem; padding: 5px 8px; }

        [data-theme="dark"] .list-group-item { border-color: #333; background-color: #1e1e1e; color: #e0e0e0; }
        [data-theme="dark"] .list-group-item:hover, [data-theme="dark"] .list-group-item:focus-within { border-color: rgba(110, 168, 254, 0.35); background-color: #242424; }
        [data-theme="dark"] .item-concluido { background-color: #161616 !important; }

        .badge-daily { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; border: 1px solid #0d6efd; }
        .badge-weekly { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; border: 1px solid #ffc107; }
        .badge-custom { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; border: 1px solid #6c757d; }
        .drag-handle { cursor: grab; font-size: 1.1rem; color: #adb5bd; padding-right: 12px; display: flex; align-items: center; }

        .item-bloqueado { opacity: 0.5; background-color: rgba(0, 0, 0, 0.02) !important; cursor: not-allowed !important; border-style: dashed; }
        [data-theme="dark"] .item-bloqueado { background-color: rgba(255, 255, 255, 0.02) !important; }
        .item-bloqueado .drag-handle { display: none; }
        
        .divisor-turno { border: none; border-top: 2px dashed #adb5bd; margin: 25px 0 15px 0; position: relative; text-align: center; overflow: visible; }
        .divisor-turno::after { content: "Tarefas Agendadas para Outros Dias"; position: absolute; top: -13px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 15px; font-size: 0.85rem; font-weight: bold; color: #6c757d; border-radius: 20px; border: 1px solid #adb5bd; }
        [data-theme="dark"] .divisor-turno::after { background: #121212; color: #a0aab2; }

        .edicao-input-box input, .edicao-input-box textarea { font-size: 0.85rem; padding: 4px 8px; }

        /* Estilo para exibir quem concluiu (sem risco) */
        .concluido-por {
            font-size: 0.75rem;
            color: #28a745;
            font-weight: 600;
            margin-left: 6px;
            text-decoration: none !important;
        }
        .concluido-por i {
            margin-right: 4px;
        }
        .concluido-por .hora {
            font-weight: normal;
            color: #6c757d;
            font-size: 0.7rem;
        }
        [data-theme="dark"] .concluido-por .hora { color: #a0aab2; }
    </style>
</head>
<body>
        <div class="d-flex">
            <?php include 'menu.php'; ?>
            <div class="content p-4 w-100">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-clipboard-check me-2"></i> Checklist Diário - DTI</h2>
                <div class="badge bg-secondary fs-6 p-2" id="current-date"><i class="far fa-calendar-alt me-2"></i><span></span></div>
            </div>

            <div class="alert alert-success d-none align-items-center mb-4 shadow-sm" id="celebration" role="alert">
                <i class="fas fa-trophy fs-4 me-3"></i>
                <div><strong>Excelente!</strong> Todas as rotinas ativas de hoje foram cumpridas.</div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card card-gerenciador mb-3 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-muted mb-3"><i class="fas fa-chart-pie me-2"></i>Progresso de Hoje</h5>
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <h2 class="mb-0 fw-bold"><span id="done-count" class="text-primary">0</span> <small class="text-muted">/ <span id="total-count">0</span></small></h2>
                                <span class="badge bg-primary rounded-pill fs-6" id="progress-pct">0%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progress-fill" style="width: 0%;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mb-3">
                        <button class="btn btn-outline-primary btn-sm shadow-sm" id="btn-hide"><i class="fas fa-eye-slash me-2"></i> <span>Ocultar marcados</span></button>
                        <button class="btn btn-outline-danger btn-sm shadow-sm" id="btn-reset"><i class="fas fa-redo-alt me-2"></i> Desmarcar Ativas</button>
                    </div>

                    <div class="card card-gerenciador shadow-sm">
                        <div class="card-body p-3">
                            <h6 class="card-title text-muted mb-3"><i class="fas fa-plus-circle me-2"></i>Nova rotina programada</h6>
                            <form id="form-add-task">
                                <div class="mb-2">
                                    <input type="text" class="form-control form-control-sm" id="new-task-text" placeholder="Ex: Reiniciar servidor de impressão..." required>
                                </div>
                                <div class="mb-2">
                                    <textarea class="form-control form-control-sm" id="new-task-desc" placeholder="Adicionar descrição da tarefa (Opcional)..." rows="2"></textarea>
                                </div>
                                <div class="d-flex gap-1 align-items-center">
                                    <select class="form-select form-select-sm flex-grow-1" id="new-task-freq">
                                        <option value="Diário">Diário</option>
                                        <option value="Semanal">Semanal</option>
                                        <option value="Mensal">Mensal</option>
                                    </select>
                                    <select class="form-select form-select-sm d-none" id="new-task-day-week" style="max-width: 75px;">
                                        <option value="1">Seg</option><option value="2">Ter</option><option value="3">Qua</option>
                                        <option value="4">Qui</option><option value="5">Sex</option><option value="6">Sáb</option><option value="7">Dom</option>
                                    </select>
                                    <input type="number" class="form-control form-control-sm d-none" id="new-task-day-month" min="1" max="31" placeholder="Dia" style="max-width: 60px;">
                                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3 text-nowrap"><i class="fas fa-plus me-1"></i>Criar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-gerenciador shadow-sm mb-3">
                        <div class="card-header-gerenciador d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i> Quadro de Rotinas</h5>
                            <span class="badge bg-light text-primary rounded-pill" id="items-count">0 itens</span>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group list-group-flush" id="checklist-items" style="gap: 5px;"></ul>
                            <hr class="divisor-turno d-none" id="divisor-inativos">
                            <ul class="list-group list-group-flush" id="checklist-inativos-items" style="gap: 5px;"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <script>
    let ativas = <?php echo json_encode($tarefas_ativas); ?>;
    let inativas = <?php echo json_encode($tarefas_inativas); ?>;
    let hideDone = false;
    let estaEditando = false;

    document.getElementById('new-task-freq').addEventListener('change', function() {
        const selectSemana = document.getElementById('new-task-day-week');
        const inputMes = document.getElementById('new-task-day-month');
        selectSemana.classList.add('d-none'); inputMes.classList.add('d-none');
        if (this.value === 'Semanal') { selectSemana.classList.remove('d-none'); }
        else if (this.value === 'Mensal') { inputMes.classList.remove('d-none'); }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const now = new Date();
        document.querySelector('#current-date span').textContent = now.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        render();
        
        new Sortable(document.getElementById('checklist-items'), {
            handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: function () {
                if (hideDone) return;
                const formData = new FormData();
                ativas.forEach(t => formData.append('ids[]', t.id));
                fetch('dailychecklist.php?action=reorder', { method: 'POST', body: formData });
            }
        });

        const savedTheme = localStorage.getItem('theme') || 'light';
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        if (savedTheme === 'dark') {
            if (icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
            if (text) text.innerText = 'Modo Claro';
        }

        setInterval(buscarAtualizacoesSegundoPlano, 5000);
    });

    function render() {
        const containerAtivas = document.getElementById('checklist-items');
        const containerInativas = document.getElementById('checklist-inativos-items');
        const divisor = document.getElementById('divisor-inativos');

        const totalAtivas = ativas.length;
        const concluidoAtivas = ativas.filter(t => t.done).length;
        const pct = totalAtivas === 0 ? 0 : Math.round((concluidoAtivas / totalAtivas) * 100);

        document.getElementById('done-count').textContent = concluidoAtivas;
        document.getElementById('total-count').textContent = totalAtivas;
        document.getElementById('progress-pct').textContent = pct + '%';
        document.getElementById('progress-fill').style.width = pct + '%';
        document.getElementById('items-count').textContent = (totalAtivas + inativas.length) + ' totais';

        containerAtivas.innerHTML = '';
        if (totalAtivas === 0) {
            containerAtivas.innerHTML = `<li class="list-group-item border-0 text-center py-4 text-muted" style="background: transparent;">Nenhuma rotina ativa para hoje.</li>`;
        } else {
            ativas.forEach(item => {
                if (hideDone && item.done) return;
                const li = document.createElement('li');
                li.className = `list-group-item d-flex align-items-center justify-content-between px-3 ${item.done ? 'item-concluido' : ''}`;
                li.id = `li-item-${item.id}`;
                li.setAttribute('tabindex', '0');
                
                let descHtml = item.desc ? `<small class="text-muted d-block text-break item-descricao">${item.desc}</small>` : '';

                // Monta a string de "Feito por" (sem risco)
                let feitoPorHtml = '';
                if (item.done && item.concluido_por && item.nome_usuario) {
                    let hora = '';
                    if (item.concluido_em) {
                        const data = new Date(item.concluido_em);
                        hora = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                    }
                    feitoPorHtml = `<span class="concluido-por"><i class="fas fa-user-check"></i> Feito por ${item.nome_usuario} <span class="hora">${hora ? 'às ' + hora : ''}</span></span>`;
                }

                li.innerHTML = `
                    <div class="d-flex align-items-center flex-grow-1 item-head-container" id="body-container-${item.id}">
                        <div class="drag-handle"><i class="fas fa-grip-lines"></i></div>
                        <input class="form-check-input" type="checkbox" id="check-${item.id}" ${item.done ? 'checked' : ''}>
                        <div class="ms-2 flex-grow-1 data-content-box">
                            <label class="form-check-label item-texto fw-semibold d-block text-break" for="check-${item.id}">
                                <span class="txt-title">${item.text}</span>
                                ${feitoPorHtml}
                            </label>
                            ${descHtml}
                            <span class="badge ${item.badge} rounded-1" style="font-size: 0.65rem;">${item.freq}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-1 action-buttons-group">
                        <button type="button" class="btn-acao-item btn-editar" onclick="ativarEdicao(${item.id}, false)" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                        <button type="button" class="btn-acao-item btn-lixeira" onclick="deletarTarefa(${item.id}, true)" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                    </div>
                `;

                li.querySelector('.item-head-container').addEventListener('click', function(e) {
                    if (e.target.closest('.drag-handle') || document.getElementById(`edit-box-${item.id}`) || e.target.closest('.edicao-input-box')) return;
                    const checkbox = this.querySelector('input');
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL' && !e.target.classList.contains('txt-title') && !e.target.classList.contains('item-descricao')) checkbox.checked = !checkbox.checked;
                    
                    const formData = new FormData();
                    formData.append('id', item.id);
                    formData.append('done', checkbox.checked ? 1 : 0);

                    fetch('dailychecklist.php?action=toggle', { method: 'POST', body: formData }).then(() => {
                        buscarAtualizacoesSegundoPlano();
                    });
                });
                containerAtivas.appendChild(li);
            });
        }

        containerInativas.innerHTML = '';
        if (inativas.length > 0) {
            divisor.classList.remove('d-none');
            inativas.forEach(item => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex align-items-center justify-content-between px-3 item-bloqueado';
                li.id = `li-item-${item.id}`;
                li.setAttribute('tabindex', '0');
                
                let descHtml = item.desc ? `<small class="text-muted d-block text-break item-descricao">${item.desc}</small>` : '';

                li.innerHTML = `
                    <div class="d-flex align-items-center flex-grow-1 item-head-container" id="body-container-${item.id}">
                        <input class="form-check-input" type="checkbox" disabled style="opacity:0.4;">
                        <div class="ms-2 flex-grow-1 data-content-box">
                            <span class="item-texto fw-semibold d-block text-break text-muted">
                                <span class="txt-title">${item.text}</span>
                            </span>
                            ${descHtml}
                            <span class="badge bg-secondary text-white rounded-1" style="font-size: 0.65rem;">${item.freq}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-1 action-buttons-group">
                        <button type="button" class="btn-acao-item btn-editar" onclick="ativarEdicao(${item.id}, true)" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                        <button type="button" class="btn-acao-item btn-lixeira" onclick="deletarTarefa(${item.id}, false)" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                    </div>
                `;
                containerInativas.appendChild(li);
            });
        } else {
            divisor.classList.add('d-none');
        }

        const celebration = document.getElementById('celebration');
        if (pct === 100 && totalAtivas > 0) celebration.classList.replace('d-none', 'd-flex');
        else celebration.classList.replace('d-flex', 'd-none');
    }

    function buscarAtualizacoesSegundoPlano() {
        if (estaEditando) return;

        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const scripts = doc.querySelectorAll('script');
                scripts.forEach(script => {
                    if (script.textContent.includes('let ativas =')) {
                        const regexAtivas = /let ativas = (\[.*?\]);/;
                        const regexInativas = /let inativas = (\[.*?\]);/;
                        
                        const matchAtivas = script.textContent.match(regexAtivas);
                        const matchInativas = script.textContent.match(regexInativas);
                        
                        if (matchAtivas && matchInativas) {
                            ativas = JSON.parse(matchAtivas[1]);
                            inativas = JSON.parse(matchInativas[1]);
                            render();
                        }
                    }
                });
            }).catch(err => console.error('Erro de sincronização:', err));
    }

    function ativarEdicao(id, isInativa) {
        estaEditando = true;
        const listaOrigem = isInativa ? inativas : ativas;
        const tarefa = listaOrigem.find(t => t.id === id);
        if(!tarefa) return;
        if(document.getElementById(`edit-box-${id}`)) return;

        const containerBox = document.getElementById(`body-container-${id}`);
        const botoesAcao = document.getElementById(`li-item-${id}`).querySelector('.action-buttons-group');
        
        containerBox.querySelector('.form-check-input').classList.add('d-none');
        containerBox.querySelector('.data-content-box').classList.add('d-none');
        if(containerBox.querySelector('.drag-handle')) containerBox.querySelector('.drag-handle').classList.add('d-none');
        botoesAcao.classList.add('d-none');

        const editDiv = document.createElement('div');
        editDiv.id = `edit-box-${id}`;
        editDiv.className = 'edicao-input-box w-100 me-3';
        editDiv.innerHTML = `
            <input type="text" class="form-control form-control-sm mb-1 fw-bold" id="input-edit-txt-${id}" value="${tarefa.text.replace(/"/g, '&quot;')}">
            <textarea class="form-control form-control-sm mb-1 text-muted" id="input-edit-desc-${id}" rows="2" placeholder="Adicionar descrição da tarefa...">${tarefa.desc}</textarea>
            <div class="d-flex gap-2 mt-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm px-3" style="font-size:0.75rem;" onclick="cancelarEdicao(${id}, ${isInativa})">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-3" style="font-size:0.75rem;" onclick="salvarEdicao(${id}, ${isInativa})">Salvar</button>
            </div>
        `;
        containerBox.appendChild(editDiv);
    }

    function cancelarEdicao(id, isInativa) { estaEditando = false; render(); }

    function salvarEdicao(id, isInativa) {
        const novoTxt = document.getElementById(`input-edit-txt-${id}`).value.trim();
        const novaDesc = document.getElementById(`input-edit-desc-${id}`).value.trim();
        if(novoTxt === "") { alert('O título da tarefa não pode ficar vazio.'); return; }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('text', novoTxt);
        formData.append('desc', novaDesc);

        fetch('dailychecklist.php?action=update', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            estaEditando = false;
            if(data.success) {
                const listaAlvo = isInativa ? inativas : ativas;
                let idx = listaAlvo.findIndex(t => t.id === id);
                if(idx !== -1) { 
                    listaAlvo[idx].text = novoTxt; 
                    listaAlvo[idx].desc = novaDesc; 
                }
                render();
            } else {
                alert('Erro ao atualizar os dados.');
            }
        });
    }

    function deletarTarefa(id, isInativa) {
        if(confirm('Excluir esta tarefa permanentemente?')){
            const formData = new FormData();
            formData.append('id', id);
            fetch('dailychecklist.php?action=delete', { method: 'POST', body: formData }).then(() => {
                if(isInativa) ativas = ativas.filter(t => t.id !== id);
                else inativas = inativas.filter(t => t.id !== id);
                render();
            });
        }
    }

    document.getElementById('form-add-task').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('text', document.getElementById('new-task-text').value);
        formData.append('desc', document.getElementById('new-task-desc').value);
        formData.append('freq', document.getElementById('new-task-freq').value);
        if(document.getElementById('new-task-freq').value === 'Semanal') formData.append('dia_semana', document.getElementById('new-task-day-week').value);
        if(document.getElementById('new-task-freq').value === 'Mensal') formData.append('dia_mes', document.getElementById('new-task-day-month').value);

        fetch('dailychecklist.php?action=add', { method: 'POST', body: formData }).then(() => location.reload());
    });

    document.getElementById('btn-hide').addEventListener('click', function() {
        hideDone = !hideDone;
        this.querySelector('span').textContent = hideDone ? "Mostrar marcados" : "Ocultar marcados";
        render();
    });
    document.getElementById('btn-reset').addEventListener('click', () => {
        if (confirm('Zerar status das tarefas ativas de hoje?')) {
            fetch('dailychecklist.php?action=reset_all', { method: 'POST' }).then(() => location.reload());
        }
    });

    function toggleTheme() {
        const doc  = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        const newTheme = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

        doc.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (newTheme === 'dark') {
            if (icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
            if (text) text.innerText = 'Modo Claro';
        } else {
            if (icon) { icon.classList.remove('fa-sun'); icon.classList.add('fa-moon'); }
            if (text) text.innerText = 'Modo Escuro';
        }
    }
    </script>
</body>
</html>