<?php
session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

include_once('conexao.php');

$pagina_atual = basename($_SERVER['PHP_SELF']);

$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$ano_anual = isset($_GET['ano_anual']) ? (int)$_GET['ano_anual'] : (int)date('Y');

$mes_anterior = $mes_selecionado - 1; $ano_anterior = $ano_selecionado;
if ($mes_anterior < 1) { $mes_anterior = 12; $ano_anterior--; }
$mes_proximo = $mes_selecionado + 1; $ano_proximo = $ano_selecionado;
if ($mes_proximo > 12) { $mes_proximo = 1; $ano_proximo++; }

$meses = [1=>"Janeiro", 2=>"Fevereiro", 3=>"Março", 4=>"Abril", 5=>"Maio", 6=>"Junho", 7=>"Julho", 8=>"Agosto", 9=>"Setembro", 10=>"Outubro", 11=>"Novembro", 12=>"Dezembro"];
$nome_mes = $meses[$mes_selecionado];

function formatarNome($nomeCompleto) {
    $partes = explode(' ', trim($nomeCompleto));
    if (count($partes) > 1) {
        return $partes[0] . ' ' . end($partes);
    }
    return $partes[0];
}

// 1. Ranking Mensal (corrigido GROUP BY)
$sql_ranking = "SELECT f.nome, COUNT(c.id) as total FROM chamado c 
                INNER JOIN users f ON c.fechado_por = f.id 
                WHERE c.status = 'FECHADO' 
                AND EXTRACT(MONTH FROM c.data_hora) = $mes_selecionado 
                AND EXTRACT(YEAR FROM c.data_hora) = $ano_selecionado
                GROUP BY f.nome 
                ORDER BY total DESC";
$result_ranking = $conn->query($sql_ranking);
$ranking_mensal = $result_ranking ? $result_ranking->fetchAll(PDO::FETCH_ASSOC) : [];
$total_ranking_count = count($ranking_mensal);

// 2. Gráfico Horas (corrigido EXTRACT)
$sql_horas = "SELECT EXTRACT(HOUR FROM data_hora) as hora, COUNT(*) as total FROM chamado 
              WHERE status = 'FECHADO' 
              AND EXTRACT(MONTH FROM data_hora) = $mes_selecionado 
              AND EXTRACT(YEAR FROM data_hora) = $ano_selecionado
              AND EXTRACT(HOUR FROM data_hora) BETWEEN 7 AND 17
              GROUP BY hora ORDER BY hora ASC";
$result_horas = $conn->query($sql_horas);
$horas_dados = array_fill(7, 11, 0);
$horas_labels = ["07h", "08h", "09h", "10h", "11h", "12h", "13h", "14h", "15h", "16h", "17h"];
if($result_horas) {
    while($row = $result_horas->fetch(PDO::FETCH_ASSOC)) {
        $hora = (int)$row['hora'];
        if ($hora >= 7 && $hora <= 17) {
            $horas_dados[$hora] = (int)$row['total'];
        }
    }
}

// 3. Ranking Geral (corrigido GROUP BY)
$sql_geral = "SELECT f.nome, COUNT(c.id) as total FROM chamado c 
              INNER JOIN users f ON c.fechado_por = f.id 
              WHERE c.status = 'FECHADO' 
              GROUP BY f.nome 
              ORDER BY total DESC";
$result_geral = $conn->query($sql_geral);
$ranking_geral = $result_geral ? $result_geral->fetchAll(PDO::FETCH_ASSOC) : [];

// 4. Gráfico Anual (corrigido EXTRACT)
$sql_meses = "SELECT EXTRACT(MONTH FROM data_hora) as mes, COUNT(*) as total FROM chamado 
              WHERE status = 'FECHADO' AND EXTRACT(YEAR FROM data_hora) = $ano_anual
              GROUP BY mes ORDER BY mes ASC";
$result_meses = $conn->query($sql_meses);
$dados_meses = array_fill(1, 12, 0);
if($result_meses) {
    while($row = $result_meses->fetch(PDO::FETCH_ASSOC)) {
        $dados_meses[(int)$row['mes']] = (int)$row['total'];
    }
}
$labels_meses = array_values($meses);
$valores_meses = array_values($dados_meses);

function countTotalGeral($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamado WHERE status = 'FECHADO'";
    $res = $conn->query($sql);
    $row = $res->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}

