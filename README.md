# Troca de peçasK

Aplicação PHP procedural compatível com PHP 5.4 e SQLSRV. Estrutura, autenticação e aparência baseadas em AtualizaEquip e checkincheckout. Entrada: `views/index.php` (ou `index.php` na raiz).

## Etapas implementadas

1. Login conforme AtualizaEquip: usuário da TB01066 com FINANCEIRO = 1; técnico da sessão.
2. Consulta por série. Reutiliza a OS aberta no status 9K; se não existir, abre a partir da TB02054 com saldo disponível. Série com mais de um produto/empresa exige correção no ERP. Mantém o equipamento original na TB02115.
3. Abertura, histórico TB02130 e contador TB00002 são gravados na mesma transação. O modal da nova OS permanece até clicar em Fechar, inclusive ao recarregar a página na mesma sessão.
4. Pesquisa paginada por código, referência ou nome. Produtos ativos dos tipos 9 e 11; cadastro novo ativo do tipo 9, com referência de até 20 caracteres e nome de até 60.
5. Seleção de peças ativas fora dos tipos 9 e 11. E = peças novas; S = peças retiradas. Cada inclusão gera uma linha independente. ID identifica exatamente a ocorrência a editar ou excluir.
6. Quantidade positiva e inteira. Custo total calculado no servidor como QTD × TB01010_CUSTO e convertido para NUMERIC(18,0), conforme solicitado. O unitário exibido é o custo atual do cadastro, com quatro casas; o total é o valor efetivamente gravado. Editar quantidade recalcula com o custo atual.
7. O botão Conferir produto final aparece quando existe ao menos uma peça. Apenas informa que a próxima etapa ainda será implementada; não fecha OS nem movimenta estoque.

## Persistência e concorrência

O produto selecionado fica na sessão até a primeira peça. Depois é recuperado pelo PRODUTO_PAI das linhas da OS. Ao encerrar a sessão antes de incluir peças, será necessário selecionar o produto novamente. Uma OS com peças de outro produto pai não pode ter o pai trocado; é necessário remover essas peças antes.

Consultas parametrizadas, validação no servidor e token de sessão nas gravações. A abertura protege a consulta de OS existente e o contador com UPDLOCK/HOLDLOCK. Alterações das peças bloqueiam a OS durante a transação e conferem ID, OS, produto pai e tipo.

## Banco e instalação

Configuração em `config/config.php`, usando os valores existentes de AtualizaEquip, sem exibir credenciais. A tabela TB_TROCA_PECAS já existe no banco consultado. A coluna ID INT IDENTITY com chave primária foi aplicada com autorização do usuário. O script idempotente está em `scripts/TB_TROCA_PECAS.sql`. Não há criação automática de tabela ao abrir uma tela.

## Validação

`C:\php\php.exe -n tests/fluxo.php` testa a lógica com funções SQLSRV simuladas; não grava no banco. Conferidos também a sintaxe PHP 5.4, a sintaxe JavaScript e os filtros de pesquisa contra o SQL Server em modo de leitura. Os testes isolados não substituem homologação dos gatilhos do ERP.