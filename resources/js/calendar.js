import Calendar from '@toast-ui/calendar';
import '@toast-ui/calendar/dist/toastui-calendar.min.css';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const eventsUrl = calendarEl.dataset.eventsUrl;
    const criarUrl = calendarEl.dataset.criarUrl;
    const reagendarTemplate = calendarEl.dataset.reagendarTemplate;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

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
        const ev = info.event;
        const props = ev.raw || {};
        const inicio = new Date(ev.start).toLocaleString('pt-BR');
        const fim = new Date(ev.end).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        let botoes = '';
        if (props.status === 'agendado') {
            botoes += `<a href="${props.confirmar_url}" class="btn btn-sm btn-primary me-1">Confirmar</a>`;
            botoes += `<a href="${props.finalizar_url}" class="btn btn-sm btn-success me-1">Finalizar</a>`;
            botoes += `<a href="${props.cancelar_url}" class="btn btn-sm btn-danger">Cancelar</a>`;
        } else if (props.status === 'confirmado') {
            botoes += `<a href="${props.finalizar_url}" class="btn btn-sm btn-success me-1">Finalizar</a>`;
            botoes += `<a href="${props.cancelar_url}" class="btn btn-sm btn-danger">Cancelar</a>`;
        }

        Swal.fire({
            title: ev.title,
            html: `
                <div class="text-start">
                    <p><strong>Cliente:</strong> ${props.cliente || '-'}</p>
                    <p><strong>Serviço:</strong> ${props.servico || '-'}</p>
                    <p><strong>Atendente:</strong> ${props.atendente || '-'}</p>
                    <p><strong>Início:</strong> ${inicio}</p>
                    <p><strong>Fim:</strong> ${fim}</p>
                    <p><strong>Status:</strong> <span class="badge bg-secondary">${props.status_label || props.status}</span></p>
                    ${props.observacoes ? `<p><strong>Obs:</strong> ${props.observacoes}</p>` : ''}
                    ${botoes ? `<hr><div class="text-center">${botoes}</div>` : ''}
                </div>
            `,
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#6c757d',
            width: 500,
        });
    });

    calendar.render();
    atualizarRange();
    carregarEventos();
});