function countTotalMes($conn, $m, $a) {
    $sql = "SELECT COUNT(*) as total FROM chamado WHERE status = 'FECHADO' 
            AND EXTRACT(MONTH FROM data_hora) = $m 
            AND EXTRACT(YEAR FROM data_hora) = $a";
    $res = $conn->query($sql);
    $row = $res->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="./imgs/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="anima.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Evita Flash Mode -->
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    
    <style>
        :root {
            --primary-blue: #0d6efd;
            --primary-dark: #084298;
            --bg-body: #f4f7f6;
            --bg-card: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --sidebar-active-bg: #ffffff;
            --sidebar-active-text: #0d6efd;
            --table-head-bg: #f8f9fa;
            --stat-card-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-body: #0f1117;
            --bg-card: #181b23;
            --bg-card-elevated: #1f2330;
            --text-main: #e8eaf0;
            --text-muted: #8b93a7;
            --border-color: rgba(255, 255, 255, 0.08);
            --sidebar-active-bg: #333333;
            --sidebar-active-text: #6ea8fe;
            --table-head-bg: #1c1f29;
            --stat-card-bg: #181b23;
            --primary-blue: #4d8eff;
            --primary-dark: #2563eb;
            --bs-body-bg: #0f1117;
            --bs-body-color: #e8eaf0;
            --bs-table-color: #e8eaf0;
            --bs-table-bg: #181b23;
            --bs-table-border-color: rgba(255, 255, 255, 0.08);
            --bs-table-striped-bg: #1c1f29;
            --bs-table-hover-bg: #222631;
        }

        body { overflow-x: hidden; font-family: 'Segoe UI', sans-serif; transition: background-color 0.3s; margin: 0; padding: 0; }
        
        .card-scoreboard { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 2px 10px rgba(0,0,0,0.04); margin-bottom: 16px; overflow: hidden; transition: background-color 0.3s, border-color 0.3s, box-shadow 0.3s; }
        [data-theme="dark"] .card-scoreboard { box-shadow: 0 4px 20px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.04); }
        .header-blue { background: var(--primary-blue); color: white; padding: 10px 16px; font-weight: 600; font-size: 0.85rem; }
        [data-theme="dark"] .header-blue { background: linear-gradient(135deg, #2563eb, #4d8eff); }
        
        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .page-header h3 { margin: 0; font-weight: 700; font-size: 1.4rem; }

        .nav-mes-pill { background: var(--bg-card); border-radius: 50px; padding: 4px 4px; border: 1px solid var(--border-color); display: inline-flex; align-items: center; color: var(--text-main); box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: background 0.3s, border-color 0.3s; }
        [data-theme="dark"] .nav-mes-pill { box-shadow: 0 2px 12px rgba(0,0,0,0.4); }
        .btn-seta { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; color: var(--primary-blue); text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .btn-seta:hover { background: rgba(13, 110, 253, 0.1); transform: scale(1.1); color: var(--primary-blue); }
        [data-theme="dark"] .btn-seta { color: #6ea8fe; }
        [data-theme="dark"] .btn-seta:hover { background: rgba(77, 142, 255, 0.15); color: #93bfff; }
        .mes-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; padding: 0 12px; letter-spacing: 0.5px; white-space: nowrap; }

        .stat-cards { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-card { background: var(--stat-card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 16px; flex: 1; min-width: 120px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s, border-color 0.3s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        [data-theme="dark"] .stat-card { box-shadow: 0 2px 12px rgba(0,0,0,0.3); }
        [data-theme="dark"] .stat-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.4); border-color: rgba(255,255,255,0.12); }
        .stat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }

        [data-theme="light"] .stat-icon.blue { background: rgba(13, 110, 253, 0.1); color: var(--primary-blue); }
        [data-theme="light"] .stat-icon.red { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        [data-theme="light"] .stat-icon.green { background: rgba(25, 135, 84, 0.1); color: #198754; }
        [data-theme="light"] .stat-icon.orange { background: rgba(255, 193, 7, 0.2); color: #e09600; }

        .stat-icon.blue { background: rgba(13, 110, 253, 0.15); color: #4dabf7; }
        .stat-icon.red { background: rgba(220, 53, 69, 0.15); color: #ff8787; }
        .stat-icon.green { background: rgba(25, 135, 84, 0.15); color: #63e6be; }
        .stat-icon.orange { background: rgba(255, 193, 7, 0.15); color: #ffd43b; }
        [data-theme="dark"] .stat-icon.blue { background: rgba(77, 142, 255, 0.18); color: #6ea8fe; }
        [data-theme="dark"] .stat-icon.red { background: rgba(255, 99, 99, 0.18); color: #ff6b6b; }
        [data-theme="dark"] .stat-icon.green { background: rgba(81, 207, 102, 0.18); color: #51cf66; }
        [data-theme="dark"] .stat-icon.orange { background: rgba(255, 212, 59, 0.18); color: #ffd43b; }

        .stat-info { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.2rem; font-weight: 700; line-height: 1.2; color: var(--text-main); }
        [data-theme="dark"] .stat-value { color: #f1f3f5; }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 600; }
        [data-theme="dark"] .stat-label { color: #7a8299; }

        .btn-seta-header { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: 0.2s; background: rgba(255,255,255,0.15); font-size: 0.75rem; }
        .btn-seta-header:hover { background: rgba(255,255,255,0.3); color: white; transform: scale(1.15); }

        .table { color: var(--text-main) !important; margin-bottom: 0; font-size: 0.85rem; }
        .table th { border-bottom-color: var(--border-color) !important; background-color: transparent !important; color: inherit !important; padding: 8px 12px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        .table td { border-bottom-color: var(--border-color) !important; background-color: transparent !important; color: inherit !important; padding: 6px 12px; transition: all 0.2s ease; }
        
        .table-light { background-color: var(--table-head-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .table-light, 
        [data-theme="dark"] .table-light th { 
            background-color: #1c1f29 !important; 
            color: #a0a8be !important; 
            border-color: rgba(255,255,255,0.06) !important; 
        }
        [data-theme="dark"] .table td {
            color: #d0d4de !important;
            border-bottom-color: rgba(255,255,255,0.05) !important;
        }
        
        .item-ranking { display: flex; align-items: center; padding: 8px 14px; border-bottom: 1px solid var(--border-color); transition: all 0.25s ease; gap: 8px; }
        .item-ranking:last-child { border-bottom: none; }
        .item-ranking:hover { background-color: rgba(13, 110, 253, 0.06); }
        
        .table-hover tbody tr { transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease; }
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.04) !important; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative; z-index: 10; }
        
        [data-theme="dark"] .item-ranking:hover { background-color: rgba(77, 142, 255, 0.08); }
        [data-theme="dark"] .table-hover tbody tr:hover { background-color: rgba(77, 142, 255, 0.06) !important; box-shadow: 0 2px 12px rgba(0,0,0,0.3); }
        
        [data-theme="dark"] .fw-bold, [data-theme="dark"] .text-dark { color: var(--text-main) !important; }

        .progress { background-color: var(--border-color) !important; border-radius: 20px; }
        [data-theme="dark"] .progress { background-color: rgba(255,255,255,0.08) !important; }
        [data-theme="dark"] .progress-bar.bg-primary { background: linear-gradient(90deg, #2563eb, #4d8eff) !important; }

        .ranking-scroll { max-height: 520px; overflow-y: auto; padding: 2px 0; }
        .ranking-scroll::-webkit-scrollbar { width: 4px; }
        .ranking-scroll::-webkit-scrollbar-track { background: transparent; }
        .ranking-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
        [data-theme="dark"] .ranking-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); }

        .pos-num { width: 24px; height: 24px; background: var(--bg-body); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); flex-shrink: 0; transition: background 0.3s ease; }
        .pos-num.top { background: var(--primary-blue); color: white; }
        [data-theme="dark"] .pos-num { background: rgba(255,255,255,0.06); color: #7a8299; }
        [data-theme="dark"] .pos-num.top { background: linear-gradient(135deg, #2563eb, #4d8eff); color: #fff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4); }
        .ranking-name { font-size: 0.8rem; font-weight: 600; flex-grow: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        [data-theme="dark"] .ranking-name { color: #d0d4de; }
        .ranking-count { font-size: 0.75rem; font-weight: 700; color: var(--primary-blue); background: rgba(13,110,253,0.08); padding: 2px 8px; border-radius: 20px; flex-shrink: 0; }
        [data-theme="dark"] .ranking-count { background: rgba(77,142,255,0.15); color: #6ea8fe; }

        canvas { width: 100% !important; height: 280px !important; cursor: pointer; }

        .content .badge { min-width: 0 !important; text-transform: none; text-align: right; }

        .ranking-grid { display: grid; grid-template-columns: 280px 1fr; gap: 16px; }
        .ranking-sidebar-col { min-width: 0; }
        .ranking-main-col { min-width: 0; flex-grow: 1; }

        [data-theme="dark"] .page-header h3 { color: #f1f3f5; }
        [data-theme="dark"] .page-header h2 { color: #f1f3f5; }
        [data-theme="dark"] .text-primary { color: #6ea8fe !important; }
        [data-theme="dark"] .badge.bg-primary { background: linear-gradient(135deg, #2563eb, #4d8eff) !important; }

        @media (max-width: 1200px) {
            .ranking-grid { grid-template-columns: 250px 1fr; }
        }
        @media (max-width: 992px) {
            .ranking-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .stat-cards { flex-direction: column; }
            .stat-card { min-width: auto; }
        }
    </style>
</head>
<body class="gerenciador">
    <div class="d-flex">
    <?php include 'menu.php'; ?>
    <div class="content p-4">

    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold header-title-clean">
            <i class="fas fa-chart-line text-primary me-2"></i>Análise dos Chamados
        </h2>
        <div class="nav-mes-pill">
            <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>&ano_anual=<?= $ano_anual ?>" class="btn-seta"><i class="fas fa-chevron-left"></i></a>
            <span class="mes-label"><?= $nome_mes ?> / <?= $ano_selecionado ?></span>
            <a href="?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?>&ano_anual=<?= $ano_anual ?>" class="btn-seta"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <?php
        $total_mes = countTotalMes($conn, $mes_selecionado, $ano_selecionado);
        $total_geral = countTotalGeral($conn);
        
        $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_selecionado, $ano_selecionado);
        $dias_uteis = 0;
        for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
            $dia_semana = date('N', strtotime(sprintf('%04d-%02d-%02d', $ano_selecionado, $mes_selecionado, $dia)));
            if ($dia_semana <= 5) $dias_uteis++;
        }
        $media_diaria = ($total_mes > 0 && $dias_uteis > 0) ? round($total_mes / $dias_uteis, 1) : 0;
    ?>
    <div class="stat-cards mb-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $total_mes ?></span>
                <span class="stat-label">Chamados no Mês</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $media_diaria ?></span>
                <span class="stat-label">Média / Dia</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $total_ranking_count ?></span>
                <span class="stat-label">Atendentes Ativos</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-database"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= number_format($total_geral, 0, ',', '.') ?></span>
                <span class="stat-label">Total Acumulado</span>
            </div>
        </div>
    </div>

    <div class="ranking-grid">

        <div class="ranking-sidebar-col">
            <div class="card-scoreboard" style="height: 100%;">
                <div class="header-blue"><i class="fas fa-trophy me-2"></i>Ranking Geral</div>
                <div class="ranking-scroll">
                    <?php 
                    $p = 1;
                    foreach ($ranking_geral as $row_g): 
                        $topClass = ($p <= 3) ? 'top' : '';
                    ?>
                    <div class="item-ranking">
                        <div class="pos-num <?= $topClass ?>"><?= $p ?></div>
                        <div class="ranking-name"><?= formatarNome($row_g['nome']) ?></div>
                        <div class="ranking-count"><?= $row_g['total'] ?></div>
                    </div>
                    <?php $p++; endforeach; ?>
                </div>
            </div>
        </div>

        <div class="ranking-main-col">
            
            <div class="card-scoreboard">
                <div class="header-blue"><i class="fas fa-clock me-2"></i>Fluxo de Atendimento — 07h às 17h</div>
                <div class="p-3"><canvas id="chartHorarios"></canvas></div>
            </div>

            <div class="card-scoreboard">
                <div class="header-blue"><i class="fas fa-medal me-2"></i>Destaques — <?= $nome_mes ?> / <?= $ano_selecionado ?></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Atendente</th>
                                <th class="text-center" style="width: 70px;">Total</th>
                                <th style="width: 35%;">Desempenho</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($ranking_mensal)) {
                                $max_val = (int)$ranking_mensal[0]['total'];
                            } else {
                                $max_val = 1;
                            }
                            $i = 1;
                            foreach ($ranking_mensal as $row): 
                                $perc = ($row['total'] / $max_val) * 100;
                            ?>
                            <tr>
                                <td class="text-center fw-bold" style="color: var(--primary-blue);"><?= $i ?></td>
                                <td class="fw-bold" style="font-size: 0.85rem;"><?= formatarNome($row['nome']) ?></td>
                                <td class="text-center"><span class="badge bg-primary" style="font-size: 0.8rem;"><?= $row['total'] ?></span></td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $perc ?>%; transition: width 0.6s ease;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php $i++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-scoreboard">
                <div class="header-blue d-flex justify-content-between align-items-center">
                    <div><i class="fas fa-chart-bar me-2"></i>Chamados Resolvidos — Anual</div>
                    <div class="d-flex align-items-center gap-2 bg-black bg-opacity-10 rounded-pill px-2 py-1">
                        <a href="?mes=<?= $mes_selecionado ?>&ano=<?= $ano_selecionado ?>&ano_anual=<?= $ano_anual - 1 ?>" class="btn-seta-header" title="Ano Anterior"><i class="fas fa-chevron-left"></i></a>
                        <span class="fw-bold px-1" style="font-size: 0.85rem;"><?= $ano_anual ?></span>
                        <a href="?mes=<?= $mes_selecionado ?>&ano=<?= $ano_selecionado ?>&ano_anual=<?= $ano_anual + 1 ?>" class="btn-seta-header" title="Próximo Ano"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="p-3"><canvas id="chartMeses"></canvas></div>
            </div>
            
        </div>
    </div>
</div>
</div>
<script>
    function updateChartsTheme(theme) {
        const isDark = theme === 'dark';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
        const tickColor = isDark ? '#8b93a7' : '#666';

        [chartH, chartM].forEach(chart => {
            if (chart) {
                chart.options.scales.x.grid.color = gridColor;
                chart.options.scales.y.grid.color = gridColor;
                chart.options.scales.x.ticks.color = tickColor;
                chart.options.scales.y.ticks.color = tickColor;
                if(isDark) {
                    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(24, 27, 35, 0.95)';
                    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.08)';
                    Chart.defaults.plugins.tooltip.borderWidth = 1;
                    Chart.defaults.plugins.tooltip.titleColor = '#e8eaf0';
                    Chart.defaults.plugins.tooltip.bodyColor = '#e8eaf0';
                } else {
                    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.8)';
                    Chart.defaults.plugins.tooltip.borderColor = 'transparent';
                    Chart.defaults.plugins.tooltip.borderWidth = 0;
                    Chart.defaults.plugins.tooltip.titleColor = '#fff';
                    Chart.defaults.plugins.tooltip.bodyColor = '#fff';
                }
                chart.update();
            }
        });
    }

    function toggleTheme() {
        const doc = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        const currentTheme = doc.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        doc.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        if (newTheme === 'dark') {
            icon.classList.replace('fa-moon', 'fa-sun');
            text.innerText = "Modo Claro";
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
            text.innerText = "Modo Escuro";
        }
        updateChartsTheme(newTheme);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (savedTheme === 'dark') {
            document.getElementById('theme-icon').classList.replace('fa-moon', 'fa-sun');
            document.getElementById('theme-text').innerText = "Modo Claro";
        }
    });

    Chart.defaults.interaction.mode = 'index';
    Chart.defaults.interaction.intersect = false;
    Chart.defaults.plugins.tooltip.backgroundColor = document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(24, 27, 35, 0.95)' : 'rgba(0, 0, 0, 0.8)';
    Chart.defaults.plugins.tooltip.borderColor = document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(255,255,255,0.08)' : 'transparent';
    Chart.defaults.plugins.tooltip.borderWidth = document.documentElement.getAttribute('data-theme') === 'dark' ? 1 : 0;
    Chart.defaults.plugins.tooltip.titleFont = { size: 13, family: "'Segoe UI', sans-serif", weight: 'bold' };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 12, family: "'Segoe UI', sans-serif" };
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = false;

    let chartH, chartM;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
    const tickColor = isDark ? '#8b93a7' : '#666';

    chartH = new Chart(document.getElementById('chartHorarios'), {
        type: 'line',
        data: {
            labels: <?= json_encode($horas_labels) ?>,
            datasets: [{
                label: ' Chamados Atendidos',
                data: <?= json_encode(array_values($horas_dados)) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2.5, 
                tension: 0.4, 
                fill: true, 
                pointRadius: 4,
                pointBackgroundColor: '#0d6efd',
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderColor: '#0d6efd',
                pointHoverBorderWidth: 2.5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { color: tickColor, precision: 0, font: { size: 11 } },
                    grid: { color: gridColor, drawBorder: false }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });

    chartM = new Chart(document.getElementById('chartMeses'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_meses) ?>,
            datasets: [{
                label: ' Total Finalizado',
                data: <?= json_encode($valores_meses) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.8)',
                hoverBackgroundColor: '#0a58ca',
                borderRadius: 4,
                borderSkipped: false,
                maxBarThickness: 40
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { color: tickColor, precision: 0, font: { size: 11 } },
                    grid: { color: gridColor, drawBorder: false }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });

</script>
</body>
</html>