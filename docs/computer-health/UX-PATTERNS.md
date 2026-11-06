# Padrões nativos do painel de computadores

Referência: componentes de ativos e relatórios do Snipe-IT desta revisão. O dashboard principal do fork já possui estilos customizados; ele não é a referência para uma nova identidade visual.

| Necessidade | Componente/padrão reutilizado |
|---|---|
| Composição | `x-container`, `x-page-column`, `x-box` (`resources/views/blade`) |
| Contadores | `info-box` AdminLTE, já usado em `reports/eula-signatures.blade.php` |
| Filtros | `x-input.text`, `x-input.select`, `form-group`, `form-control` e Select2 local |
| Listagem | `table table-striped snipe-table` e `partials.bootstrap-table`, como `reports/unaccepted_assets.blade.php` |
| Paginação/colunas/impressão | Bootstrap Table existente, sem implementação paralela |
| Prioridade | `label-*` e barras `progress` / `progress-bar-*` existentes |
| Detalhes e fonte | Modal Bootstrap, `x-tabs`, `x-page-data` e `dl.table-display` |
| Histórico | Tabela Bootstrap Table client-side, com antes/depois por campo e ID original do evento |
| Links internos | `hardwareLinkFormatter`, `usersLinkObjFormatter`, `modelsLinkObjFormatter`, `categoriesLinkObjFormatter`, `locationsLinkObjFormatter` |
| Tema | Tokens e classes existentes no layout; nenhuma paleta, tipografia ou família de controles própria |

## Adaptações necessárias

O endpoint do painel fornece o snapshot autorizado completo, não uma API paginada `total/rows`. A tabela usa o modo client-side e recebe somente o recorte calculado pelo domínio atual. O componente `x-table` genérico não aceita atributos arbitrários/slot e força paginação server-side com `api_url`; por isso é usado o markup nativo dos relatórios, com o mesmo include central.

Busca e filtros são aplicados antes de alimentar a tabela para manter os contadores e o CSV no mesmo recorte. O CSV mantém BOM, separador `;`, proteção de fórmulas e todas as colunas do contrato, através de uma ação no toolbar nativo. As opções do Select2 são derivadas somente dos ativos autorizados.

As duas tabelas usam `cookie:false` e `addrbar:false`: o estado de paginação do histórico não pode sobrescrever o da listagem pela URL. Alterar filtros/ordenação reinicia a primeira página; consultar o histórico preserva a página atual. O adaptador define `showAdvancedSearch:false` além de `advancedSearch:false`, pois o bundle instalado adiciona esse botão sempre que há outro controle no toolbar. A busca única do painel mantém tabela, indicadores e exportação no mesmo recorte.

As abas reutilizam também a navegação por setas/Home/End já aplicada em `contracts/view.blade.php`, com sincronização de `aria-selected` e foco. O modal conserva o fechamento por Escape e devolve o foco ao botão do histórico.

Os formatters nativos esperam texto pré-escapado. O adaptador escapa os valores apenas para renderizar, preservando texto cru para busca e exportação. Identificadores e permissões de navegação são acrescentados ao DTO sem substituir campos existentes. Usuários reportados e históricos não recebem vínculos inferidos por nome/login.

O histórico conserva valores ausentes e valores vazios como conceitos diferentes. Os valores das coordenadas são tratados separadamente em cada lado da mudança; nada é herdado de outro evento.

## Navegação e coordenadas

Marcação/nome do ativo e os demais vínculos internos seguem a navegação normal da plataforma, na mesma aba. O histórico adicional usa o modal Bootstrap; o cadastro continua acessível pelo link nativo.

Coordenadas só viram link quando contêm um par completo `latitude, longitude` em graus decimais com ponto, latitude entre -90 e 90 e longitude entre -180 e 180. Ausência, texto adicional, JSON, URLs e valores inválidos continuam texto. `0,0` é válido.

O link externo usa [Google Maps URLs — Search](https://developers.google.com/maps/documentation/urls/get-started#search): `https://www.google.com/maps/search/?api=1&query=` com o par codificado. Ele abre somente por ação do usuário. Não há mapa incorporado, geolocalização, API key nem consulta externa durante a carga.

## Validação

Validar seleção/filtros/CSV, paginação nativa, escape de textos/formatters, links e políticas, modal/abas/Escape, coordenadas válidas/inválidas, tema e viewport móvel. Os testes de banco usam exclusivamente o banco descartável. Nenhuma migration ou mudança de schema é necessária; a réplica de trabalho não é recriada.
