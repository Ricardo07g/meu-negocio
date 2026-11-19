{{--
    Armadilha do GuardaBot: honeypot + carimbo de tempo criptografado.

    Vai DENTRO do <form>. Nao renderiza nada com o modo `desligado`.

    O honeypot fica fora da tela (nao `display:none`, que alguns robos pulam),
    fora da ordem de tab, sem autocomplete e escondido de leitor de tela.
--}}
@if (\Ricardo07g\GuardaBot\Modo::atual() !== \Ricardo07g\GuardaBot\Modo::Desligado)
    {{-- id por renderizacao: a mesma tela pode ter mais de um formulario protegido. --}}
    @php($guardaBotId = 'guarda-bot-'.\Illuminate\Support\Str::random(8))
    <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
        <label for="{{ $guardaBotId }}">Deixe este campo em branco</label>
        <input type="text"
               id="{{ $guardaBotId }}"
               name="{{ config('guarda-bot.armadilha.campo') }}"
               value=""
               tabindex="-1"
               autocomplete="off">
    </div>
    <input type="hidden"
           name="{{ config('guarda-bot.armadilha.campo_tempo') }}"
           value="{{ app(\Ricardo07g\GuardaBot\Detectores\Armadilha::class)->carimbo() }}">

    {{-- isset: o componente pode renderizar fora do grupo `web` (sem $errors). --}}
    @if (isset($errors) && $errors->has(\Ricardo07g\GuardaBot\Http\Middleware\ProtegerContraBots::CHAVE_ERRO))
        <div class="text-danger small text-center mb-3">{{ $errors->first(\Ricardo07g\GuardaBot\Http\Middleware\ProtegerContraBots::CHAVE_ERRO) }}</div>
    @endif
@endif
