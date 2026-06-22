<?php
// menu.php - Sidebar com badges e atualização em tempo real (polling)
if (!isset($conn)) {
    include_once('conexao.php');
}

// ===== FUNÇÕES DE CONTAGEM (para carregamento inicial) =====
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

// ===== CÁLCULO DOS BADGES (carregamento inicial) =====
$total_abertos_chamados = totalAbertosChamados($conn);
$total_em_atendimento_chamados = totalEmAtendimentoChamados($conn);

$badge_chamados_num = 0;
$badge_chamados_class = 'badge-notif';

if ($total_abertos_chamados > 0) {
    $badge_chamados_num = $total_abertos_chamados;
    $badge_chamados_class = 'badge-notif';
} elseif ($total_em_atendimento_chamados > 0) {
    $badge_chamados_num = $total_em_atendimento_chamados;
    $badge_chamados_class = 'badge-notif badge-notif-yellow';
}

$total_abertos_rh = totalAbertosRh($conn);
$total_em_atendimento_rh = totalEmAtendimentoRh($conn);

$badge_rh_num = 0;
$badge_rh_class = 'badge-notif';

if ($total_abertos_rh > 0) {
    $badge_rh_num = $total_abertos_rh;
    $badge_rh_class = 'badge-notif';
} elseif ($total_em_atendimento_rh > 0) {
    $badge_rh_num = $total_em_atendimento_rh;
    $badge_rh_class = 'badge-notif badge-notif-yellow';
}

$checklist_done = todasTarefasConcluidasHoje($conn);
$checklist_class = $checklist_done ? 'checklist-done' : '';

$sidebar_active_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-profile">
        <!-- NOVA LOGO (código de barras) -->
        <img src="./imgs/logo.png" class="logo-sidebar" alt="Logo">
        <div class="sidebar-user-info">
            <span class="sidebar-user-name" title="<?php echo htmlspecialchars($_SESSION['NOME'] ?? ''); ?>"><?php echo htmlspecialchars($_SESSION['NOME'] ?? ''); ?></span>
            <span class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['PERFIL'] ?? ''); ?></span>
        </div>
    </div>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">PRINCIPAL</div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item">
            <a href="gerenciar.php" class="nav-link <?= ($sidebar_active_page == 'gerenciar.php') ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i><span>Gerenciar Chamados</span>
                <?php if ($badge_chamados_num > 0): ?>
                    <span class="<?= $badge_chamados_class ?>" id="badge-chamados"><?= $badge_chamados_num ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="ranking.php" class="nav-link <?= ($sidebar_active_page == 'ranking.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i><span>Análise de Chamados</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="dailychecklist.php" class="nav-link <?= strpos($sidebar_active_page, 'dailychecklist') !== false ? 'active' : '' ?> <?= $checklist_class ?>" id="link-checklist">
                <i class="fas fa-clipboard-check"></i><span>Checklist</span>
            </a>
        </li>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section-label">ADMINISTRAÇÃO</div>
        <li class="nav-item">
            <a href="gerenciar_rh.php" class="nav-link <?= ($sidebar_active_page == 'gerenciar_rh.php') ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i><span>Gerenciar RH</span>
                <?php if ($badge_rh_num > 0): ?>
                    <span class="<?= $badge_rh_class ?>" id="badge-rh"><?= $badge_rh_num ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php if(isset($_SESSION['PERFIL']) && ($_SESSION['PERFIL'] == "ADMINISTRADOR" || $_SESSION['PERFIL'] == "MASTER")): ?>
        <?php endif; ?>
    </ul>
    <div class="sidebar-bottom">
        <ul class="nav flex-column mb-0">
            <li class="nav-item">
                <a href="logout.php" class="nav-link sidebar-logout">
                    <i class="fas fa-sign-out-alt"></i><span>Sair</span>
                </a>
            </li>
        </ul>
        <div class="theme-switch" onclick="toggleTheme()">
            <i class="fas fa-moon" id="theme-icon"></i>
            <span id="theme-text">Modo Escuro</span>
        </div>
        <div class="sidebar-footer">
            <p class="small text-center">
                <i class="fas fa-info-circle"></i>
                <span class="footer-text"> Desenvolvido pela Equipe DTI</span>
            </p>
        </div>
    </div>
</div>

<style>
/* ==========================================
   BADGE DE NOTIFICAÇÃO
   ========================================== */
.sidebar .nav-link {
    position: relative;
    display: flex;
    align-items: center;
}
.sidebar .nav-link span:not(.badge-notif) {
    flex: 1;
    white-space: nowrap;
}

