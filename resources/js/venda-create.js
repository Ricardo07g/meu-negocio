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
    const carrinhoCards = document.getElementById('carrinhoCards');
    const carrinhoVazioBlock = document.getElementById('carrinhoVazioBlock');
    const carrinhoTabelaWrap = document.getElementById('carrinhoTabelaWrap');
    const carrinhoContador = document.getElementById('carrinhoContador');
    const carrinhoDescontosRow = document.getElementById('carrinhoDescontosRow');
    const carrinhoAcrescimosRow = document.getElementById('carrinhoAcrescimosRow');
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

    function formatarMoedaCarrinho(valor) {
        return 'R$ ' + valor.toFixed(2).replace('.', ',');
    }

    function totalDoItemCarrinho(item) {
        return (item.quantidade * item.valor_unitario) - item.desconto + item.acrescimo;
    }

    function miniaturaCarrinho(item) {
        return item.imagem_thumb_url
            ? '<img src="' + escaparHtml(item.imagem_thumb_url) + '" class="rounded flex-shrink-0" style="width:40px;height:40px;object-fit:cover;">'
            : '<span class="d-inline-flex align-items-center justify-content-center rounded bg-soft-primary text-primary flex-shrink-0" style="width:40px;height:40px;"><i class="feather-package"></i></span>';
    }

    function campoCarrinhoMobile(idx, campo, label, valor, step, min) {
        return '<div class="col-6">' +
            '<label class="form-label fs-12 text-muted mb-1">' + label + '</label>' +
            '<input type="number" class="form-control form-control-sm text-end" value="' + valor + '" step="' + step + '" min="' + min + '" data-idx="' + idx + '" data-campo="' + campo + '">' +
        '</div>';
    }

    const PASSO_VALOR = '0.50'; // passo das setinhas dos campos de valor: R$ 0,50

    // Reflete o valor de um campo em todos os layouts (tabela + cards), opcionalmente exceto um elemento
    function setCampoInputsCarrinho(idx, campo, valor, exceto) {
        document.querySelectorAll('[data-idx="' + idx + '"][data-campo="' + campo + '"]').forEach(function(el) {
            if (el !== exceto) el.value = valor;
        });
    }

    // Atualiza apenas o total de uma linha (desktop + card mobile compartilham o mesmo data-total-idx)
    function atualizarTotalLinhaCarrinho(idx) {
        const texto = formatarMoedaCarrinho(totalDoItemCarrinho(carrinhoItens[idx]));
        document.querySelectorAll('[data-total-idx="' + idx + '"]').forEach(function(el) {
            el.textContent = texto;
        });
    }

    // Atualiza o painel de resumo e o contador (sem reconstruir as linhas)
    function atualizarResumoCarrinho() {
        let subtotalGeral = 0, descontoGeral = 0, acrescimoGeral = 0;
        carrinhoItens.forEach(function(item) {
            subtotalGeral += item.quantidade * item.valor_unitario;
            descontoGeral += item.desconto;
            acrescimoGeral += item.acrescimo;
        });
        const totalGeral = subtotalGeral - descontoGeral + acrescimoGeral;

        carrinhoSubtotal.textContent = formatarMoedaCarrinho(subtotalGeral);
        carrinhoDescontos.textContent = '- ' + formatarMoedaCarrinho(descontoGeral);
        carrinhoAcrescimos.textContent = '+ ' + formatarMoedaCarrinho(acrescimoGeral);
        // Definir o total dispara o MutationObserver de #carrinhoTotal (recalcula pagamento/parcelas)
        carrinhoTotal.textContent = formatarMoedaCarrinho(totalGeral);

        // d-flex é !important e venceria style.display; controlar visibilidade só pela classe d-none
        carrinhoDescontosRow.classList.toggle('d-none', descontoGeral <= 0);
        carrinhoAcrescimosRow.classList.toggle('d-none', acrescimoGeral <= 0);

        carrinhoContador.textContent = carrinhoItens.length + (carrinhoItens.length === 1 ? ' item' : ' itens');
        carrinhoContador.classList.toggle('d-none', carrinhoItens.length === 0);
    }

    function renderCarrinho() {
        carrinhoTbody.innerHTML = '';
        carrinhoCards.innerHTML = '';

        // Alterna via classes (as utilitarias d-* do Bootstrap sao !important e venceriam style.display)
        if (carrinhoItens.length === 0) {
            carrinhoVazioBlock.classList.remove('d-none');
            carrinhoTabelaWrap.className = 'd-none';
            carrinhoCards.className = 'd-none';
            carrinhoResumo.classList.add('d-none');
            carrinhoContador.classList.add('d-none');
            return;
        }

        carrinhoVazioBlock.classList.add('d-none');
        carrinhoTabelaWrap.className = 'd-none d-md-block';
        carrinhoCards.className = 'd-md-none';
        carrinhoResumo.classList.remove('d-none');

        carrinhoItens.forEach(function(item, idx) {
            const nome = escaparHtml(item.nome);
            const mini = miniaturaCarrinho(item);
            const totalLinha = formatarMoedaCarrinho(totalDoItemCarrinho(item));

            // Linha da tabela (desktop)
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><div class="d-flex align-items-center gap-2">' + mini + '<span class="fw-semibold">' + nome + '</span></div></td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center" value="' + item.quantidade + '" min="1" data-idx="' + idx + '" data-campo="quantidade"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.valor_unitario.toFixed(2) + '" step="' + PASSO_VALOR + '" min="0" data-idx="' + idx + '" data-campo="valor_unitario"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.desconto.toFixed(2) + '" step="' + PASSO_VALOR + '" min="0" data-idx="' + idx + '" data-campo="desconto"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.acrescimo.toFixed(2) + '" step="' + PASSO_VALOR + '" min="0" data-idx="' + idx + '" data-campo="acrescimo"></td>' +
                '<td class="text-end fw-semibold" data-total-idx="' + idx + '">' + totalLinha + '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-sm btn-light text-danger" data-remover="' + idx + '" aria-label="Remover item"><i class="feather-trash-2"></i></button></td>';
            carrinhoTbody.appendChild(tr);

            // Card do item (mobile)
            const card = document.createElement('div');
            card.className = 'border rounded p-3' + (idx < carrinhoItens.length - 1 ? ' mb-2' : '');
            card.innerHTML =
                '<div class="d-flex align-items-center gap-2 mb-3">' +
                    mini +
                    '<div class="flex-grow-1" style="min-width:0;">' +
                        '<div class="fw-semibold text-truncate">' + nome + '</div>' +
                        '<div class="fs-13 text-muted">Total: <span class="fw-semibold" style="color:var(--cor-destaque);" data-total-idx="' + idx + '">' + totalLinha + '</span></div>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-light text-danger flex-shrink-0" data-remover="' + idx + '" aria-label="Remover item"><i class="feather-trash-2"></i></button>' +
                '</div>' +
                '<div class="row g-2">' +
                    campoCarrinhoMobile(idx, 'quantidade', 'Qtd', item.quantidade, '1', '1') +
                    campoCarrinhoMobile(idx, 'valor_unitario', 'Vl. unit.', item.valor_unitario.toFixed(2), PASSO_VALOR, '0') +
                    campoCarrinhoMobile(idx, 'desconto', 'Desconto', item.desconto.toFixed(2), PASSO_VALOR, '0') +
                    campoCarrinhoMobile(idx, 'acrescimo', 'Acréscimo', item.acrescimo.toFixed(2), PASSO_VALOR, '0') +
                '</div>';
            carrinhoCards.appendChild(card);
        });

        atualizarResumoCarrinho();

        // Edição inline: atualiza modelo, total da linha e resumo sem reconstruir (mantém o foco no campo)
        document.querySelectorAll('#carrinhoTbody input[data-campo], #carrinhoCards input[data-campo]').forEach(function(input) {
            input.addEventListener('input', function() {
                const idx = parseInt(this.dataset.idx);
                const campo = this.dataset.campo;
                let val = parseFloat(this.value) || 0;
                if (campo === 'quantidade') {
                    val = Math.max(1, Math.round(val));
                    if (parseInt(this.value) !== val) this.value = val;
                } else if (val < 0) {
                    val = 0;
                }
                carrinhoItens[idx][campo] = val;

                // Desconto e acréscimo são mutuamente exclusivos: ao preencher um, zera o outro
                if (campo === 'desconto' && val > 0 && carrinhoItens[idx].acrescimo !== 0) {
                    carrinhoItens[idx].acrescimo = 0;
                    setCampoInputsCarrinho(idx, 'acrescimo', '0.00');
                } else if (campo === 'acrescimo' && val > 0 && carrinhoItens[idx].desconto !== 0) {
                    carrinhoItens[idx].desconto = 0;
                    setCampoInputsCarrinho(idx, 'desconto', '0.00');
                }

                // Mantém o mesmo campo em sincronia no outro layout (tabela/cards)
                setCampoInputsCarrinho(idx, campo, this.value, this);

                atualizarTotalLinhaCarrinho(idx);
                atualizarResumoCarrinho();
            });

            // Ao sair do campo, nunca deixa vazio: reexibe o valor do modelo (quantidade inteira, valores com 2 casas)
            input.addEventListener('blur', function() {
                const idx = parseInt(this.dataset.idx);
                const campo = this.dataset.campo;
                const item = carrinhoItens[idx];
                if (!item) return;
                this.value = campo === 'quantidade' ? item[campo] : Number(item[campo]).toFixed(2);
            });
        });

        // Remover item
        document.querySelectorAll('#carrinhoTbody button[data-remover], #carrinhoCards button[data-remover]').forEach(function(btn) {
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
        atualizarResumoEtapas();
    }

    // ===== Preview de etapas: render reativo + validação de duplicatas =====
    const sessoesTbody = document.getElementById('sessoesTbody');
    const resumoEtapas = document.getElementById('resumoEtapas');
    const avisoEtapasDuplicadas = document.getElementById('avisoEtapasDuplicadas');
    let temEtapasDuplicadas = false;

    function formatarReais(valor) {
        return 'R$ ' + (parseFloat(valor) || 0).toFixed(2).replace('.', ',');
    }

    function duracaoServico() {
        return parseInt(servicoSelecionado && servicoSelecionado.duracao) || 0;
    }

    function calcularFimSessao(horario, duracao) {
        if (!horario || duracao <= 0) return '—';
        const partes = horario.split(':').map(Number);
        if (partes.length < 2 || isNaN(partes[0]) || isNaN(partes[1])) return '—';
        const totalMin = partes[0] * 60 + partes[1] + duracao;
        return String(Math.floor(totalMin / 60) % 24).padStart(2, '0') + ':' + String(totalMin % 60).padStart(2, '0');
    }

    function ehFimDeSemana(dia) {
        return dia === 0 || dia === 6;
    }

    // Monta a <tr> de uma sessão (usada tanto ao gerar o preview quanto ao repopular após erro)
    function renderLinhaSessao(indice, dataStr, horarioStr) {
        const dia = new Date(dataStr + 'T12:00:00').getDay();
        const tr = document.createElement('tr');
        tr.dataset.fds = ehFimDeSemana(dia) ? '1' : '0';
        tr.innerHTML =
            '<td class="text-muted">' + (indice + 1) + '</td>' +
            '<td data-cel="dia" class="fw-medium">' + diasNomes[dia] + '</td>' +
            '<td><input type="date" name="datas[]" value="' + dataStr + '" class="form-control form-control-sm" required></td>' +
            '<td><input type="time" name="horarios[]" value="' + horarioStr + '" class="form-control form-control-sm" required></td>' +
            '<td data-cel="fim" class="text-muted">' + calcularFimSessao(horarioStr, duracaoServico()) + '</td>';
        return tr;
    }

    function atualizarResumoEtapas() {
        if (!resumoEtapas || !sessoesTbody) return;
        const qtd = sessoesTbody.querySelectorAll('tr').length;
        const total = parseFloat(valorTotal.value) || 0;
        const celTotal = resumoEtapas.querySelector('[data-cel="resumo-total"]');
        const celEtapa = resumoEtapas.querySelector('[data-cel="resumo-etapa"]');
        if (celTotal) celTotal.textContent = formatarReais(total);
        if (celEtapa) celEtapa.textContent = (qtd > 0 && total > 0) ? (formatarReais(total / qtd) + ' por etapa') : '';
    }

    // Marca em vermelho as sessões com a mesma data+horário e liga o flag de bloqueio do submit
    function revalidarEtapas() {
        if (!sessoesTbody) return;
        const linhas = Array.from(sessoesTbody.querySelectorAll('tr'));
        const contagem = {};
        linhas.forEach(function(tr) {
            const d = tr.querySelector('input[name="datas[]"]');
            const h = tr.querySelector('input[name="horarios[]"]');
            if (!d || !h) return;
            const chave = d.value + '|' + h.value;
            contagem[chave] = (contagem[chave] || 0) + 1;
        });

        temEtapasDuplicadas = false;
        linhas.forEach(function(tr) {
            const d = tr.querySelector('input[name="datas[]"]');
            const h = tr.querySelector('input[name="horarios[]"]');
            if (!d || !h) return;
            const duplicada = contagem[d.value + '|' + h.value] > 1;
            tr.classList.toggle('table-danger', duplicada);
            if (duplicada) {
                temEtapasDuplicadas = true;
                tr.classList.remove('table-secondary');
            } else {
                tr.classList.toggle('table-secondary', tr.dataset.fds === '1');
            }
        });

        if (avisoEtapasDuplicadas) avisoEtapasDuplicadas.classList.toggle('d-none', !temEtapasDuplicadas);
        atualizarResumoEtapas();
    }

    // Edição inline de uma sessão: recalcula o dia da semana e o término, e revalida duplicatas
    function aoEditarSessao(e) {
        const alvo = e.target;
        const tr = alvo.closest ? alvo.closest('tr') : null;
        if (!tr) return;

        if (alvo.matches('input[name="datas[]"]') && alvo.value) {
            const dia = new Date(alvo.value + 'T12:00:00').getDay();
            if (!isNaN(dia)) {
                const celDia = tr.querySelector('[data-cel="dia"]');
                if (celDia) celDia.textContent = diasNomes[dia];
                tr.dataset.fds = ehFimDeSemana(dia) ? '1' : '0';
            }
        }

        if (alvo.matches('input[name="datas[]"]') || alvo.matches('input[name="horarios[]"]')) {
            const h = tr.querySelector('input[name="horarios[]"]');
            const celFim = tr.querySelector('[data-cel="fim"]');
            if (h && celFim) celFim.textContent = calcularFimSessao(h.value, duracaoServico());
        }

        revalidarEtapas();
    }

    if (sessoesTbody) {
        sessoesTbody.addEventListener('input', aoEditarSessao);
        sessoesTbody.addEventListener('change', aoEditarSessao);
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

        const inicio = new Date(dataInicio.value + 'T12:00:00');
        const datas = [];
        let cursor = new Date(inicio);
        let seguranca = 0;

        while (datas.length < qtdEtapas && seguranca < 365) {
            if (diasSemana.includes(cursor.getDay())) datas.push(new Date(cursor));
            cursor.setDate(cursor.getDate() + 1);
            seguranca++;
        }

        sessoesTbody.innerHTML = '';
        datas.forEach((data, i) => {
            sessoesTbody.appendChild(renderLinhaSessao(i, data.toISOString().split('T')[0], horario.value));
        });

        document.getElementById('qtdEtapasBadge').textContent = datas.length + (datas.length === 1 ? ' etapa' : ' etapas');
        previewCard.style.display = 'block';
        previewCard.scrollIntoView({ behavior: 'smooth' });
        atualizarValorPorSessao();
        revalidarEtapas();
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
            sessoesTbody.innerHTML = '';
            oldDatas.forEach(function(dataStr, i) {
                sessoesTbody.appendChild(renderLinhaSessao(i, dataStr, oldHorarios[i] || '09:00'));
            });
            document.getElementById('qtdEtapasBadge').textContent = oldDatas.length + (oldDatas.length === 1 ? ' etapa' : ' etapas');
            previewCard.style.display = 'block';
            revalidarEtapas();
        })();
    }

    // ===== RECEBIMENTOS (split de formas de pagamento) =====
    // Sem toggle à vista/a prazo: o comportamento é derivado da(s) forma(s).
    const numeroParcelasInput = document.getElementById('numeroParcelas');
    const primeiroVencimento = document.getElementById('primeiroVencimento');
    const mesReferencia = document.getElementById('mesReferencia');
    const valorPorParcelaHint = document.getElementById('valorPorParcelaHint');
    const crediarioCampos = document.getElementById('crediarioCampos');
    const formaRecebPrazoSelect = document.getElementById('formaRecebimentoPrazoSelect');

    const previewCarneCard = document.getElementById('previewCarneCard');
    const previewCarneBadge = document.getElementById('previewCarneBadge');
    const previewCarneInfo = document.getElementById('previewCarneInfo');
    const carneTbody = document.getElementById('carneTbody');
    const carneTotalFoot = document.getElementById('carneTotalFoot');

    // Catálogo de formas da empresa (embarcado; a busca é client-side).
    const FORMAS = cfg.formas || [];

    const recebToolbar = document.getElementById('recebToolbar');
    const recebVazio = document.getElementById('recebVazio');
    const recebFormaBusca = document.getElementById('recebFormaBusca');
    const recebDropdown = document.getElementById('recebDropdown');
    const recebValorNovo = document.getElementById('recebValorNovo');
    const recebParcelasNovoWrap = document.getElementById('recebParcelasNovoWrap');
    const recebParcelasNovo = document.getElementById('recebParcelasNovo');
    const btnAdicionarRecebimento = document.getElementById('btnAdicionarRecebimento');
    const recebToolbarAviso = document.getElementById('recebToolbarAviso');
    const recebCompletoInfo = document.getElementById('recebCompletoInfo');
    const recebTotalVenda = document.getElementById('recebTotalVenda');
    const recebTotalInformado = document.getElementById('recebTotalInformado');
    const recebDiferenca = document.getElementById('recebDiferenca');

    // Itens já adicionados: { formaId, formaNome, valor, parcelasCartao } (strings).
    let recebimentos = [];
    // Forma escolhida na toolbar (ainda não adicionada).
    let formaNovaToolbar = null;

    // Empresa selecionada no sub-seletor da tela: as formas listadas são só dela.
    const empresaSelect = document.getElementById('empresa_id');
    function empresaSelecionadaId() {
        const v = empresaSelect ? parseInt(empresaSelect.value) : null;
        return v || cfg.empresaId || null;
    }
    function formasDaEmpresa() {
        const eid = empresaSelecionadaId();
        return eid ? FORMAS.filter(f => String(f.empresa_id) === String(eid)) : FORMAS;
    }
    if (empresaSelect) {
        empresaSelect.addEventListener('change', function () {
            // Trocar de empresa invalida as formas escolhidas: recomeça do zero.
            recebimentos = [];
            limparToolbar();
            renderRecebimentos();
            atualizarHabilitacaoPagamento();
        });
    }

    function formaPorId(id) {
        return FORMAS.find(f => String(f.id) === String(id)) || null;
    }

    // Retorna a forma crediário selecionada (se houver) — dita o modo a prazo.
    function linhaCrediario() {
        for (const r of recebimentos) {
            const f = formaPorId(r.formaId);
            if (f && f.forca_a_prazo) return f;
        }
        return null;
    }

    function ehModoCrediario() {
        return !!linhaCrediario();
    }

    function somaRecebimentos() {
        return recebimentos.reduce((acc, r) => acc + (parseFloat(r.valor) || 0), 0);
    }

    function somaCarneAtual() {
        let soma = 0;
        carneTbody.querySelectorAll('input[data-parcela-valor]').forEach(function (el) { soma += parseFloat(el.value) || 0; });
        return soma;
    }

    // O que ainda falta receber (joga a diferença/centavo na próxima forma adicionada).
    function restanteAReceber() {
        return Math.round((obterTotalVenda() - somaRecebimentos()) * 100) / 100;
    }

    function recalcularRecebimentos() {
        const total = obterTotalVenda();
        const soma = somaRecebimentos();
        recebTotalVenda.textContent = 'R$ ' + formatarBR(total);
        recebTotalInformado.textContent = 'R$ ' + formatarBR(soma);

        const diff = Math.round((soma - total) * 100) / 100;
        let txt = 'R$ 0,00';
        let cls = 'text-muted';
        if (total > 0 && Math.abs(diff) < 0.01) {
            cls = 'text-success';
        } else if (total > 0 && diff < 0) {
            txt = 'Faltam R$ ' + formatarBR(Math.abs(diff));
            cls = 'text-danger';
        } else if (total > 0 && diff > 0) {
            txt = 'Excedem R$ ' + formatarBR(diff);
            cls = 'text-danger';
        }
        recebDiferenca.textContent = txt;
        recebDiferenca.className = 'fw-bold ' + cls;
        atualizarValorNovoDefault();
        atualizarToolbarEstado();
    }

    // Quando o total já foi coberto, não há mais o que receber: inativa a barra
    // de inclusão (não deixa adicionar valor além do total). Excluir um item reabre.
    function atualizarToolbarEstado() {
        if (!recebToolbar) return;
        // Card não pronto: quem controla o disable é atualizarHabilitacaoPagamento.
        if (cardPagamento && cardPagamento.classList.contains('opacity-50')) return;
        // No crediário a barra já fica escondida; nada a inativar.
        if (ehModoCrediario()) {
            if (recebCompletoInfo) recebCompletoInfo.style.display = 'none';
            return;
        }
        const completo = obterTotalVenda() > 0 && restanteAReceber() <= 0.001;
        [recebFormaBusca, recebValorNovo, recebParcelasNovo, btnAdicionarRecebimento].forEach(function (el) {
            if (el) el.disabled = completo;
        });
        recebToolbar.classList.toggle('opacity-50', completo);
        if (recebCompletoInfo) recebCompletoInfo.style.display = completo ? '' : 'none';
        if (completo && recebDropdown) recebDropdown.style.display = 'none';
    }

    // Valor sugerido para o PRÓXIMO recebimento = o que falta. Não atropela o
    // que o usuário está digitando na toolbar.
    function atualizarValorNovoDefault() {
        if (!recebValorNovo || document.activeElement === recebValorNovo) return;
        if (formaNovaToolbar && formaNovaToolbar.forca_a_prazo) {
            recebValorNovo.value = (obterTotalVenda() || 0).toFixed(2);
            return;
        }
        const restante = restanteAReceber();
        recebValorNovo.value = restante > 0 ? restante.toFixed(2) : '';
    }

    function avisarToolbar(msg) {
        if (!recebToolbarAviso) return;
        recebToolbarAviso.textContent = msg || '';
        recebToolbarAviso.style.display = msg ? '' : 'none';
    }

    function limparToolbar() {
        formaNovaToolbar = null;
        if (recebFormaBusca) recebFormaBusca.value = '';
        if (recebDropdown) recebDropdown.style.display = 'none';
        if (recebParcelasNovoWrap) recebParcelasNovoWrap.style.display = 'none';
        if (recebParcelasNovo) recebParcelasNovo.value = '1';
        avisarToolbar('');
    }

    // A forma é de parcelamento no cartão? (cartão de crédito parcelável)
    function formaParcelavel(forma) {
        return !!(forma && forma.gera_recebivel && forma.permite_parcelas);
    }

    // Dropdown de busca de formas da toolbar (filtro client-side, só da empresa).
    function montarDropdownToolbar(filtro) {
        const q = (filtro || '').toLowerCase();
        const itens = formasDaEmpresa().filter(f => f.nome.toLowerCase().includes(q));
        recebDropdown.innerHTML = '';
        if (itens.length === 0) {
            const vazio = document.createElement('div');
            vazio.className = 'px-3 py-2 text-muted fs-13';
            vazio.textContent = 'Nenhuma forma encontrada.';
            recebDropdown.appendChild(vazio);
        } else {
            itens.forEach(f => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'dropdown-item text-wrap';
                item.textContent = f.nome;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // evita o blur antes do clique
                    escolherFormaToolbar(f);
                });
                recebDropdown.appendChild(item);
            });
        }
        recebDropdown.style.display = '';
    }

    function escolherFormaToolbar(forma) {
        formaNovaToolbar = forma;
        recebFormaBusca.value = forma.nome;
        recebDropdown.style.display = 'none';
        avisarToolbar('');
        // Parcelas só entram na barra quando a forma é de parcelamento (cartão),
        // pois o item, depois de adicionado, não é mais editável (só excluído).
        const parcelavel = formaParcelavel(forma);
        if (recebParcelasNovoWrap) recebParcelasNovoWrap.style.display = parcelavel ? '' : 'none';
        if (recebParcelasNovo) {
            recebParcelasNovo.value = '1';
            if (parcelavel && forma.max_parcelas) recebParcelasNovo.max = forma.max_parcelas;
        }
        atualizarValorNovoDefault();
        recebValorNovo.focus();
    }

    // Adiciona um recebimento a partir da toolbar (forma + valor).
    function tentarAdicionar() {
        if (!formaNovaToolbar) {
            avisarToolbar('Busque e selecione uma forma de recebimento.');
            recebFormaBusca.focus();
            return;
        }
        const forma = formaNovaToolbar;

        if (forma.forca_a_prazo) {
            // Crediário é single-line e financia o total; não combina com outras formas.
            if (recebimentos.length > 0) {
                avisarToolbar('Crediário não pode ser combinado com outras formas de recebimento.');
                return;
            }
            recebimentos.push({ formaId: String(forma.id), formaNome: forma.nome, valor: (obterTotalVenda() || 0).toFixed(2), parcelasCartao: '' });
        } else {
            if (ehModoCrediario()) {
                avisarToolbar('Há um crediário na venda. Remova-o para usar outras formas.');
                return;
            }
            const valor = parseFloat(recebValorNovo.value);
            if (!(valor > 0)) {
                avisarToolbar('Informe o valor deste recebimento.');
                recebValorNovo.focus();
                return;
            }
            // Parcelas do cartão definidas AGORA (o item não será mais editável).
            let parcelasCartao = '';
            if (formaParcelavel(forma)) {
                const p = parseInt(recebParcelasNovo.value) || 1;
                const max = forma.max_parcelas || 1;
                parcelasCartao = String(Math.min(Math.max(1, p), max));
            }
            recebimentos.push({
                formaId: String(forma.id),
                formaNome: forma.nome,
                valor: valor.toFixed(2),
                parcelasCartao: parcelasCartao,
            });
        }
        limparToolbar();
        renderRecebimentos();
        recebFormaBusca.focus();
    }

    if (recebFormaBusca) {
        recebFormaBusca.addEventListener('focus', function () { montarDropdownToolbar(recebFormaBusca.value); });
        recebFormaBusca.addEventListener('input', function () {
            formaNovaToolbar = null; // digitar limpa a seleção até escolher de novo
            if (recebParcelasNovoWrap) recebParcelasNovoWrap.style.display = 'none';
            montarDropdownToolbar(recebFormaBusca.value);
        });
        recebFormaBusca.addEventListener('blur', function () {
            setTimeout(function () { recebDropdown.style.display = 'none'; }, 150);
        });
    }
    btnAdicionarRecebimento.addEventListener('click', tentarAdicionar);

    // Item já adicionado: SOMENTE-LEITURA (forma + valor + parcelas). Para alterar,
    // exclua e adicione de novo. Os valores vão ao backend por hidden inputs.
    function criarItemRecebimento(r, idx) {
        const forma = formaPorId(r.formaId);
        const parcelas = parseInt(r.parcelasCartao) || 0;
        const badgeParc = parcelas > 1
            ? '<span class="badge bg-soft-primary text-primary ms-2">' + parcelas + 'x no cartão</span>'
            : '';
        const wrap = document.createElement('div');
        wrap.className = 'receb-linha border rounded p-3 mb-2';
        wrap.dataset.recebIdx = idx;
        wrap.innerHTML =
            '<div class="d-flex align-items-center justify-content-between gap-3">' +
                '<div class="flex-grow-1" style="min-width:0;">' +
                    '<div class="fw-semibold text-dark">' + escaparHtml(r.formaNome || (forma ? forma.nome : '')) + badgeParc + '</div>' +
                    '<div class="fs-13 text-muted">R$ ' + formatarBR(parseFloat(r.valor) || 0) + '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-light text-danger receb-remover flex-shrink-0"><i class="feather-trash-2 me-1"></i>Excluir</button>' +
                '<input type="hidden" name="recebimentos[' + idx + '][forma_pagamento_id]" value="' + escaparHtml(r.formaId || '') + '">' +
                '<input type="hidden" name="recebimentos[' + idx + '][valor]" value="' + escaparHtml(r.valor || '') + '">' +
                (r.parcelasCartao ? '<input type="hidden" name="recebimentos[' + idx + '][parcelas_cartao]" value="' + escaparHtml(r.parcelasCartao) + '">' : '') +
            '</div>';

        wrap.querySelector('.receb-remover').addEventListener('click', function () {
            recebimentos.splice(idx, 1);
            renderRecebimentos();
        });

        return wrap;
    }

    function renderRecebimentos() {
        recebimentosLista.innerHTML = '';
        recebimentos.forEach(function (r, idx) {
            recebimentosLista.appendChild(criarItemRecebimento(r, idx));
        });
        if (recebVazio) recebVazio.style.display = recebimentos.length ? 'none' : '';
        sincronizarModoPagamento();
        recalcularRecebimentos();
        atualizarPreviewCarne();
    }

    // Ajusta a UI ao modo derivado das formas: crediário (a prazo, single-line,
    // mostra o carnê e esconde a toolbar) x imediato (split via toolbar).
    function sincronizarModoPagamento() {
        const formaCred = linhaCrediario();
        const modoCred = !!formaCred;

        crediarioCampos.style.display = modoCred ? '' : 'none';
        crediarioCampos.querySelectorAll('input, select').forEach(function (el) { el.disabled = !modoCred; });
        previewCarneCard.style.display = modoCred ? '' : 'none';
        if (recebToolbar) recebToolbar.style.display = modoCred ? 'none' : '';

        numeroParcelasInput.max = (modoCred && formaCred.max_parcelas) ? formaCred.max_parcelas : 24;
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
        if (!ehModoCrediario()) {
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

    // Quando o total da venda muda (valor das etapas ou carrinho), reavalia o card,
    // atualiza o resumo e o valor sugerido; no crediário mantém o item = total.
    function aoMudarTotalVenda() {
        atualizarHabilitacaoPagamento();
        if (cardPagamento.classList.contains('opacity-50')) return;
        if (ehModoCrediario() && recebimentos[0]) {
            recebimentos[0].valor = (obterTotalVenda() || 0).toFixed(2);
            renderRecebimentos();
        } else {
            recalcularRecebimentos();
        }
    }

    numeroParcelasInput.addEventListener('input', atualizarPreviewCarne);
    primeiroVencimento.addEventListener('change', atualizarPreviewCarne);
    mesReferencia.addEventListener('change', atualizarPreviewCarne);
    const valorTotalEl = document.getElementById('valorTotal');
    if (valorTotalEl) valorTotalEl.addEventListener('input', aoMudarTotalVenda);
    const carrinhoTotalObs = document.getElementById('carrinhoTotal');
    if (carrinhoTotalObs) {
        new MutationObserver(aoMudarTotalVenda).observe(carrinhoTotalObs, { childList: true, characterData: true, subtree: true });
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
            if (temEtapasDuplicadas) return { pronto: false, motivo: 'Há sessões repetidas (mesma data e horário). Ajuste as datas destacadas antes de salvar.' };
        }
        return { pronto: true, motivo: '' };
    }

    // Coleta TODAS as pendencias que impedem salvar, com rotulo amigavel e o
    // elemento a destacar/focar — usado no submit para dizer exatamente o que falta.
    function coletarPendencias() {
        const pend = [];
        const add = (el, label) => pend.push({ el: el || null, label });
        const tipo = tipoVendaInput.value;

        if (tipo === 'produto') {
            if (carrinhoItens.length === 0) {
                add(document.getElementById('produtoSearch'), 'Adicione ao menos 1 produto ao carrinho');
            }
        } else {
            if (!document.getElementById('clienteHidden').value) {
                add(document.getElementById('clienteSearch'), 'Selecione o cliente');
            }
            if (!servicoSelecionado) {
                add(document.getElementById('servicoSearch'), 'Selecione o serviço');
            }
            if (!document.getElementById('atendenteSelect').value) {
                add(document.getElementById('atendenteSelect'), 'Selecione o atendente');
            }
            if (servicoSelecionado) {
                const tipoServ = (typeof servicoSelecionado.tipo === 'object' && servicoSelecionado.tipo !== null)
                    ? (servicoSelecionado.tipo.value || servicoSelecionado.tipo)
                    : servicoSelecionado.tipo;
                if (tipoServ === 'etapas') {
                    if (!document.getElementById('horario').value) add(document.getElementById('horario'), 'Informe o horário das sessões');
                    if ((parseFloat(document.getElementById('valorTotal').value) || 0) <= 0) add(document.getElementById('valorTotal'), 'Informe o valor total');
                    if (sessoesTbody.querySelectorAll('tr').length === 0) add(document.getElementById('btnGerarPreview'), 'Gere o preview das etapas para definir as datas');
                    if (temEtapasDuplicadas) add(sessoesTbody, 'Há sessões repetidas (mesma data e horário)');
                } else {
                    if (!document.getElementById('dataUnico').value) add(document.getElementById('dataUnico'), 'Informe a data do atendimento');
                    if (!document.getElementById('horarioUnico').value) add(document.getElementById('horarioUnico'), 'Informe o horário');
                }
            }
        }

        // Recebimentos: so cobra quando o card ja esta habilitado (dados basicos ok).
        if (!cardPagamento.classList.contains('opacity-50')) {
            if (!mesReferencia.value) add(mesReferencia, 'Informe a competência (mês de referência)');

            if (recebimentos.length === 0) {
                add(recebFormaBusca, 'Adicione ao menos uma forma de recebimento');
            } else if (ehModoCrediario()) {
                if (!(parseInt(numeroParcelasInput.value) >= 2)) add(numeroParcelasInput, 'Informe o número de parcelas (mínimo 2)');
                if (!primeiroVencimento.value) add(primeiroVencimento, 'Informe o primeiro vencimento');
                if (formaRecebPrazoSelect && !formaRecebPrazoSelect.value) add(formaRecebPrazoSelect, 'Selecione a forma de recebimento');
                const totalCred = obterTotalVenda();
                if (totalCred > 0 && Math.abs(somaCarneAtual() - totalCred) > 0.01) {
                    add(carneTbody, 'A soma das parcelas deve ser igual ao total da venda');
                }
            } else {
                const total = obterTotalVenda();
                const soma = somaRecebimentos();
                if (total > 0 && Math.abs(soma - total) > 0.01) {
                    add(recebimentosLista, 'A soma dos recebimentos (R$ ' + formatarBR(soma) + ') deve bater com o total da venda (R$ ' + formatarBR(total) + ')');
                }
            }
        }

        return pend;
    }

    const PAGAMENTO_DEFAULTS = {
        numeroParcelas: '2',
        primeiroVencimento: cfg.pagamentoDefaults.primeiroVencimento,
        mesReferencia: cfg.pagamentoDefaults.mesReferencia,
    };

    function resetarPagamentoDefault() {
        recebimentos = [];
        limparToolbar();
        numeroParcelasInput.value = PAGAMENTO_DEFAULTS.numeroParcelas;
        primeiroVencimento.value = PAGAMENTO_DEFAULTS.primeiroVencimento;
        mesReferencia.value = PAGAMENTO_DEFAULTS.mesReferencia;
        if (formaRecebPrazoSelect) formaRecebPrazoSelect.value = 'carne';
        valorPorParcelaHint.textContent = '';
        previewCarneBadge.textContent = '';
        previewCarneInfo.textContent = 'Simulação das parcelas que serão geradas ao salvar.';
        carneTbody.innerHTML = '';
        carneTotalFoot.textContent = 'R$ 0,00';
        renderRecebimentos();
    }

    let ultimoEstadoPronto = null;

    function atualizarHabilitacaoPagamento() {
        const { pronto, motivo } = obterEstadoVenda();

        // Reset só na transição pronto → não pronto, pra não apagar old() após erro de validação
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
            sincronizarModoPagamento();
            recalcularRecebimentos();
            atualizarPreviewCarne();
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

    // Repopula os recebimentos a partir do old() (após erro de validação).
    if (Array.isArray(cfg.recebimentosOld) && cfg.recebimentosOld.length) {
        recebimentos = cfg.recebimentosOld.map(function (o) {
            const forma = formaPorId(o.forma_pagamento_id);
            return {
                formaId: o.forma_pagamento_id ? String(o.forma_pagamento_id) : '',
                formaNome: forma ? forma.nome : '',
                valor: (o.valor !== undefined && o.valor !== null) ? String(o.valor) : '',
                parcelasCartao: (o.parcelas_cartao !== undefined && o.parcelas_cartao !== null) ? String(o.parcelas_cartao) : '',
            };
        });
    }

    renderRecebimentos();
    atualizarHabilitacaoPagamento();

    // Bloqueia o submit e lista TUDO que falta, destacando e rolando ate o 1o campo.
    const formNovaVenda = document.getElementById('formNovaVenda');

    formNovaVenda.addEventListener('submit', function(e) {
        formNovaVenda.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        const pendencias = coletarPendencias();
        if (pendencias.length === 0) return; // tudo certo — deixa enviar

        e.preventDefault();
        pendencias.forEach(function(p) {
            if (p.el && p.el.classList) p.el.classList.add('is-invalid');
        });

        const primeiro = pendencias[0].el;
        if (primeiro) {
            primeiro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof primeiro.focus === 'function') {
                try { primeiro.focus({ preventScroll: true }); } catch (_) { primeiro.focus(); }
            }
        }

        const itens = pendencias.map(function(p) {
            const d = document.createElement('div');
            d.textContent = p.label;
            return '<li>' + d.innerHTML + '</li>';
        }).join('');
        window.Swal.fire({
            icon: 'warning',
            title: 'Faltam dados para concluir a venda',
            html: '<ul style="text-align:left;margin:0;padding-left:1.2rem;line-height:1.7;">' + itens + '</ul>',
            confirmButtonColor: '#3454d1',
        });
    });

    // Limpa o destaque de erro assim que o usuario corrige o campo.
    formNovaVenda.addEventListener('input', function(e) {
        if (e.target && e.target.classList) e.target.classList.remove('is-invalid');
    });
    formNovaVenda.addEventListener('change', function(e) {
        if (e.target && e.target.classList) e.target.classList.remove('is-invalid');
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
        if (input) { input.value = ''; input.disabled = false; }
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

        // Trava o campo de busca enquanto há cliente selecionado (evita troca acidental)
        var clienteInput = document.getElementById('clienteSearch');
        if (clienteInput) clienteInput.disabled = true;

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
