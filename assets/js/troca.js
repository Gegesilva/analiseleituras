// Interacoes diretas com os models, seguindo os paineis de Checkin/Checkout.
(function () {
    'use strict';
    var tela = document.querySelector('.troca-shell');
    if (!tela) return;
    var os = tela.getAttribute('data-os'), token = tela.getAttribute('data-token');
    var ultimoFoco = null;
    // Atualiza o texto de um elemento com seguranca.
    function texto(el, valor) { el.textContent = valor == null ? '' : String(valor); }
    // Exibe uma mensagem de sucesso ou erro.
    function mensagem(el, valor, erro) { texto(el, valor); el.className = 'mensagem' + (erro ? ' erro' : ''); }
    // Envia uma requisicao assincrona e chama o retorno de sucesso ou falha.
    function requisicao(url, dados, pronto, falha) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.timeout = 30000;
        if (dados) xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            var retorno;
            try { retorno = JSON.parse(xhr.responseText); } catch (e) { falha('A sessão expirou ou a resposta não pôde ser lida. Atualize a página.'); return; }
            if (xhr.status < 200 || xhr.status >= 300 || !retorno.sucesso) { falha(retorno.mensagem || 'Não foi possível concluir a solicitação.'); return; }
            pronto(retorno);
        };
        xhr.onerror = function () { falha('Falha na conexão. Tente novamente.'); };
        xhr.ontimeout = function () { falha('A resposta demorou. Atualize a tela para conferir se a gravação foi concluída antes de tentar novamente.'); };
        xhr.send(dados || null);
        return xhr;
    }
    // Codifica um objeto como parametros de formulario.
    function codificar(dados) {
        var partes = [];
        for (var chave in dados) if (Object.prototype.hasOwnProperty.call(dados, chave)) partes.push(encodeURIComponent(chave) + '=' + encodeURIComponent(dados[chave]));
        return partes.join('&');
    }
    // Envia uma gravacao para o endpoint PHP.
    function enviar(dados, pronto, falha) { requisicao('../models/salvarTroca.php', codificar(dados), pronto, falha); }
    // Abre um modal e move o foco para o primeiro controle.
    function abrirModal(id) {
        var modal = document.getElementById(id); ultimoFoco = document.activeElement;
        modal.hidden = false; modal.classList.add('is-open'); document.body.classList.add('modal-aberto');
        var aviso = modal.querySelector('.mensagem'); if (aviso) mensagem(aviso, '', false);
        var foco = modal.querySelector('input:not([type="hidden"]), button'); if (foco) foco.focus();
    }
    // Fecha um modal e restaura o foco anterior.
    function fecharModal(modal) {
        modal.classList.remove('is-open'); modal.hidden = true; document.body.classList.remove('modal-aberto');
        if (ultimoFoco) ultimoFoco.focus();
    }
    var fechar = document.querySelectorAll('.fechar-modal');
    for (var i = 0; i < fechar.length; i++) fechar[i].onclick = function () { fecharModal(this.closest('.modal-backdrop-custom')); };
    document.addEventListener('keydown', function (e) {
        var modal = document.querySelector('.modal-backdrop-custom.is-open'); if (!modal) return;
        // Nenhum modal desaparece por clique externo ou Escape; o usuario escolhe fechar/cancelar.
        if (e.key === 'Tab') {
            var focos = modal.querySelectorAll('input:not([type="hidden"]):not([disabled]), button:not([disabled]), a[href]');
            if (!focos.length) return;
            var primeiro = focos[0], ultimo = focos[focos.length - 1];
            if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
        }
    });
    // Liga o envio do formulario ao fluxo de gravacao assincrono.
    function ligarFormulario(id) {
        var form = document.getElementById(id); if (!form) return;
        form.onsubmit = function (e) {
            e.preventDefault(); if (form.getAttribute('data-enviando') === '1') return;
            var dados = {}, elementos = form.elements;
            for (var j = 0; j < elementos.length; j++) if (elementos[j].name) dados[elementos[j].name] = elementos[j].value;
            var botoes = form.querySelectorAll('button'), aviso = form.querySelector('.mensagem') || document.getElementById('mensagem');
            form.setAttribute('data-enviando', '1');
            for (var j = 0; j < botoes.length; j++) botoes[j].disabled = true;
            mensagem(aviso, 'Salvando...', false);
            enviar(dados, function (retorno) { window.location.href = retorno.destino; }, function (erro) {
                form.removeAttribute('data-enviando'); for (var j = 0; j < botoes.length; j++) botoes[j].disabled = false; mensagem(aviso, erro, true);
            });
        };
    }
    ['formSerie', 'formProduto', 'formPeca', 'formExcluir', 'formLimparProduto'].forEach(ligarFormulario);
    var nova = document.getElementById('modalNovaOs');
    if (nova) {
        document.body.classList.add('modal-aberto');
        document.getElementById('fecharNovaOs').focus();
        document.getElementById('fecharNovaOs').onclick = function () {
            var botao = this; botao.disabled = true;
            enviar({ acao: 'fechar_aviso', os: os, token: token }, function () { fecharModal(nova); }, function (erro) { botao.disabled = false; mensagem(nova.querySelector('.mensagem'), erro, true); });
        };
    }
    // Formata valores numericos no padrao brasileiro.
    function dinheiro(valor, casas) { return Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }); }
    // Prepara o modal de pecas para inclusao ou edicao da quantidade.
    function abrirPeca(produto, tipo, id, quantidade) {
        var form = document.getElementById('formPeca');
        form.elements.acao.value = id ? 'editar_peca' : 'incluir_peca';
        form.elements.id.value = id || ''; form.elements.codigo.value = produto.codigo; form.elements.tipo.value = tipo;
        form.elements.quantidade.value = quantidade || '1'; form.setAttribute('data-custo', produto.custo || '0');
        texto(document.getElementById('tituloPeca'), (id ? 'Editar quantidade · ' : 'Incluir · ') + (tipo === 'E' ? 'Peças novas' : 'Peças retiradas'));
        texto(document.getElementById('nomePeca'), produto.codigo + ' · ' + produto.nome);
        atualizarCusto(); abrirModal('modalPeca');
    }
    // Recalcula o total estimado usando custo unitario e quantidade.
    function atualizarCusto() {
        var form = document.getElementById('formPeca'); if (!form) return;
        var custo = Number(form.getAttribute('data-custo') || 0), qtd = Number(form.elements.quantidade.value || 0);
        texto(document.getElementById('resumoCusto'), 'Custo unitário: ' + dinheiro(custo, 4) + ' · Total estimado: ' + dinheiro(Math.round(custo * qtd), 2));
    }
    var quantidade = document.getElementById('quantidade'); if (quantidade) quantidade.oninput = atualizarCusto;
    // Inicializa pesquisa de produtos, paginacao e selecao com atraso controlado.
    function prepararBusca(bloco) {
        var form = bloco.querySelector('form'), input = bloco.querySelector('.termo-busca'), resultados = bloco.querySelector('.resultados');
        var paginas = bloco.querySelector('.paginacao'), criar = bloco.querySelector('.criar-produto'), tipo = bloco.getAttribute('data-tipo');
        var pagina = 1, timer = null, versao = 0, ultimaBusca = null;
        // Cria um botao de paginacao para a pesquisa atual.
        function botaoPagina(rotulo, novaPagina) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn-secundario'; texto(b, rotulo); b.onclick = function () { buscar(novaPagina); }; paginas.appendChild(b); }
        // Consulta o servidor e exibe a pagina atual de produtos.
        function buscar(numero) {
            pagina = numero; var atual = ++versao; if (ultimaBusca) ultimaBusca.abort();
            resultados.innerHTML = ''; paginas.innerHTML = ''; if (criar) criar.hidden = true;
            texto(resultados, 'Pesquisando...');
            ultimaBusca = requisicao('../models/pesquisarProdutos.php', codificar({ os: os, tipo: tipo, termo: input.value, pagina: pagina, token: token }), function (retorno) {
                if (atual !== versao) return; resultados.innerHTML = '';
                retorno.produtos.forEach(function (produto) {
                    var card = document.createElement('article'); card.className = 'produto-item';
                    var nome = document.createElement('h3'); texto(nome, produto.nome); card.appendChild(nome);
                    var codigo = document.createElement('p'); codigo.className = 'produto-meta'; texto(codigo, 'Código: ' + produto.codigo); card.appendChild(codigo);
                    var ref = document.createElement('p'); ref.className = 'produto-meta'; texto(ref, 'Referência: ' + (produto.referencia || '—')); card.appendChild(ref);
                    if (tipo !== 'produto') { var custo = document.createElement('p'); custo.className = 'produto-meta'; texto(custo, 'Custo unitário: ' + dinheiro(produto.custo, 4)); card.appendChild(custo); }
                    var botao = document.createElement('button'); botao.type = 'button'; botao.className = 'btn-acao'; texto(botao, 'Selecionar');
                    botao.onclick = function () {
                        if (tipo !== 'produto') { abrirPeca(produto, tipo); return; }
                        botao.disabled = true;
                        enviar({ acao: 'selecionar_produto', os: os, token: token, codigo: produto.codigo }, function (r) { window.location.href = r.destino; }, function (erro) { botao.disabled = false; mensagem(document.getElementById('mensagem'), erro, true); });
                    };
                    card.appendChild(botao); resultados.appendChild(card);
                });
                if (!retorno.produtos.length) texto(resultados, 'Nenhum produto encontrado.');
                if (criar) criar.hidden = retorno.produtos.length > 0 || pagina !== 1;
                if (pagina > 1) botaoPagina('← Anterior', pagina - 1);
                var indicador = document.createElement('span'); texto(indicador, 'Página ' + pagina); paginas.appendChild(indicador);
                if (retorno.mais) botaoPagina('Próxima →', pagina + 1);
            }, function (erro) { if (atual === versao) texto(resultados, erro); });
        }
        form.onsubmit = function (e) { e.preventDefault(); clearTimeout(timer); buscar(1); };
        input.oninput = function () { clearTimeout(timer); ++versao; if (ultimaBusca) ultimaBusca.abort(); timer = setTimeout(function () { buscar(1); }, 300); };
        if (criar) criar.onclick = function () { abrirModal('modalProduto'); };
        texto(resultados, 'Digite um código, referência ou nome para pesquisar.');
    }
    var buscas = document.querySelectorAll('.busca-produtos'); for (var i = 0; i < buscas.length; i++) prepararBusca(buscas[i]);
    var editar = document.querySelectorAll('.editar-peca');
    for (var i = 0; i < editar.length; i++) editar[i].onclick = function () { abrirPeca({ codigo: this.getAttribute('data-codigo'), nome: this.getAttribute('data-nome'), custo: this.getAttribute('data-custo') }, this.getAttribute('data-tipo'), this.getAttribute('data-id'), this.getAttribute('data-qtd')); };
    var excluir = document.querySelectorAll('.excluir-peca');
    for (var i = 0; i < excluir.length; i++) excluir[i].onclick = function () {
        var form = document.getElementById('formExcluir'); form.elements.id.value = this.getAttribute('data-id'); form.elements.tipo.value = this.getAttribute('data-tipo');
        texto(document.getElementById('nomeExcluir'), 'Excluir esta ocorrência de ' + this.getAttribute('data-nome') + '?'); abrirModal('modalExcluir');
    };
    var conferir = document.getElementById('conferirProduto'); if (conferir) conferir.onclick = function () { abrirModal('modalConferencia'); };
})();
