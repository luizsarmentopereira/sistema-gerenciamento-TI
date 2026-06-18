<?php
session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php');

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

function todasTarefasConcluidasHoje($conn) {
    $hoje = date('Y-m-d');
    $dia_semana = date('N');
    $dia_mes = date('j');
    $agenda_mes = 'm-' . $dia_mes;

    $sql_count = "SELECT COUNT(*) as total 
                  FROM checklist_tarefas 
                  WHERE data_modificacao = :hoje
                    AND (agendamento = 'todos' 
                         OR agendamento = :dia_semana 
                         OR agendamento = :agenda_mes)";
    $stmt = $conn->prepare($sql_count);
    $stmt->execute(['hoje' => $hoje, 'dia_semana' => $dia_semana, 'agenda_mes' => $agenda_mes]);
    $total = $stmt->fetchColumn();
    if ($total == 0) return true;

    $sql_done = "SELECT COUNT(*) as done 
                 FROM checklist_tarefas 
                 WHERE data_modificacao = :hoje
                   AND (agendamento = 'todos' 
                        OR agendamento = :dia_semana 
                        OR agendamento = :agenda_mes)
                   AND concluida = true";
    $stmt = $conn->prepare($sql_done);
    $stmt->execute(['hoje' => $hoje, 'dia_semana' => $dia_semana, 'agenda_mes' => $agenda_mes]);
    $done = $stmt->fetchColumn();

    return $done == $total;
}

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