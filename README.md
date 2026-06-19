# Sistema de Gerenciamento de Chamados TI

Sistema web desenvolvido para o **gerenciamento de chamados de TI** e **solicitações de RH**, com painéis analíticos, checklist diário, notificações em tempo real e registro de atividades.

Ideal para órgãos públicos e equipes de suporte que precisam de um controle centralizado, ágil e com rastreabilidade.

---

## ✨ Funcionalidades Principais

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

![Checklist Diário - Exemplo de uso](imgs/checklist-exemplo.png)

---

## 🚀 Melhorias Implementadas (por Luiz Sarmento)

### 1. Otimização do Sistema de Checklist Diário
- **Atualização em tempo real** – as alterações feitas por um usuário são refletidas automaticamente para todos os demais (polling a cada 5 segundos).
- **CRUD completo** – criação, edição e exclusão de tarefas diretamente pela interface.
- **Descrição oculta** – descrição das tarefas aparece apenas ao passar o mouse (melhor experiência visual).
- **Separação de tarefas** – tarefas agendadas para outros dias são exibidas em uma seção separada, com transparência e sem interação.
- **Registro de conclusão** – ao marcar uma tarefa como concluída, o sistema registra **quem** (nome do usuário) e **quando** (horário), com exibição ao lado da tarefa.
- **Indicador visual na sidebar** – o ícone do checklist muda para **verde** quando todas as tarefas do dia estão concluídas, visível em qualquer página do sistema.

### 2. Refatoração e Incremento da Sidebar
- **Centralização do menu** – criação de um arquivo único (`menu.php`) para a sidebar, eliminando duplicação de código em todas as páginas.
- **Badges inteligentes** – notificações visuais com duas cores:
  - **Verde** → chamados/solicitações abertos (prioridade máxima).
  - **Amarelo** → chamados/solicitações em atendimento.
- **Comportamento adaptativo** – na sidebar recolhida, o badge se torna um pequeno ponto discreto sobre o ícone; na sidebar expandida (hover), exibe o número exato.
- **Atualização dinâmica** – os badges são atualizados automaticamente a cada 5 segundos via polling, sem necessidade de recarregar a página.

### 3. Melhoria no Gerenciador de RH
- **Notificações na sidebar** – os badges para o módulo de RH seguem a mesma lógica de cores do módulo de chamados (verde para abertos, amarelo para em atendimento).
- **Atualização em tempo real** – o polling da sidebar também reflete alterações nas solicitações de RH, mantendo todos os usuários sincronizados.
- **Registro de ações** – cada atendimento/fechamento de solicitação fica registrado com o nome do responsável.

### 4. Melhorias Técnicas (Back-end)
- **Migração de MySQLi para PDO** – todas as consultas foram refatoradas para usar PDO, garantindo maior segurança e portabilidade.
- **Adaptação para PostgreSQL** – substituição de funções específicas do MySQL (`MONTH`, `YEAR`, `LIMIT X,Y`) por equivalentes do PostgreSQL (`EXTRACT`, `LIMIT OFFSET`).
- **Uso de prepared statements** – todas as queries foram convertidas para prepared statements, eliminando riscos de injeção SQL.
- **Correção de sintaxe** – ajuste de `fetch_assoc()` para `fetch(PDO::FETCH_ASSOC)` e `num_rows` para `rowCount()`.

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
