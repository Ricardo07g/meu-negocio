const cfg = window.vendaCreateConfig || {};
cfg.itensOld = cfg.itensOld || [];
cfg.oldDatas = cfg.oldDatas || [];
cfg.oldHorarios = cfg.oldHorarios || [];
cfg.pagamentoDefaults = cfg.pagamentoDefaults || {};
cfg.urls = cfg.urls || {};

document.addEventListener('DOMContentLoaded', function() {
    const diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const tipoVendaInput = document.getElementById('tipoVendaInput');
    const camposServico = document.getElementById('campos-servico');
    const camposProduto = document.getElementById('campos-produto');
    const camposUnico = document.getElementById('campos-unico');
    const camposEtapas = document.getElementById('campos-etapas');
    const previewCard = document.getElementById('previewCard');
    const cardCarrinho = document.getElementById('card-carrinho');

    // Itens selecionados via AJAX (armazenados temporariamente)
    var produtoSelecionado = null;
    var servicoSelecionado = null;

    // Toggle Serviço / Produto
    document.querySelectorAll('.btn-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipoVenda;
            tipoVendaInput.value = tipo;

            document.querySelectorAll('.btn-toggle').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-primary');
            });
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');

            if (tipo === 'servico') {
                camposServico.style.display = 'block';
                camposProduto.style.display = 'none';
                cardCarrinho.style.display = 'none';
                habilitarContainer(camposProduto, false);
                habilitarContainer(cardCarrinho, false);
                aplicarTipoServico(servicoSelecionado);
            } else {
                camposServico.style.display = 'none';
                camposProduto.style.display = 'block';
                cardCarrinho.style.display = 'block';
                habilitarContainer(camposUnico, false);
                habilitarContainer(camposEtapas, false);
                habilitarContainer(camposProduto, true);
                habilitarContainer(cardCarrinho, true);
                previewCard.style.display = 'none';
            }
            if (typeof atualizarHabilitacaoPagamento === 'function') atualizarHabilitacaoPagamento();
        });
    });

    // Toggle Avulso / Pacote conforme tipo do servico selecionado
    function aplicarTipoServico(servico) {
        if (!servico) {
            camposUnico.style.display = 'none';
            camposEtapas.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposUnico, false);
            habilitarContainer(camposEtapas, false);
            return;
        }

        var tipo = servico.tipo;
        if (typeof tipo === 'object' && tipo !== null) tipo = tipo.value || tipo;

        if (tipo === 'unico') {
            camposUnico.style.display = 'block';
            camposEtapas.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposUnico, true);
            habilitarContainer(camposEtapas, false);
            atualizarFimUnico();
        } else if (tipo === 'etapas') {
            camposUnico.style.display = 'none';
            camposEtapas.style.display = 'block';
            habilitarContainer(camposUnico, false);
            habilitarContainer(camposEtapas, true);
            var qtdEtapasInput = document.getElementById('qtdEtapasInput');
            var valorTotal = document.getElementById('valorTotal');
            if (servico.qtd_etapas && !qtdEtapasInput.value) {
                qtdEtapasInput.value = servico.qtd_etapas;
            }
            if (servico.valor) {
                valorTotal.value = parseFloat(servico.valor).toFixed(2);
            }
            atualizarValorPorSessao();
        }
    }

    function habilitarContainer(container, habilitar) {
        container.querySelectorAll('input, select, textarea, button').forEach(function(el) {
            el.disabled = !habilitar;
        });
    }

    // ===== CARRINHO DE PRODUTOS =====
    const carrinhoItens = [];
    const carrinhoTbody = document.getElementById('carrinhoTbody');
    const carrinhoTotal = document.getElementById('carrinhoTotal');
    const carrinhoSubtotal = document.getElementById('carrinhoSubtotal');
    const carrinhoDescontos = document.getElementById('carrinhoDescontos');
    const carrinhoAcrescimos = document.getElementById('carrinhoAcrescimos');
    const carrinhoResumo = document.getElementById('carrinhoResumo');
    const carrinhoHidden = document.getElementById('carrinhoHiddenInputs');
    const btnAdicionar = document.getElementById('btnAdicionarProduto');
    const produtoQtd = document.getElementById('produtoQtd');
    const produtoPreco = document.getElementById('produtoPreco');
    const produtoSearch = document.getElementById('produtoSearch');
    const produtoHidden = document.getElementById('produtoHidden');

    btnAdicionar.addEventListener('click', function() {
        if (!produtoSelecionado) return;

        const produtoId = produtoSelecionado.id;
        const qtd = parseInt(produtoQtd.value) || 1;
        const preco = parseFloat(produtoPreco.value) || parseFloat(produtoSelecionado.valor_venda);
        const existente = carrinhoItens.find(i => i.produto_id === produtoId);

        if (existente) {
            existente.quantidade += qtd;
            existente.valor_unitario = preco;
        } else {
            carrinhoItens.push({
                produto_id: produtoId,
                nome: produtoSelecionado.nome,
                imagem_thumb_url: produtoSelecionado.imagem_thumb_url,
                quantidade: qtd,
                valor_unitario: preco,
                desconto: 0,
                acrescimo: 0,
            });
        }

        renderCarrinho();
        produtoSearch.value = '';
        produtoHidden.value = '';
        produtoPreco.value = '';
        produtoQtd.value = 1;
        produtoSelecionado = null;
        produtoSearch.focus();
    });

    function renderCarrinho() {
        carrinhoTbody.innerHTML = '';

        if (carrinhoItens.length === 0) {
            carrinhoTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum item adicionado.</td></tr>';
            carrinhoResumo.style.display = 'none';
            return;
        }

        let subtotalGeral = 0;
        let descontoGeral = 0;
        let acrescimoGeral = 0;

        carrinhoItens.forEach(function(item, idx) {
            const itemTotal = (item.quantidade * item.valor_unitario) - item.desconto + item.acrescimo;
            subtotalGeral += item.quantidade * item.valor_unitario;
            descontoGeral += item.desconto;
            acrescimoGeral += item.acrescimo;

            const tr = document.createElement('tr');
            const miniatura = item.imagem_thumb_url
                ? '<img src="' + item.imagem_thumb_url + '" class="rounded me-2" style="width:28px;height:28px;object-fit:cover;vertical-align:middle;">'
                : '';
            tr.innerHTML =
                '<td>' + miniatura + item.nome + '</td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center" value="' + item.quantidade + '" min="1" data-idx="' + idx + '" data-campo="quantidade"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.valor_unitario.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="valor_unitario"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.desconto.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="desconto"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.acrescimo.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="acrescimo"></td>' +
                '<td class="text-end fw-bold">R$ ' + itemTotal.toFixed(2).replace('.', ',') + '</td>' +
                '<td class="text-center py-2"><button type="button" class="btn btn-sm btn-danger text-white" data-remover="' + idx + '"><i class="feather-trash-2"></i></button></td>';
            carrinhoTbody.appendChild(tr);
        });

        const totalGeral = subtotalGeral - descontoGeral + acrescimoGeral;
        carrinhoSubtotal.textContent = 'R$ ' + subtotalGeral.toFixed(2).replace('.', ',');
        carrinhoDescontos.textContent = descontoGeral > 0 ? '- R$ ' + descontoGeral.toFixed(2).replace('.', ',') : 'R$ 0,00';
        carrinhoAcrescimos.textContent = acrescimoGeral > 0 ? '+ R$ ' + acrescimoGeral.toFixed(2).replace('.', ',') : 'R$ 0,00';
        carrinhoTotal.textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
        carrinhoResumo.style.display = 'flex';

        // Eventos de edicao inline
        carrinhoTbody.querySelectorAll('input[data-campo]').forEach(function(input) {
            input.addEventListener('input', function() {
                const idx = parseInt(this.dataset.idx);
                const campo = this.dataset.campo;
                let val = parseFloat(this.value) || 0;
                if (campo === 'quantidade') val = Math.max(1, Math.round(val));
                carrinhoItens[idx][campo] = val;
                renderCarrinho();
            });
        });

        // Eventos de remover
        carrinhoTbody.querySelectorAll('button[data-remover]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                carrinhoItens.splice(parseInt(this.dataset.remover), 1);
                renderCarrinho();
            });
        });
    }

    // Montar hidden inputs antes do submit
    document.getElementById('formNovaVenda').addEventListener('submit', function() {
        carrinhoHidden.innerHTML = '';
        carrinhoItens.forEach(function(item, idx) {
            ['produto_id', 'quantidade', 'valor_unitario', 'desconto', 'acrescimo'].forEach(function(campo) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'itens[' + idx + '][' + campo + ']';
                input.value = item[campo];
                carrinhoHidden.appendChild(input);
            });
        });
    });

    // Valor por etapa (servico em etapas)
    const valorTotal = document.getElementById('valorTotal');
    const qtdEtapasInput = document.getElementById('qtdEtapasInput');
    const valorPorSessao = document.getElementById('valorPorSessao');

    valorTotal.addEventListener('input', atualizarValorPorSessao);
    qtdEtapasInput.addEventListener('input', atualizarValorPorSessao);

    function atualizarValorPorSessao() {
        const qtd = parseInt(qtdEtapasInput.value) || 0;
        const val = parseFloat(valorTotal.value) || 0;
        if (qtd > 0 && val > 0) {
            valorPorSessao.textContent = 'R$ ' + (val / qtd).toFixed(2).replace('.', ',') + ' por etapa';
        } else {
            valorPorSessao.textContent = '';
        }
    }

    // Calcula e exibe o horario de termino para servico unico
    const horarioUnico = document.getElementById('horarioUnico');
    const fimCalculadoUnico = document.getElementById('fimCalculadoUnico');

    horarioUnico.addEventListener('input', atualizarFimUnico);

    function atualizarFimUnico() {
        if (!servicoSelecionado || !horarioUnico.value) {
            fimCalculadoUnico.textContent = '';
            return;
        }
        const duracao = parseInt(servicoSelecionado.duracao) || 0;
        if (duracao <= 0) {
            fimCalculadoUnico.textContent = '';
            return;
        }
        const [h, m] = horarioUnico.value.split(':').map(Number);
        const totalMin = h * 60 + m + duracao;
        const hFim = String(Math.floor(totalMin / 60) % 24).padStart(2, '0');
        const mFim = String(totalMin % 60).padStart(2, '0');
        fimCalculadoUnico.textContent = 'Termina às ' + hFim + ':' + mFim;
    }

    // Gerar preview das etapas (servico em etapas)
    document.getElementById('btnGerarPreview').addEventListener('click', function() {
        const dataInicio = document.getElementById('dataInicio');
        const horario = document.getElementById('horario');

        if (!servicoSelecionado) { swalAlerta('Selecione um serviço.'); return; }
        if (!dataInicio.value) { swalAlerta('Informe a data de início.'); return; }

        const qtdEtapas = parseInt(qtdEtapasInput.value);
        if (!qtdEtapas || qtdEtapas < 2) { swalAlerta('Informe a quantidade de etapas (mínimo 2).'); qtdEtapasInput.focus(); return; }

        const diasSemana = [];
        document.querySelectorAll('.dias-semana-check:checked').forEach(cb => diasSemana.push(parseInt(cb.value)));
        if (diasSemana.length === 0) { swalAlerta('Selecione pelo menos um dia da semana.'); return; }

        const duracao = parseInt(servicoSelecionado.duracao);
        const inicio = new Date(dataInicio.value + 'T12:00:00');
        const datas = [];
        let cursor = new Date(inicio);
        let seguranca = 0;

        while (datas.length < qtdEtapas && seguranca < 365) {
            if (diasSemana.includes(cursor.getDay())) datas.push(new Date(cursor));
            cursor.setDate(cursor.getDate() + 1);
            seguranca++;
        }

        const tbody = document.getElementById('sessoesTbody');
        tbody.innerHTML = '';
        datas.forEach((data, i) => {
            const dataStr = data.toISOString().split('T')[0];
            const tr = document.createElement('tr');
            const fh = horario.value.split(':').map(Number);
            const totalMin = fh[0] * 60 + fh[1] + duracao;
            const fim = String(Math.floor(totalMin / 60) % 24).padStart(2, '0') + ':' + String(totalMin % 60).padStart(2, '0');
            tr.innerHTML = `<td>${i + 1}</td><td>${diasNomes[data.getDay()]}</td><td><input type="date" name="datas[]" value="${dataStr}" class="form-control form-control-sm" required></td><td><input type="time" name="horarios[]" value="${horario.value}" class="form-control form-control-sm" required></td>`;
            tbody.appendChild(tr);
        });

        document.getElementById('qtdEtapasBadge').textContent = datas.length + ' etapas';
        previewCard.style.display = 'block';
        previewCard.scrollIntoView({ behavior: 'smooth' });
        atualizarValorPorSessao();
    });

    // Inicializar estado
    if (tipoVendaInput.value === 'produto') {
        habilitarContainer(camposProduto, true);
        habilitarContainer(cardCarrinho, true);
    }

    // Restaurar estado apos erro de validacao
    if (cfg.itensOld.length) {
        cfg.itensOld.forEach(function(item) {
            carrinhoItens.push({
                produto_id: item.produto_id,
                nome: item.nome,
                quantidade: item.quantidade,
                valor_unitario: parseFloat(item.valor_unitario),
                desconto: parseFloat(item.desconto),
                acrescimo: parseFloat(item.acrescimo),
            });
        });
        renderCarrinho();
    }

    if (cfg.servicoOld) {
        servicoSelecionado = cfg.servicoOld;
        aplicarTipoServico(servicoSelecionado);
    }

    if (cfg.oldDatas && cfg.oldDatas.length) {
        (function() {
            const oldDatas = cfg.oldDatas;
            const oldHorarios = cfg.oldHorarios || [];
            const tbody = document.getElementById('sessoesTbody');
            tbody.innerHTML = '';
            oldDatas.forEach(function(dataStr, i) {
                const horario = oldHorarios[i] || '09:00';
                const dateObj = new Date(dataStr + 'T12:00:00');
                const tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (i + 1) + '</td>' +
                               '<td>' + diasNomes[dateObj.getDay()] + '</td>' +
                               '<td><input type="date" name="datas[]" value="' + dataStr + '" class="form-control form-control-sm" required></td>' +
                               '<td><input type="time" name="horarios[]" value="' + horario + '" class="form-control form-control-sm" required></td>';
                tbody.appendChild(tr);
            });
            document.getElementById('qtdEtapasBadge').textContent = oldDatas.length + ' etapas';
            previewCard.style.display = 'block';
        })();
    }

    // Toggle Forma/Condição de Pagamento (à vista, a prazo, boleto, pix parcelado)
    const formaPagamentoWrapper = document.getElementById('formaPagamentoWrapper');
    const formaPagamentoSelect = document.getElementById('formaPagamentoSelect');
    const parcelasWrapper = document.getElementById('parcelasWrapper');
    const numeroParcelasInput = document.getElementById('numeroParcelas');
    const primeiroVencimentoWrapper = document.getElementById('primeiroVencimentoWrapper');
    const primeiroVencimento = document.getElementById('primeiroVencimento');
    const mesReferencia = document.getElementById('mesReferencia');
    const parceladoAviso = document.getElementById('parceladoAviso');
    const valorPorParcelaHint = document.getElementById('valorPorParcelaHint');

    const condicaoSelect = document.getElementById('condicaoPagamentoSelect');

    function condicaoSelecionada() {
        return condicaoSelect ? condicaoSelect.value : 'a_vista';
    }

    const previewCarneCard = document.getElementById('previewCarneCard');
    const previewCarneBadge = document.getElementById('previewCarneBadge');
    const previewCarneInfo = document.getElementById('previewCarneInfo');
    const carneTbody = document.getElementById('carneTbody');
    const carneTotalFoot = document.getElementById('carneTotalFoot');

    // Opcoes de forma_pagamento por condicao. Mantem em sincronia com FormaPagamento.cases().
    const FORMAS_AVISTA = [
        { value: 'pix', label: 'Pix' },
        { value: 'dinheiro', label: 'Dinheiro' },
        { value: 'cartao', label: 'Cartão' },
    ];
    const FORMAS_APRAZO = [
        { value: 'boleto', label: 'Boleto' },
        { value: 'pix', label: 'Pix' },
        { value: 'cartao', label: 'Cartão' },
    ];

    function popularFormasPagamento(opcoes) {
        const valorAtual = formaPagamentoSelect.value || formaPagamentoSelect.dataset.old || '';
        formaPagamentoSelect.innerHTML = '<option value="">Selecione...</option>'
            + opcoes.map(o => `<option value="${o.value}">${o.label}</option>`).join('');
        // Restaura a selecao se ainda for valida no novo conjunto.
        if (opcoes.some(o => o.value === valorAtual)) {
            formaPagamentoSelect.value = valorAtual;
        } else {
            formaPagamentoSelect.value = '';
        }
    }

    function aplicarCondicaoPagamento() {
        const c = condicaoSelecionada();
        const aVista = c === 'a_vista';
        const aPrazo = c === 'a_prazo';
        const parcelado = aPrazo;
        const exigeForma = aVista || aPrazo;

        formaPagamentoWrapper.style.display = exigeForma ? '' : 'none';
        formaPagamentoSelect.disabled = !exigeForma;
        if (!exigeForma) {
            formaPagamentoSelect.value = '';
        } else {
            popularFormasPagamento(aVista ? FORMAS_AVISTA : FORMAS_APRAZO);
        }

        const formaLabel = document.getElementById('formaPagamentoLabel');
        if (formaLabel) {
            formaLabel.innerHTML = aVista
                ? 'Recebimento à vista em <span class="text-danger">*</span>'
                : 'Forma de recebimento prevista <span class="text-danger">*</span>';
        }

        parcelasWrapper.style.display = parcelado ? '' : 'none';
        numeroParcelasInput.disabled = !parcelado;
        primeiroVencimentoWrapper.style.display = parcelado ? '' : 'none';
        primeiroVencimento.disabled = !parcelado;
        parceladoAviso.style.display = parcelado ? '' : 'none';
        previewCarneCard.style.display = parcelado ? '' : 'none';

        atualizarPreviewCarne();
    }

    function obterTotalVenda() {
        const valorTotalEl = document.getElementById('valorTotal');
        const carrinhoTotalEl = document.getElementById('carrinhoTotal');
        if (valorTotalEl && valorTotalEl.value && parseFloat(valorTotalEl.value) > 0) {
            return parseFloat(valorTotalEl.value);
        }
        if (tipoVendaInput.value === 'produto' && carrinhoTotalEl) {
            const raw = carrinhoTotalEl.textContent.replace(/[^0-9,.-]/g, '').replace('.', '').replace(',', '.');
            return parseFloat(raw) || 0;
        }
        if (servicoSelecionado && servicoSelecionado.valor) {
            return parseFloat(servicoSelecionado.valor);
        }
        return 0;
    }

    function formatarBR(v) { return v.toFixed(2).replace('.', ','); }

    function formatarDataBR(dateObj) {
        const d = String(dateObj.getDate()).padStart(2, '0');
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        return d + '/' + m + '/' + dateObj.getFullYear();
    }

    function adicionarMeses(base, meses) {
        const d = new Date(base.getFullYear(), base.getMonth() + meses, base.getDate());
        return d;
    }

    function dataIsoToMonth(iso) { return iso.substring(0, 7); }

    function recalcularSomaCarne() {
        const inputs = carneTbody.querySelectorAll('input[data-parcela-valor]');
        let soma = 0;
        inputs.forEach(function (el) { soma += parseFloat(el.value) || 0; });

        carneTotalFoot.textContent = 'R$ ' + formatarBR(soma);

        const total = obterTotalVenda();
        const diff = Math.round((soma - total) * 100) / 100;
        const badge = document.getElementById('carneDiferencaBadge');
        const badgeTxt = document.getElementById('carneDiferencaTexto');
        if (badge) {
            if (Math.abs(diff) < 0.01 || total <= 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = '';
                badgeTxt.textContent = (diff > 0 ? 'Excedendo' : 'Faltando') + ' R$ ' + formatarBR(Math.abs(diff));
            }
        }
    }

    function atualizarPreviewCarne() {
        if (condicaoSelecionada() === 'a_vista') {
            valorPorParcelaHint.textContent = '';
            previewCarneBadge.textContent = '';
            carneTbody.innerHTML = '';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }

        const n = parseInt(numeroParcelasInput.value) || 0;
        const total = obterTotalVenda();

        if (n < 2 || n > 24) {
            valorPorParcelaHint.textContent = 'Informe entre 2 e 24 parcelas.';
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe 2 a 24 parcelas.</td></tr>';
            previewCarneBadge.textContent = '';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }
        if (total <= 0) {
            valorPorParcelaHint.textContent = '';
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o valor da venda para simular as parcelas.</td></tr>';
            previewCarneBadge.textContent = n + 'x';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }

        const vencRaw = primeiroVencimento.value;
        if (!vencRaw) {
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o primeiro vencimento.</td></tr>';
            return;
        }

        const porParcela = Math.round((total / n) * 100) / 100;
        const ultima = Math.round((total - porParcela * (n - 1)) * 100) / 100;

        valorPorParcelaHint.textContent = n + 'x de R$ ' + formatarBR(porParcela);
        previewCarneBadge.textContent = n + ' parcelas';
        previewCarneInfo.textContent =
            'Ajuste valor, vencimento e competência de cada parcela se necessário. A soma precisa bater com o total da venda.';

        const baseVenc = new Date(vencRaw + 'T12:00:00');
        const linhas = [];
        for (let i = 1; i <= n; i++) {
            const venc = adicionarMeses(baseVenc, i - 1);
            const vencIso = venc.getFullYear() + '-' +
                String(venc.getMonth() + 1).padStart(2, '0') + '-' +
                String(venc.getDate()).padStart(2, '0');
            // Competencia da parcela = mes do seu vencimento.
            const mesRefParcela = vencIso.substring(0, 7);
            const val = i === n ? ultima : porParcela;
            const idx = i - 1;
            linhas.push(
                '<tr>' +
                '<td><span class="fw-semibold">' + i + '/' + n + '</span>' +
                '<input type="hidden" name="parcelas[' + idx + '][numero]" value="' + i + '">' +
                '<input type="hidden" name="parcelas[' + idx + '][total]" value="' + n + '">' +
                '</td>' +
                '<td><input type="date" name="parcelas[' + idx + '][data_vencimento]" ' +
                '       class="form-control form-control-sm" data-parcela-vencimento value="' + vencIso + '" required></td>' +
                '<td><input type="month" name="parcelas[' + idx + '][mes_referencia]" ' +
                '       class="form-control form-control-sm" data-parcela-mes-ref value="' + mesRefParcela + '" required></td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0.01" data-parcela-valor ' +
                '       name="parcelas[' + idx + '][valor]" ' +
                '       class="form-control form-control-sm text-end" value="' + val.toFixed(2) + '" required></td>' +
                '</tr>'
            );
        }
        carneTbody.innerHTML = linhas.join('');

        // Quando o usuario muda o vencimento de uma parcela manualmente, atualiza
        // a competencia daquela linha (a menos que ja tenha sido editada manual).
        carneTbody.querySelectorAll('input[data-parcela-vencimento]').forEach(function (el) {
            el.addEventListener('change', function () {
                const tr = el.closest('tr');
                const mesRefInput = tr.querySelector('input[data-parcela-mes-ref]');
                if (mesRefInput && el.value) {
                    mesRefInput.value = el.value.substring(0, 7);
                }
            });
        });

        carneTbody.querySelectorAll('input[data-parcela-valor]').forEach(function (el) {
            el.addEventListener('input', recalcularSomaCarne);
        });

        recalcularSomaCarne();
    }

    if (condicaoSelect) condicaoSelect.addEventListener('change', function() { atualizarHabilitacaoPagamento(); });
    numeroParcelasInput.addEventListener('input', atualizarPreviewCarne);
    primeiroVencimento.addEventListener('change', atualizarPreviewCarne);
    mesReferencia.addEventListener('change', atualizarPreviewCarne);
    const valorTotalEl = document.getElementById('valorTotal');
    if (valorTotalEl) valorTotalEl.addEventListener('input', function() { atualizarHabilitacaoPagamento(); });
    const carrinhoTotalObs = document.getElementById('carrinhoTotal');
    if (carrinhoTotalObs) {
        new MutationObserver(function() { atualizarHabilitacaoPagamento(); }).observe(carrinhoTotalObs, { childList: true, characterData: true, subtree: true });
    }

    // ── Habilitação do card de Pagamento conforme estado da venda ──
    const cardPagamento = document.getElementById('cardPagamento');
    const pagamentoAvisoInline = document.getElementById('pagamentoAvisoInline');
    const pagamentoAvisoTexto = document.getElementById('pagamentoAvisoTexto');

    function obterEstadoVenda() {
        const tipo = tipoVendaInput.value;
        if (tipo === 'produto') {
            if (carrinhoItens.length === 0) {
                return { pronto: false, motivo: 'Adicione ao menos 1 produto ao carrinho.' };
            }
            return { pronto: true, motivo: '' };
        }
        if (!servicoSelecionado) {
            return { pronto: false, motivo: 'Selecione um serviço para a venda.' };
        }
        const tipoServ = (typeof servicoSelecionado.tipo === 'object' && servicoSelecionado.tipo !== null)
            ? (servicoSelecionado.tipo.value || servicoSelecionado.tipo)
            : servicoSelecionado.tipo;
        if (tipoServ === 'etapas') {
            const vt = parseFloat(document.getElementById('valorTotal').value) || 0;
            if (vt <= 0) return { pronto: false, motivo: 'Informe o valor total da venda.' };
        }
        return { pronto: true, motivo: '' };
    }

    const PAGAMENTO_DEFAULTS = {
        condicao: 'a_vista',
        forma: '',
        numeroParcelas: '2',
        primeiroVencimento: cfg.pagamentoDefaults.primeiroVencimento,
        mesReferencia: cfg.pagamentoDefaults.mesReferencia,
    };

    function resetarPagamentoDefault() {
        condicaoSelect.value = PAGAMENTO_DEFAULTS.condicao;
        formaPagamentoSelect.value = PAGAMENTO_DEFAULTS.forma;
        numeroParcelasInput.value = PAGAMENTO_DEFAULTS.numeroParcelas;
        primeiroVencimento.value = PAGAMENTO_DEFAULTS.primeiroVencimento;
        mesReferencia.value = PAGAMENTO_DEFAULTS.mesReferencia;
        valorPorParcelaHint.textContent = '';
        previewCarneBadge.textContent = '';
        previewCarneInfo.textContent = 'Simulação das parcelas que serão geradas ao salvar.';
        carneTbody.innerHTML = '';
        carneTotalFoot.textContent = 'R$ 0,00';
    }

    let ultimoEstadoPronto = null;

    function atualizarHabilitacaoPagamento() {
        const { pronto, motivo } = obterEstadoVenda();

        // Reset dos campos só na transição pronto → não pronto, pra não apagar old() após erro de validação
        if (ultimoEstadoPronto === true && !pronto) {
            resetarPagamentoDefault();
        }
        ultimoEstadoPronto = pronto;

        cardPagamento.classList.toggle('opacity-50', !pronto);
        cardPagamento.querySelectorAll('input, select, textarea').forEach(function(el) {
            el.disabled = !pronto;
        });

        if (pronto) {
            pagamentoAvisoInline.style.display = 'none';
            aplicarCondicaoPagamento();
        } else {
            pagamentoAvisoTexto.textContent = motivo;
            pagamentoAvisoInline.style.display = '';
            previewCarneCard.style.display = 'none';
        }
    }

    // Recalcular habilitação quando o carrinho é alterado
    const origRenderCarrinho = renderCarrinho;
    renderCarrinho = function() {
        origRenderCarrinho.apply(this, arguments);
        atualizarHabilitacaoPagamento();
    };

    aplicarCondicaoPagamento();
    atualizarHabilitacaoPagamento();

    // Bloqueia submit se a venda não estiver pronta
    document.getElementById('formNovaVenda').addEventListener('submit', function(e) {
        const { pronto, motivo } = obterEstadoVenda();
        if (!pronto) {
            e.preventDefault();
            swalAlerta(motivo);
        }
    });

    // AJAX Search — Serviço
    initAjaxSearch({
        inputId: 'servicoSearch',
        hiddenId: 'servicoHidden',
        url: cfg.urls.servicos,
        renderItem: function(item) {
            var tipo = item.tipo;
            if (typeof tipo === 'object' && tipo !== null) tipo = tipo.value || tipo;
            var badge = tipo === 'etapas' ? '<span class="badge bg-info ms-1">Etapas</span>' : '<span class="badge bg-secondary ms-1">Único</span>';
            var mini = item.imagem_thumb_url ? '<img src="' + item.imagem_thumb_url + '" class="rounded me-2" style="width:32px;height:32px;object-fit:cover;vertical-align:middle;">' : '';
            return mini + '<strong>' + item.nome + '</strong> ' + badge + '<br><small class="text-muted">R$ ' + parseFloat(item.valor).toFixed(2).replace('.', ',') + ' — ' + item.duracao + ' min</small>';
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) {
            servicoSelecionado = item;
            aplicarTipoServico(item);
            atualizarHabilitacaoPagamento();
        },
        onClear: function() {
            servicoSelecionado = null;
            aplicarTipoServico(null);
            atualizarHabilitacaoPagamento();
        },
    });

    // ===== Card do cliente selecionado =====
    function escaparHtml(valor) {
        if (valor === null || valor === undefined) return '';
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function limparClienteCard() {
        var card = document.getElementById('clienteCard');
        if (!card) return;
        card.innerHTML = '';
        card.style.display = 'none';
    }

    function trocarCliente() {
        var input = document.getElementById('clienteSearch');
        var hidden = document.getElementById('clienteHidden');
        if (input) input.value = '';
        if (hidden) hidden.value = '';
        limparClienteCard();
        if (input) input.focus();
    }

    function renderClienteCard(item) {
        var card = document.getElementById('clienteCard');
        if (!card || !item) return;

        var inicial = item.nome ? item.nome.trim().charAt(0).toUpperCase() : '?';
        var fotoUrl = item.imagem_thumb_url || item.imagem_url;
        var avatar = fotoUrl
            ? '<img src="' + escaparHtml(fotoUrl) + '" alt="' + escaparHtml(item.nome) + '" class="rounded-circle flex-shrink-0" style="width:40px;height:40px;object-fit:cover;">'
            : '<div class="bg-primary text-white rounded-circle fw-bold d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;">' + escaparHtml(inicial) + '</div>';

        var telefone = item.telefone ? escaparHtml(item.telefone) : '-';
        if (item.telefone && item.telefone_whatsapp) {
            telefone += ' <span class="badge bg-success ms-1"><i class="feather-message-circle me-1"></i>WhatsApp</span>';
        }

        var sexo = item.sexo === 'M' ? 'Masculino'
            : item.sexo === 'F' ? 'Feminino'
            : item.sexo === 'outro' ? 'Outro'
            : '-';

        var nascimento = '-';
        if (item.data_nascimento) {
            nascimento = escaparHtml(item.data_nascimento);
            if (item.idade !== null && item.idade !== undefined) {
                nascimento += ' <small class="text-muted">(' + escaparHtml(item.idade) + ' anos)</small>';
            }
        }

        var endLinhas = [];
        if (item.logradouro) {
            endLinhas.push(escaparHtml(item.logradouro)
                + (item.numero ? ', ' + escaparHtml(item.numero) : '')
                + (item.complemento ? ' - ' + escaparHtml(item.complemento) : ''));
        }
        if (item.bairro) endLinhas.push(escaparHtml(item.bairro));
        if (item.cidade || item.estado) {
            endLinhas.push(escaparHtml(item.cidade || '')
                + (item.estado ? (item.cidade ? ' - ' : '') + escaparHtml(item.estado) : ''));
        }
        if (item.cep) endLinhas.push('CEP: ' + escaparHtml(item.cep));
        var endereco = endLinhas.length ? endLinhas.join('<br>') : '-';

        function campo(label, valor, colClass) {
            return '<div class="' + colClass + '">'
                + '<div class="fs-11 text-uppercase text-muted mb-1">' + label + '</div>'
                + '<div class="fw-semibold">' + valor + '</div>'
                + '</div>';
        }

        card.innerHTML =
            '<div class="card border shadow-none mb-0">'
                + '<div class="card-body">'
                    + '<div class="d-flex align-items-center justify-content-between mb-3">'
                        + '<div class="hstack gap-3">'
                            + avatar
                            + '<h6 class="mb-0 fw-bold">' + escaparHtml(item.nome || '') + '</h6>'
                        + '</div>'
                        + '<button type="button" id="clienteTrocar" class="btn btn-sm btn-light">'
                            + '<i class="feather-refresh-cw me-1"></i>Trocar cliente'
                        + '</button>'
                    + '</div>'
                    + '<div class="row g-3">'
                        + campo('Telefone', telefone, 'col-6 col-md-4')
                        + campo('Email', item.email ? escaparHtml(item.email) : '-', 'col-6 col-md-4')
                        + campo('CPF', item.cpf ? escaparHtml(item.cpf) : '-', 'col-6 col-md-4')
                        + campo('Nascimento', nascimento, 'col-6 col-md-4')
                        + campo('Sexo', sexo, 'col-6 col-md-4')
                        + campo('Endereço', endereco, 'col-12 col-md-8')
                    + '</div>'
                + '</div>'
            + '</div>';

        card.style.display = '';

        var btnTrocar = document.getElementById('clienteTrocar');
        if (btnTrocar) btnTrocar.addEventListener('click', trocarCliente);
    }

    // AJAX Search — Cliente
    initAjaxSearch({
        inputId: 'clienteSearch',
        hiddenId: 'clienteHidden',
        url: cfg.urls.clientes,
        renderItem: function(item) {
            var mini = item.imagem_thumb_url ? '<img src="' + item.imagem_thumb_url + '" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;vertical-align:middle;">' : '';
            return mini + '<strong>' + item.nome + '</strong>' + (item.telefone ? '<br><small class="text-muted">' + item.telefone + '</small>' : '');
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) { renderClienteCard(item); },
        onClear: function() { limparClienteCard(); },
    });

    // Renderiza o card se houver cliente pré-selecionado (repopulação após erro de validação)
    if (cfg.clienteSelecionado) {
        renderClienteCard(cfg.clienteSelecionado);
    }

    // AJAX Search — Produto
    initAjaxSearch({
        inputId: 'produtoSearch',
        hiddenId: 'produtoHidden',
        url: cfg.urls.produtos,
        renderItem: function(item) {
            var mini = item.imagem_thumb_url ? '<img src="' + item.imagem_thumb_url + '" class="rounded me-2" style="width:32px;height:32px;object-fit:cover;vertical-align:middle;">' : '';
            return mini + '<strong>' + item.nome + '</strong><br><small class="text-muted">R$ ' + parseFloat(item.valor_venda).toFixed(2).replace('.', ',') + ' — ' + item.quantidade + ' em estoque</small>';
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) {
            produtoSelecionado = item;
            produtoPreco.value = parseFloat(item.valor_venda).toFixed(2);
            produtoQtd.value = 1;
            produtoQtd.focus();
        },
        onClear: function() {
            produtoSelecionado = null;
            produtoPreco.value = '';
        },
    });
});
