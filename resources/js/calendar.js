import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const eventsUrl = calendarEl.dataset.eventsUrl;

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        locale: ptBrLocale,
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia',
            list: 'Lista'
        },
        firstDay: 1, // segunda-feira
        nowIndicator: true,
        allDaySlot: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '21:00:00',
        slotDuration: '00:30:00',
        expandRows: true,
        height: 'auto',
        navLinks: true,
        editable: false,
        selectable: false,
        weekends: true,
        events: {
            url: eventsUrl,
            method: 'GET',
            failure: function () {
                console.error('Erro ao carregar agendamentos.');
            }
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps;
            const start = info.event.start;
            const end = info.event.end;

            const horaInicio = start ? start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            const horaFim = end ? end.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            const dataFormatada = start ? start.toLocaleDateString('pt-BR') : '';

            let statusBadge = '';
            switch (props.status) {
                case 'agendado': statusBadge = '<span class="badge bg-info">Agendado</span>'; break;
                case 'confirmado': statusBadge = '<span class="badge bg-primary">Confirmado</span>'; break;
                case 'finalizado': statusBadge = '<span class="badge bg-success">Finalizado</span>'; break;
                case 'cancelado': statusBadge = '<span class="badge bg-danger">Cancelado</span>'; break;
            }

            let botoesHtml = '';
            if (props.status === 'agendado') {
                botoesHtml = '<a href="' + props.confirmar_url + '" class="btn btn-sm btn-primary me-1">Confirmar</a>';
                botoesHtml += '<a href="' + props.finalizar_url + '" class="btn btn-sm btn-success me-1">Finalizar</a>';
                botoesHtml += '<a href="' + props.cancelar_url + '" class="btn btn-sm btn-danger">Cancelar</a>';
            } else if (props.status === 'confirmado') {
                botoesHtml = '<a href="' + props.finalizar_url + '" class="btn btn-sm btn-success me-1">Finalizar</a>';
                botoesHtml += '<a href="' + props.cancelar_url + '" class="btn btn-sm btn-danger">Cancelar</a>';
            }

            Swal.fire({
                title: info.event.title,
                html: '<div class="text-start">' +
                    '<p><strong>Cliente:</strong> ' + (props.cliente || '-') + '</p>' +
                    '<p><strong>Serviço:</strong> ' + (props.servico || '-') + '</p>' +
                    '<p><strong>Atendente:</strong> ' + (props.atendente || '-') + '</p>' +
                    '<p><strong>Data:</strong> ' + dataFormatada + '</p>' +
                    '<p><strong>Horário:</strong> ' + horaInicio + ' - ' + horaFim + '</p>' +
                    '<p><strong>Status:</strong> ' + statusBadge + '</p>' +
                    (props.observacoes ? '<p><strong>Obs:</strong> ' + props.observacoes + '</p>' : '') +
                    (botoesHtml ? '<hr><div class="text-center">' + botoesHtml + '</div>' : '') +
                    '</div>',
                showConfirmButton: true,
                confirmButtonText: 'Fechar',
                confirmButtonColor: '#6c757d',
                width: 500
            });
        },
        eventDidMount: function (info) {
            const status = info.event.extendedProps.status;
            if (status === 'cancelado') {
                info.el.style.opacity = '0.5';
                info.el.style.textDecoration = 'line-through';
            }
            if (status === 'finalizado') {
                info.el.style.opacity = '0.7';
            }
        }
    });

    calendar.render();

    // Filtros por atendente
    document.querySelectorAll('.filtro-atendente').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const atendenteId = this.value;
            const events = calendar.getEvents();
            events.forEach(function (event) {
                if (String(event.extendedProps.atendente_id) === atendenteId) {
                    event.setProp('display', checkbox.checked ? 'auto' : 'none');
                }
            });
        });
    });

    // Toggle todos
    const toggleAll = document.getElementById('filtro-todos');
    if (toggleAll) {
        toggleAll.addEventListener('change', function () {
            const checked = this.checked;
            document.querySelectorAll('.filtro-atendente').forEach(function (cb) {
                cb.checked = checked;
                cb.dispatchEvent(new Event('change'));
            });
        });
    }
});
