<?php
// menu.php - Sidebar unificada com badges de notificação (hover)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['ID'])) {
    $_SESSION['ID'] = 1;
    $_SESSION['NOME'] = 'Admin';
    $_SESSION['PERFIL'] = 'MASTER';
    $_SESSION['DEPARTAMENTO'] = 'T.I.';
}

if (!isset($conn)) {
    include_once('conexao.php');
}

function totalNotificacoesChamados($conn) {
    $sql = "SELECT COUNT(*) as total FROM chamado WHERE status IN ('ABERTO', 'NOVO')";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }
    return 0;
}

function totalNotificacoesRh($conn) {
    $sql = "SELECT COUNT(*) as total FROM (
                SELECT status FROM inclusao_colaborador WHERE status IN ('ABERTO', 'NOVO')
                UNION ALL
                SELECT status FROM exclusao_colaborador WHERE status IN ('ABERTO', 'NOVO')
            ) as notificacoes";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }
    return 0;
}

$total_notificacoes_chamados = totalNotificacoesChamados($conn);
$total_notificacoes_rh      = totalNotificacoesRh($conn);
$sidebar_active_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-profile">
        <img src="./imgs/icone.png" class="logo-sidebar" alt="Logo">
        <div class="sidebar-user-info">
            <span class="sidebar-user-name" title="<?php echo htmlspecialchars($_SESSION['NOME'] ?? 'Admin'); ?>"><?php echo htmlspecialchars($_SESSION['NOME'] ?? 'Admin'); ?></span>
            <span class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['PERFIL'] ?? 'MASTER'); ?></span>
        </div>
    </div>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section-label">PRINCIPAL</div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item">
            <a href="gerenciar.php" class="nav-link <?= ($sidebar_active_page == 'gerenciar.php') ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i><span>Gerenciar Chamados</span>
                <?php if ($total_notificacoes_chamados > 0): ?>
                    <span class="badge-notif"><?= $total_notificacoes_chamados ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="ranking.php" class="nav-link <?= ($sidebar_active_page == 'ranking.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i><span>Análise de Chamados</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="dailychecklist.php" class="nav-link <?= strpos($sidebar_active_page, 'dailychecklist') !== false ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i><span>Checklist</span>
            </a>
        </li>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section-label">ADMINISTRAÇÃO</div>
        <li class="nav-item">
            <a href="gerenciar_transferencias.php" class="nav-link <?= ($sidebar_active_page == 'gerenciar_transferencias.php') ? 'active' : '' ?>">
                <i class="fas fa-exchange-alt"></i><span>Gerenciar Transf.</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="gerenciar_rh.php" class="nav-link <?= ($sidebar_active_page == 'gerenciar_rh.php') ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i><span>Gerenciar RH</span>
                <?php if ($total_notificacoes_rh > 0): ?>
                    <span class="badge-notif"><?= $total_notificacoes_rh ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php if(isset($_SESSION['PERFIL']) && ($_SESSION['PERFIL'] == "ADMINISTRADOR" || $_SESSION['PERFIL'] == "MASTER")): ?>
        <li class="nav-item">
            <a href="cadastrar.php" class="nav-link <?= ($sidebar_active_page == 'cadastrar.php') ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i><span>Cadastrar Usuário</span>
            </a>
        </li>
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
                <span class="footer-text"> Desenvolvido pela Equipe</span>
            </p>
        </div>
    </div>
</div>

<style>
.sidebar .nav-link {
    position: relative;
    display: flex;
    align-items: center;
}
.sidebar .nav-link span:not(.badge-notif) {
    flex: 1;
    white-space: nowrap;
}
.badge-notif {
    position: absolute !important;
    top: 2px !important;
    right: 8px !important;
    left: auto !important;
    width: 9px !important;
    height: 9px !important;
    min-width: 9px !important;
    min-height: 9px !important;
    background: #dc3545 !important;
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
}
.sidebar:hover .badge-notif {
    position: relative !important;
    top: auto !important;
    right: auto !important;
    width: 22px !important;
    height: 22px !important;
    min-width: 22px !important;
    min-height: 22px !important;
    margin-left: 8px !important;
    font-size: 11px !important;
    color: #fff !important;
    background: #dc3545 !important;
    border-radius: 50% !important;
    box-shadow: none !important;
}
</style>

<script>
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