/* Estado recolhido (padrão) */
.badge-notif {
    position: absolute !important;
    top: 2px !important;
    right: 8px !important;
    left: auto !important;
    width: 9px !important;
    height: 9px !important;
    min-width: 9px !important;
    min-height: 9px !important;
    background: #28a745 !important;
    border-radius: 50% !important;
    font-size: 0 !important;
    color: transparent !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.2);
    z-index: 5;
    line-height: 1 !important;
    overflow: hidden !important;
}
.badge-notif-yellow {
    background: #ffc107 !important;
    color: #212529 !important;
}

/* Estado expandido (hover) */
.sidebar:hover .badge-notif {
    position: relative !important;
    top: auto !important;
    right: auto !important;
    left: auto !important;
    width: 19px !important;
    height: 19px !important;
    min-width: 19px !important;
    min-height: 19px !important;
    margin-left: 8px !important;
    font-size: 8px !important;
    color: #fff !important;
    border-radius: 50% !important;
    box-shadow: none !important;
    overflow: visible !important;
    background: #28a745 !important;
}
.sidebar:hover .badge-notif-yellow {
    background: #ffc107 !important;
    color: #212529 !important;
}

/* ==========================================
   ÍCONE DO CHECKLIST - VERDE VIVO
   ========================================== */
.sidebar .nav-link i.fa-clipboard-check {
    color: rgba(255, 255, 255, 0.8);
    transition: color 0.3s ease;
}
.sidebar .nav-link.checklist-done i.fa-clipboard-check {
    color: #2ECC71 !important;
}
.sidebar:hover .nav-link.checklist-done i.fa-clipboard-check {
    color: #2ECC71 !important;
}
.sidebar .nav-link.checklist-done.active i.fa-clipboard-check {
    color: #2ECC71 !important;
}

/* ==========================================
   INVERSÃO DA LOGO NO MODO ESCURO
   ========================================== */
[data-theme="dark"] .logo-sidebar {
    filter: brightness(0) invert(1) !important;
}
</style>

<script>
// ==========================================
// ATUALIZAÇÃO EM TEMPO REAL (POLLING)
// ==========================================
function atualizarSidebar() {
    fetch('get_notificacoes.php')
        .then(response => {
            if (!response.ok) throw new Error('Erro na requisição');
            return response.json();
        })
        .then(data => {
            // Atualiza badge de Chamados
            const badgeChamados = document.getElementById('badge-chamados');
            if (badgeChamados) {
                const total = data.chamados.abertos + data.chamados.em_atendimento;
                if (total > 0) {
                    badgeChamados.textContent = data.chamados.abertos > 0 ? data.chamados.abertos : data.chamados.em_atendimento;
                    if (data.chamados.abertos > 0) {
                        badgeChamados.className = 'badge-notif';
                    } else {
                        badgeChamados.className = 'badge-notif badge-notif-yellow';
                    }
                } else {
                    badgeChamados.parentElement.removeChild(badgeChamados);
                }
            } else {
                // Se não existir badge e houver notificações, recarregar a página para criar o elemento
                if (data.chamados.abertos > 0 || data.chamados.em_atendimento > 0) {
                    location.reload();
                }
            }

            // Atualiza badge de RH
            const badgeRh = document.getElementById('badge-rh');
            if (badgeRh) {
                const total = data.rh.abertos + data.rh.em_atendimento;
                if (total > 0) {
                    badgeRh.textContent = data.rh.abertos > 0 ? data.rh.abertos : data.rh.em_atendimento;
                    if (data.rh.abertos > 0) {
                        badgeRh.className = 'badge-notif';
                    } else {
                        badgeRh.className = 'badge-notif badge-notif-yellow';
                    }
                } else {
                    badgeRh.parentElement.removeChild(badgeRh);
                }
            } else {
                if (data.rh.abertos > 0 || data.rh.em_atendimento > 0) {
                    location.reload();
                }
            }

            // Atualiza classe do Checklist
            const linkChecklist = document.getElementById('link-checklist');
            if (linkChecklist) {
                if (data.checklist_done) {
                    linkChecklist.classList.add('checklist-done');
                } else {
                    linkChecklist.classList.remove('checklist-done');
                }
            }
        })
        .catch(error => {
            console.warn('Erro ao atualizar sidebar:', error);
        });
}

// Inicia o polling a cada 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    atualizarSidebar();
    setInterval(atualizarSidebar, 5000);
});

// Função toggleTheme (mantida)
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

document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    if (savedTheme === 'dark') {
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        if (icon) icon.classList.replace('fa-moon', 'fa-sun');
        if (text) text.innerText = 'Modo Claro';
    }
});
</script>