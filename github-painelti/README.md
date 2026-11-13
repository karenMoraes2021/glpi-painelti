# Painel TI para GLPI

Painel de indicadores da TI dentro do GLPI 11: chamados, SLA, mesas de atendimento, inventário e antivírus em cartões coloridos e **clicáveis**. Cada cartão abre a lista do próprio GLPI (Chamados ou Computadores) já filtrada.

O painel fica em **Assistência › Painel TI** e também como **aba na página inicial** (Home), que pode abrir por padrão quando o técnico entra no GLPI.


<img width="1913" height="924" alt="PainelTI-Previa" src="https://github.com/user-attachments/assets/30597a46-cb37-428d-9240-a0eea5a919b4" />


## O que mostra

| Seção | Cartões |
|---|---|
| **Chamados** | A fazer (na mesa, sem técnico) · Em atendimento · Pendentes · Abertos · Meus · Solucionados no mês |
| **SLA** | Estourados · Prestes a estourar (próximas X horas) · Sem técnico com prazo de atendimento vencido · Sem categoria · Sem mesa · Satisfação no mês |
| **Por mesa** | Tabela com A fazer / Em atendimento / Pendentes / Estourados de cada grupo que recebe chamados |
| **Inventário** | Computadores inventariados · Agentes comunicando nas últimas 24h · Sem comunicação há mais de X dias |
| **Antivírus** | Instalado · Na versão mais nova · Desatualizados · Desativados · Sem antivírus · Gráfico das versões instaladas |

Recursos:

- **Cartões clicáveis**: abrem a lista nativa do GLPI com os mesmos itens do cartão.
- **Filtro por mesa** (grupo de atendimento).
- **Respeita as permissões**:
  - quem tem *Ver todos os chamados* vê todas as mesas;
  - quem tem só *Ver chamados do grupo* vê as próprias mesas e os chamados atribuídos a ele;
  - o perfil Self-Service não vê o painel.
- **Respeita as entidades** ativas do usuário.
- **Tema claro e escuro**, **tela cheia** (bom para TV da TI) e botão **atualizar**. Recarrega sozinho a cada 5 minutos.
- **Aba na Home**, opcionalmente em primeiro lugar e aberta ao entrar.
- **Não cria tabelas**: só lê os dados do GLPI e guarda 4 configurações em `glpi_configs`.

> "Mesa" é o nome usado no painel para os **grupos que recebem chamados**: grupos marcados como *pode ser atribuído* e usados como grupo responsável em pelo menos uma categoria.

## Requisitos

- GLPI **11.0.x**
- PHP compatível com o seu GLPI
- Para os cartões de inventário e antivírus: computadores inventariados pelo **GLPI Agent** (inventário nativo)

## Instalação

1. Baixe o `.zip` da última versão em **Releases**.
2. Extraia dentro de `plugins/` do GLPI. A pasta tem que se chamar **`painelti`**:
   ```bash
   cd /var/www/glpi/plugins
   unzip /caminho/painelti-1.0.0.zip
   chown -R www-data:www-data painelti
   ```
   Para instalar pelo Git, clone já com o nome certo:
   ```bash
   git clone https://github.com/karenMoraes2021/glpi-painelti.git /var/www/glpi/plugins/painelti
   ```
3. No GLPI, abra **Configurar › Plugins** e clique em **Instalar** e depois em **Ativar** no *Painel TI*.
4. Clique no nome do plugin (ou no ícone de configuração) para ajustar as opções.

## Configuração

Em **Configurar › Plugins › Painel TI**:

| Opção | Padrão | Para que serve |
|---|---|---|
| Antivírus padrão (parte do nome) | vazio | Nome como aparece na aba *Antivírus* do computador (ex.: `Defender`, `Kaspersky`). Vazio = qualquer antivírus |
| "Prestes a estourar" (horas) | 4 | Chamados com prazo de solução nas próximas X horas |
| "Sem comunicação" (dias) | 7 | Agentes sem contato há mais de X dias |
| Abrir a Home na aba Painel TI | ligado | Coloca a aba em primeiro lugar e abre nela ao entrar. Depois vale a última aba que a pessoa escolher |

## Quem vê o painel

O acesso segue a permissão de **Chamados** do perfil (Administração › Perfis › aba Assistência):

| Permissão no perfil | O que vê |
|---|---|
| Ver todos os chamados | Todas as mesas |
| Ver chamados do grupo | As mesas de que é membro e os chamados atribuídos a ele |
| Nenhuma das duas | Não vê o painel |

## Perguntas frequentes

**A aba não apareceu em primeiro lugar.** Saia e entre de novo no GLPI (a aba padrão é definida no login) e recarregue com Ctrl+F5.

**O cartão mostra "—".** A consulta deu erro. Veja `files/_log/php-errors.log` e abra uma issue com a mensagem.

**Os números do cartão e da lista diferem um pouco.** Até 80 itens, a lista filtra exatamente os itens do cartão. Acima disso, usa os filtros equivalentes do GLPI, que podem diferir em poucos itens.

## Contribuindo

Issues e pull requests são bem-vindos. Ao relatar um problema, informe a versão do GLPI, a do plugin e a mensagem do `php-errors.log`, se houver.

## Licença

[GPLv3 ou posterior](LICENSE).
