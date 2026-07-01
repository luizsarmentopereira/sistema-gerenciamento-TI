<?php
session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php');

// ===== FUNÇÕES DE CONTAGEM (cópia da lógica do menu.php) =====
function totalAbertosChamados($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamado WHERE status IN ('ABERTO', 'NOVO')";
    $result = $conn->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}

function totalEmAtendimentoChamados($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamado WHERE status = 'EM ATENDIMENTO'";
    $result = $conn->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}

function totalAbertosRh($conn) {
    $sql = "SELECT COUNT(*) as total FROM (
                SELECT status FROM inclusao_colaborador WHERE status IN ('ABERTO', 'NOVO')
                UNION ALL
                SELECT status FROM exclusao_colaborador WHERE status IN ('ABERTO', 'NOVO')
            ) as notificacoes";
    $result = $conn->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}

function totalEmAtendimentoRh($conn) {
    $sql = "SELECT COUNT(*) as total FROM (
                SELECT status FROM inclusao_colaborador WHERE status IN ('ABERTO', 'NOVO')
                UNION ALL
                SELECT status FROM exclusao_colaborador WHERE status = 'EM ATENDIMENTO'
            ) as notificacoes";
    $result = $conn->query($sql);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}

// ============================================================
// FUNÇÃO CORRIGIDA (idêntica à do menu.php)
// ============================================================
function todasTarefasConcluidasHoje($conn) {
    $hoje = date('Y-m-d');
    $dia_semana = date('N');
    $dia_mes = date('j');
    $agenda_mes = 'm-' . $dia_mes;

    $sql_total = "SELECT COUNT(*) as total 
                  FROM checklist_tarefas 
                  WHERE agendamento = 'todos' 
                     OR agendamento = :dia_semana 
                     OR agendamento = :agenda_mes";
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->execute(['dia_semana' => $dia_semana, 'agenda_mes' => $agenda_mes]);
    $total = (int) $stmt_total->fetchColumn();

    if ($total == 0) return false;

    $sql_done = "SELECT COUNT(*) as done 
                 FROM checklist_tarefas 
                 WHERE (agendamento = 'todos' 
                        OR agendamento = :dia_semana 
                        OR agendamento = :agenda_mes)
                   AND concluida = true
                   AND data_modificacao = :hoje";
    $stmt_done = $conn->prepare($sql_done);
    $stmt_done->execute([
        'dia_semana' => $dia_semana,
        'agenda_mes' => $agenda_mes,
        'hoje' => $hoje
    ]);
    $done = (int) $stmt_done->fetchColumn();

    return $done == $total;
}
// ============================================================

// ===== CALCULAR DADOS =====
$total_abertos_chamados = totalAbertosChamados($conn);
$total_em_atendimento_chamados = totalEmAtendimentoChamados($conn);
$total_abertos_rh = totalAbertosRh($conn);
$total_em_atendimento_rh = totalEmAtendimentoRh($conn);
$checklist_done = todasTarefasConcluidasHoje($conn);

$response = [
    'chamados' => [
        'abertos' => $total_abertos_chamados,
        'em_atendimento' => $total_em_atendimento_chamados
    ],
    'rh' => [
        'abertos' => $total_abertos_rh,
        'em_atendimento' => $total_em_atendimento_rh
    ],
    'checklist_done' => $checklist_done
];

header('Content-Type: application/json');
echo json_encode($response);
?>