@php
    /** @var \Illuminate\Support\Collection<int, \App\Modules\Tenant\Models\HorarioAtendimento> $expediente */
    $dias = \App\Modules\Tenant\Models\HorarioAtendimento::DIAS;
@endphp

<div class="card stretch stretch-full mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Expediente</h5>
    </div>
    <div class="card-body">
        <p class="text-muted fs-13 mb-3">
            <i class="feather-info me-1"></i>
            A janela em que esta unidade atende. A agenda recusa horário fora dela — quem tem
            permissão de encaixe pode agendar mesmo assim, e o atendimento fica sinalizado.
        </p>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40%;">Dia</th>
                        <th style="width:25%;">Abre</th>
                        <th style="width:25%;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dias as $numero => $nome)
                        @php
                            $linha = $expediente[$numero] ?? null;
                            $padrao = \App\Modules\Tenant\Models\HorarioAtendimento::PADRAO[$numero];
                            $ativo = (bool) old("expediente.{$numero}.ativo", $linha?->ativo ?? $padrao[2]);
                            $inicio = old("expediente.{$numero}.hora_inicio", substr($linha?->hora_inicio ?? $padrao[0], 0, 5));
                            $fim = old("expediente.{$numero}.hora_fim", substr($linha?->hora_fim ?? $padrao[1], 0, 5));
                        @endphp
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="expediente[{{ $numero }}][ativo]" value="1"
                                           id="expediente-{{ $numero }}" {{ $ativo ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="expediente-{{ $numero }}">{{ $nome }}</label>
                                </div>
                            </td>
                            <td>
                                <input type="time" class="form-control" name="expediente[{{ $numero }}][hora_inicio]" value="{{ $inicio }}">
                            </td>
                            <td>
                                <input type="time" class="form-control" name="expediente[{{ $numero }}][hora_fim]" value="{{ $fim }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
