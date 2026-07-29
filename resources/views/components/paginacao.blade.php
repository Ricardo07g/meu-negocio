@props(['paginator'])

{{--
    Rodape de paginacao da listagem. O mesmo @if(hasPages) + card-footer +
    onEachSide(1) estava repetido em 8 telas.

    onEachSide(1) mantem a barra curta o bastante para caber no celular.
--}}
@if($paginator->hasPages())
    <div class="card-footer">
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
