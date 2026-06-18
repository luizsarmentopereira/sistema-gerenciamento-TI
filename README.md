# Sistema de Gerenciamento de Chamados TI

Sistema web desenvolvido para o **gerenciamento de chamados de TI** e **solicitações de RH**, com painéis analíticos, checklist diário, notificações em tempo real e registro de atividades.

Ideal para órgãos públicos e equipes de suporte que precisam de um controle centralizado, ágil e com rastreabilidade.

---

## ✨ Funcionalidades

### Módulo TI
- Abertura de chamados com descrição, setor e anexo.
- Fluxo de atendimento: **Aberto → Em Atendimento → Fechado**.
- Chat integrado para cada chamado (histórico de interações).
- Filtros por setor e status.
- Ranking de atendentes (mensal e geral).
- Gráficos de fluxo de atendimento (por hora e por mês).
- Notificações em tempo real de novos chamados e mensagens (polling a cada 10s).

### Módulo RH
- Solicitações de **inclusão** e **exclusão** de colaboradores.
- Status: Novo, Aberto, Em Atendimento, Fechado.
- Filtros por setor e status.
- Registro de quem atendeu e quem fechou a solicitação.

### Checklist Diário (DTI)
- Tarefas com frequência: **diária**, **semanal** (dia da semana) e **mensal** (dia do mês).
- Marcação de conclusão com registro de **quem concluiu** e **horário**.
- Visualização de tarefas agendadas para outros dias.
- Progresso visual com barra de percentual.
- Sincronização automática entre múltiplos usuários (polling a cada 5s).

### Interface & Experiência
- **Sidebar retrátil** com hover (expansão suave).
- **Badges inteligentes**:
  - **Verde** → chamados/solicitações abertos (prioridade).
  - **Amarelo** → chamados/solicitações em atendimento.
- **Ícone do checklist** fica verde quando todas as tarefas do dia estão concluídas.
- **Modo claro/escuro** (persistente via localStorage).
- Layout responsivo (Bootstrap 5 + CSS customizado).

### Atualizações em Tempo Real (Polling)
- A sidebar consulta `get_notificacoes.php` a cada **5 segundos** e atualiza automaticamente:
  - Números e cores dos badges.
  - Status do ícone do checklist.
- O checklist sincroniza entre abas/usuários a cada **5 segundos** (sem recarregar a página).
- O gerenciador de chamados detecta novos chamados e mensagens a cada **10 segundos** e exibe notificações push (com som).

---

## 🛠️ Tecnologias

- **Back-end**: PHP 7.4+ (com PDO)
- **Banco de Dados**: PostgreSQL 14+
- **Front-end**: Bootstrap 5, Font Awesome 6, Chart.js
- **JavaScript**: Vanilla JS, SortableJS (para arrastar tarefas)
- **Servidor**: PHP embutido (desenvolvimento) ou Apache/Nginx (produção)

---

## ⚙️ Pré-requisitos

- PHP 7.4 ou superior (com extensões: `pdo_pgsql`, `pgsql`, `json`, `session`).
- PostgreSQL 14 ou superior.
- Composer (opcional, para gerenciar dependências, mas o projeto não usa bibliotecas externas via Composer).

---

## 📥 Instalação

### 1. Clone o repositório
```bash
git clone https://github.com/luizsarmontopereira/sistema-gerenciamento-TI.git
cd sistema-gerenciamento-TI
