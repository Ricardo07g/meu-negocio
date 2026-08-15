import Calendar from '@toast-ui/calendar';
import '@toast-ui/calendar/dist/toastui-calendar.min.css';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const eventsUrl = calendarEl.dataset.eventsUrl;
    const criarUrl = calendarEl.dataset.criarUrl;
    const reagendarTemplate = calendarEl.dataset.reagendarTemplate;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    let motivosSemCobranca = [];
    try {
        motivosSemCobranca = JSON.parse(calendarEl.dataset.motivosSemCobranca || '[]');
    } catch (e) {
        console.error('Motivos de não cobrança inválidos:', e);
    }

    let atendenteCalendars = [];
    let eventosCache = [];

    const calendar = new Calendar(calendarEl, {
        defaultView: 'week',
        isReadOnly: false,
        useFormPopup: false,
        useDetailPopup: false,
        week: {
            startDayOfWeek: 1,
            hourStart: 8,
            hourEnd: 21,
            taskView: false,
            eventView: ['time'],
        },
        month: {
            startDayOfWeek: 1,
        },
        template: {
            time(event) {
                const start = new Date(event.start).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                return `<strong>${start}</strong> ${event.title}`;
            },
        },
    });

    // ——— Carregar eventos ———
    async function carregarEventos() {
        const inicio = calendar.getDateRangeStart().toDate();
        const fim = calendar.getDateRangeEnd().toDate();
        const url = `${eventsUrl}?start=${inicio.toISOString()}&end=${fim.toISOString()}`;

        try {
            const resp = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            atendenteCalendars = data.calendars || [];
            eventosCache = data.events || [];
            calendar.setCalendars(atendenteCalendars);
            renderEventos();
            renderFiltrosAtendente();
        } catch (e) {
            console.error('Erro ao carregar agendamentos:', e);
        }
    }

    function renderEventos() {
        const statusAtivos = Array.from(document.querySelectorAll('.filtro-status:checked')).map((c) => c.value);
        const visiveis = statusAtivos.length
            ? eventosCache.filter((ev) => statusAtivos.includes(ev.raw?.status))
            : eventosCache;
        calendar.clear();
        calendar.createEvents(visiveis);
    }

    // ——— Sidebar: filtros de atendente ———
    function renderFiltrosAtendente() {
        const container = document.getElementById('filtros-atendentes');
        if (!container) return;

        container.innerHTML = atendenteCalendars.map((c) => `
            <div class="form-check d-flex align-items-center gap-2 py-1">
                <input class="form-check-input filtro-atendente" type="checkbox"
                       id="filtro-at-${c.id}" value="${c.id}" checked>
                <label class="form-check-label d-flex align-items-center flex-grow-1" for="filtro-at-${c.id}">
                    <span class="d-inline-block rounded-circle me-2"
                          style="width:12px;height:12px;background:${c.backgroundColor};"></span>
                    ${c.name}
                </label>
            </div>
        `).join('');

        container.querySelectorAll('.filtro-atendente').forEach((cb) => {
            cb.addEventListener('change', () => {
                calendar.setCalendarVisibility(cb.value, cb.checked);
            });
        });

        const todos = document.getElementById('filtro-todos');
        if (todos) {
            todos.onchange = () => {
                container.querySelectorAll('.filtro-atendente').forEach((cb) => {
                    cb.checked = todos.checked;
                    calendar.setCalendarVisibility(cb.value, todos.checked);
                });
            };
        }
    }

    // ——— Sidebar: filtros de status ———
    document.querySelectorAll('.filtro-status').forEach((cb) => {
        cb.addEventListener('change', renderEventos);
    });

    // ——— Toolbar ———
    document.getElementById('cal-prev')?.addEventListener('click', () => { calendar.prev(); atualizarRange(); carregarEventos(); });
    document.getElementById('cal-next')?.addEventListener('click', () => { calendar.next(); atualizarRange(); carregarEventos(); });
    document.getElementById('cal-today')?.addEventListener('click', () => { calendar.today(); atualizarRange(); carregarEventos(); });
    document.querySelectorAll('[data-view]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const view = btn.dataset.view;
            calendar.changeView(view);
            document.querySelectorAll('[data-view]').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            atualizarRange();
            carregarEventos();
        });
    });

    function atualizarRange() {
        const range = document.getElementById('cal-range');
        if (!range) return;
        const ini = calendar.getDateRangeStart().toDate();
        const fim = calendar.getDateRangeEnd().toDate();
        const fmt = (d) => d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
        range.textContent = `${fmt(ini)} – ${fmt(fim)}`;
    }

    // ——— Criar evento ao selecionar no calendário ———
    calendar.on('selectDateTime', (info) => {
        const inicio = info.start instanceof Date ? info.start : new Date(info.start);
        abrirModalCriar(inicio);
        calendar.clearGridSelections();
    });

    function abrirModalCriar(inicio) {
        const modalEl = document.getElementById('modalNovoAgendamento');
        if (!modalEl) return;

        const form = modalEl.querySelector('form');
        form.reset();
        const pad = (n) => String(n).padStart(2, '0');
        const iso = `${inicio.getFullYear()}-${pad(inicio.getMonth() + 1)}-${pad(inicio.getDate())}T${pad(inicio.getHours())}:${pad(inicio.getMinutes())}`;
        form.querySelector('[name="inicio"]').value = iso;

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        form.onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            try {
                const resp = await fetch(criarUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'Erro ao criar');
                }
                modal.hide();
                carregarEventos();
            } catch (err) {
                alert(err.message);
            }
        };
    }

    // ——— Drag-drop reagendar ———
    calendar.on('beforeUpdateEvent', async (info) => {
        const { event, changes } = info;
        if (!changes || (!changes.start && !changes.end)) return;

        const novoInicio = changes.start ? changes.start.toDate() : event.start.toDate();
        const novoFim = changes.end ? changes.end.toDate() : event.end.toDate();
        const url = reagendarTemplate.replace('__ID__', event.id);

        try {
            const resp = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    inicio: toLocalIso(novoInicio),
                    fim: toLocalIso(novoFim),
                }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'Erro ao reagendar');
            }
            calendar.updateEvent(event.id, event.calendarId, changes);
        } catch (err) {
            alert(err.message);
        }
    });

    function toLocalIso(d) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
    }

    // ——— Click em evento: mostra detalhes ———
    calendar.on('clickEvent', (info) => {
        abrirModalDetalhes(info.event);
    });

    function abrirModalDetalhes(ev) {
        const props = ev.raw || {};
        const inicioDate = new Date(ev.start);
        const fimDate = new Date(ev.end);
        const dataTexto = inicioDate.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        const horaInicio = inicioDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        const horaFim = fimDate.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        const corStatus = {
            agendado: 'info',
            confirmado: 'primary',
            finalizado: 'success',
            cancelado: 'danger',
        }[props.status] || 'secondary';

        // O desfecho financeiro do atendimento e explicito: quem ja tem titulo
        // so finaliza; quem nao tem escolhe entre cobrar ou registrar por que
        // nao cobrou. "Finalizar" mudo era o que fazia atendimento virar
        // historico sem nunca virar receita.
        const jaCobrado = props.situacao === 'a_receber' || props.situacao === 'pago';
        const emAberto = props.status === 'agendado' || props.status === 'confirmado';

        let actionsHtml = '';
        if (emAberto) {
            const botoes = [];

            if (props.status === 'agendado') {
                botoes.push('<button type="button" class="btn btn-primary" data-acao="confirmar"><i class="feather-check-circle me-1"></i>Confirmar</button>');
            }

            if (jaCobrado) {
                botoes.push('<button type="button" class="btn btn-success" data-acao="finalizar"><i class="feather-flag me-1"></i>Finalizar</button>');
            } else {
                botoes.push('<button type="button" class="btn btn-success" data-acao="cobrar"><i class="feather-dollar-sign me-1"></i>Finalizar e cobrar</button>');
                botoes.push('<button type="button" class="btn btn-outline-secondary" data-acao="finalizar-sem-cobrar"><i class="feather-gift me-1"></i>Finalizar sem cobrar</button>');
            }

            botoes.push('<button type="button" class="btn btn-warning" data-acao="reagendar"><i class="feather-calendar me-1"></i>Reagendar</button>');
            botoes.push('<button type="button" class="btn btn-outline-danger" data-acao="cancelar"><i class="feather-x-circle me-1"></i>Cancelar</button>');

            actionsHtml = botoes.join('');
        }

        Swal.fire({
            title: ev.title,
            customClass: { popup: 'swal-agenda' },
            width: 520,
            showConfirmButton: false,
            showCloseButton: true,
            html: `
                <div class="swal-status mb-3">
                    <span class="swal-status-pill bg-soft-${corStatus} text-${corStatus}">
                        <span class="dot bg-${corStatus}"></span>${props.status_label || props.status}
                    </span>
                    <span class="swal-status-pill bg-soft-${props.situacao_cor || 'secondary'} text-${props.situacao_cor || 'secondary'} ms-2">
                        <span class="dot bg-${props.situacao_cor || 'secondary'}"></span>${escapeHtml(props.situacao_label || '—')}
                    </span>
                </div>
                <div class="swal-info">
                    <div class="swal-info-row"><span class="label">Cliente</span><span class="value">${escapeHtml(props.cliente || '-')}</span></div>
                    <div class="swal-info-row"><span class="label">Serviço</span><span class="value">${escapeHtml(props.servico || '-')}</span></div>
                    <div class="swal-info-row"><span class="label">Atendente</span><span class="value">${escapeHtml(props.atendente || '-')}</span></div>
                    <div class="swal-info-row"><span class="label">Data</span><span class="value">${dataTexto}</span></div>
                    <div class="swal-info-row"><span class="label">Horário</span><span class="value">${horaInicio} – ${horaFim}</span></div>
                    <div class="swal-info-row"><span class="label">Valor</span><span class="value">R$ ${formatarValor(props.valor)}</span></div>
                    ${props.motivo_sem_cobranca ? `<div class="swal-info-row"><span class="label">Não cobrado</span><span class="value">${escapeHtml(props.motivo_sem_cobranca)}</span></div>` : ''}
                </div>
                ${props.observacoes ? `<div class="swal-obs"><strong>Observações:</strong> ${escapeHtml(props.observacoes)}</div>` : ''}
                ${actionsHtml ? `<div class="swal-actions">${actionsHtml}</div>` : ''}
            `,
            onOpen: function (popup) {
                popup.querySelectorAll('[data-acao]').forEach((btn) => {
                    btn.addEventListener('click', function () {
                        const acao = this.dataset.acao;
                        if (acao === 'reagendar') {
                            Swal.close();
                            abrirModalReagendar(ev);
                        } else if (acao === 'cancelar') {
                            confirmarCancelamento(props);
                        } else if (acao === 'confirmar') {
                            executarAcao(props.confirmar_url, 'Agendamento confirmado!');
                        } else if (acao === 'finalizar') {
                            executarAcao(props.finalizar_url, 'Agendamento finalizado!');
                        } else if (acao === 'cobrar') {
                            // A cobranca acontece na tela de venda: la mora o
                            // split de formas, o crediario e o preview do carne.
                            window.location.href = props.cobrar_url;
                        } else if (acao === 'finalizar-sem-cobrar') {
                            Swal.close();
                            abrirModalSemCobranca(props);
                        }
                    });
                });
            },
        });
    }

    async function executarAcao(url, msgSucesso, corpo) {
        try {
            const resp = await fetch(url, {
                method: 'PATCH',
                headers: Object.assign(
                    {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    corpo ? { 'Content-Type': 'application/json' } : {},
                ),
                body: corpo ? JSON.stringify(corpo) : undefined,
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'HTTP ' + resp.status);
            }
            Swal.fire({ icon: 'success', title: msgSucesso, timer: 1500, showConfirmButton: false });
            carregarEventos();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro', text: err.message });
        }
    }

    /**
     * Finalizar sem cobrar exige um motivo — o servidor recusa (422) sem ele.
     * A pergunta e o ponto da tela: e o que separa "cortesia" de "esqueci de
     * cobrar", que antes eram o mesmo agendamento finalizado e mudo.
     */
    function abrirModalSemCobranca(props) {
        const opcoes = motivosSemCobranca
            .map((m) => `<option value="${escapeHtml(m.valor)}">${escapeHtml(m.label)}</option>`)
            .join('');

        Swal.fire({
            title: 'Finalizar sem cobrar',
            iconHtml: '<i class="feather-gift" style="font-size:28px;color:#3454d1;"></i>',
            customClass: { popup: 'swal-reagendar' },
            width: 460,
            html: `
                <div class="swal-hint mb-3">O atendimento será encerrado sem gerar cobrança. O motivo fica registrado no histórico.</div>
                <div class="swal-field">
                    <label>Motivo</label>
                    <select id="swal-motivo-sem-cobranca" class="form-control">${opcoes}</select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Finalizar sem cobrar',
            cancelButtonText: 'Voltar',
            confirmButtonColor: '#3454d1',
            focusConfirm: false,
            preConfirm: function () {
                const motivo = document.getElementById('swal-motivo-sem-cobranca').value;
                if (!motivo) {
                    Swal.showValidationMessage('Selecione o motivo.');
                    return false;
                }
                return { motivo_sem_cobranca: motivo };
            },
        }).then(function (result) {
            if (!result.value) return;
            executarAcao(props.finalizar_url, 'Atendimento finalizado sem cobrança.', result.value);
        });
    }

    function formatarValor(valor) {
        const n = parseFloat(valor);
        return Number.isFinite(n) ? n.toFixed(2).replace('.', ',') : '0,00';
    }

    function confirmarCancelamento(props) {
        const jaCobrado = props.situacao === 'a_receber' || props.situacao === 'pago';

        Swal.fire({
            title: 'Cancelar agendamento?',
            text: jaCobrado
                ? 'A cobrança deste atendimento será estornada. Essa ação não pode ser desfeita.'
                : 'Essa ação não pode ser desfeita.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d13b4c',
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Voltar',
        }).then(function (result) {
            if (result.isConfirmed) {
                executarAcao(props.cancelar_url, 'Agendamento cancelado.');
            }
        });
    }

    function abrirModalReagendar(ev) {
        const isoInicio = toLocalIsoInput(new Date(ev.start));
        const isoFim = toLocalIsoInput(new Date(ev.end));

        Swal.fire({
            title: 'Reagendar atendimento',
            iconHtml: '<i class="feather-calendar" style="font-size:28px;color:#3454d1;"></i>',
            customClass: { popup: 'swal-reagendar' },
            width: 460,
            html: `
                <div class="swal-hint mb-3">Defina o novo início e fim. O atendimento será movido no calendário.</div>
                <div class="swal-field">
                    <label>Novo início</label>
                    <input id="swal-reagendar-inicio" type="datetime-local" class="form-control" value="${isoInicio}">
                </div>
                <div class="swal-field">
                    <label>Novo fim</label>
                    <input id="swal-reagendar-fim" type="datetime-local" class="form-control" value="${isoFim}">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Reagendar',
            cancelButtonText: 'Voltar',
            confirmButtonColor: '#3454d1',
            focusConfirm: false,
            preConfirm: function () {
                const inicio = document.getElementById('swal-reagendar-inicio').value;
                const fim = document.getElementById('swal-reagendar-fim').value;
                if (!inicio || !fim) {
                    Swal.showValidationMessage('Preencha início e fim.');
                    return false;
                }
                if (new Date(fim) <= new Date(inicio)) {
                    Swal.showValidationMessage('O fim deve ser posterior ao início.');
                    return false;
                }
                return { inicio: inicio + ':00', fim: fim + ':00' };
            },
        }).then(async function (result) {
            if (!result.value) return;
            const url = reagendarTemplate.replace('__ID__', ev.id);
            try {
                const resp = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(result.value),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'Erro ao reagendar');
                }
                Swal.fire({ icon: 'success', title: 'Reagendado!', timer: 1500, showConfirmButton: false });
                carregarEventos();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Erro', text: err.message });
            }
        });
    }

    function toLocalIsoInput(d) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    calendar.render();
    atualizarRange();
    carregarEventos();
});
