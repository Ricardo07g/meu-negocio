<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo #{{ $numero }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #212529; margin: 0; }
        h1, h2, h3 { margin: 0; }
        .empresa { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 18px; }
        .empresa-nome { font-size: 18pt; font-weight: bold; }
        .empresa-info { font-size: 10pt; color: #555; margin-top: 4px; }
        .recibo-title { text-align: center; font-size: 14pt; font-weight: bold; letter-spacing: 2px; margin: 10px 0 18px; padding: 6px; border: 1px solid #333; }
        .meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .meta td { padding: 4px 0; font-size: 10pt; }
        .meta .label { color: #666; width: 120px; }
        .section-title { font-size: 11pt; font-weight: bold; background: #eee; padding: 5px 8px; margin: 14px 0 8px; border-left: 3px solid #333; }
        .dados { width: 100%; border-collapse: collapse; }
        .dados td { padding: 3px 0; font-size: 10pt; vertical-align: top; }
        .dados .label { color: #666; width: 110px; }
        table.itens { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.itens thead th { background: #333; color: white; padding: 6px 8px; font-size: 10pt; text-align: left; }
        table.itens tbody td { padding: 6px 8px; font-size: 10pt; border-bottom: 1px solid #ddd; }
        table.itens tbody tr:nth-child(even) td { background: #f7f7f7; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .valores { width: 100%; margin-top: 10px; }
        .valores td { padding: 4px 8px; font-size: 10pt; }
        .valores .label { text-align: right; color: #555; }
        .valores .valor { text-align: right; width: 120px; }
        .valores .total td { border-top: 2px solid #333; font-weight: bold; font-size: 12pt; padding-top: 8px; }
        .desconto { color: #c0392b; }
        .acrescimo { color: #27ae60; }
        .pagamento-block { margin-top: 16px; padding: 10px; border: 1px solid #ddd; background: #fafafa; }
        .baixas { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9pt; }
        .baixas th, .baixas td { padding: 4px 6px; border-bottom: 1px dotted #aaa; text-align: left; }
        .assinatura { margin-top: 50px; text-align: center; font-size: 10pt; }
        .assinatura .linha { border-top: 1px solid #333; width: 60%; margin: 0 auto 4px; padding-top: 4px; }
        .footer { margin-top: 30px; text-align: center; font-size: 8pt; color: #999; }
    </style>
</head>
<body>

<div class="empresa">
    <div class="empresa-nome">{{ $empresa->nome ?? '—' }}</div>
    <div class="empresa-info">
        @if($empresa->documento ?? null) CNPJ/CPF: {{ $empresa->documento }}<br>@endif
        @if($empresa->telefone ?? null) Telefone: {{ $empresa->telefone }}@endif
        @if(($empresa->telefone ?? null) && ($empresa->email ?? null)) &nbsp;|&nbsp;@endif
        @if($empresa->email ?? null) E-mail: {{ $empresa->email }}@endif
    </div>
</div>

<div class="recibo-title">RECIBO Nº {{ str_pad($numero, 6, '0', STR_PAD_LEFT) }}</div>

<table class="meta">
    <tr>
        <td class="label">Data de emissão:</td><td>{{ now()->format('d/m/Y H:i') }}</td>
        <td class="label">Data da venda:</td><td>{{ $dataVenda->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Tipo:</td><td>{{ $tipoLabel }}</td>
        <td class="label">Status:</td><td>{{ $statusLabel }}</td>
    </tr>
</table>

<div class="section-title">Cliente</div>
<table class="dados">
    <tr>
        <td class="label">Nome:</td><td>{{ $cliente->nome ?? '—' }}</td>
        <td class="label">CPF:</td><td>{{ $cliente->cpf ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Telefone:</td><td>{{ $cliente->telefone ?? '—' }}</td>
        <td class="label">E-mail:</td><td>{{ $cliente->email ?? '—' }}</td>
    </tr>
    @if($cliente && ($cliente->logradouro || $cliente->cidade))
        <tr>
            <td class="label">Endereço:</td>
            <td colspan="3">
                {{ $cliente->logradouro }}{{ $cliente->numero ? ', '.$cliente->numero : '' }}{{ $cliente->complemento ? ' - '.$cliente->complemento : '' }}
                @if($cliente->bairro) - {{ $cliente->bairro }} @endif
                @if($cliente->cidade) - {{ $cliente->cidade }}{{ $cliente->estado ? '/'.$cliente->estado : '' }} @endif
                @if($cliente->cep) - CEP {{ $cliente->cep }} @endif
            </td>
        </tr>
    @endif
</table>

@if($atendente)
    <div class="section-title">{{ $atendenteLabel }}</div>
    <table class="dados">
        <tr><td class="label">Nome:</td><td>{{ $atendente->nome }}</td></tr>
    </table>
@endif

<div class="section-title">{{ $tipo === 'produto' ? 'Itens' : 'Serviço / Sessões' }}</div>

@if($tipo === 'produto')
    <table class="itens">
        <thead>
            <tr>
                <th>Produto</th>
                <th class="text-center">Qtd</th>
                <th class="text-end">Unit.</th>
                <th class="text-end">Desc.</th>
                <th class="text-end">Acr.</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($itens as $item)
                <tr>
                    <td>{{ $item->descricao }}</td>
                    <td class="text-center">{{ $item->quantidade }}</td>
                    <td class="text-end">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                    <td class="text-end">{{ $item->desconto > 0 ? '-R$ ' . number_format($item->desconto, 2, ',', '.') : '—' }}</td>
                    <td class="text-end">{{ $item->acrescimo > 0 ? '+R$ ' . number_format($item->acrescimo, 2, ',', '.') : '—' }}</td>
                    <td class="text-end">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif($tipo === 'pacote')
    <table class="dados">
        <tr>
            <td class="label">Serviço:</td><td>{{ $servico->nome ?? '—' }}</td>
            <td class="label">Sessões:</td><td>{{ $qtdSessoes }} ({{ $sessoesRealizadas }} realizadas)</td>
        </tr>
    </table>
    @if($sessoes && $sessoes->count())
        <table class="itens" style="margin-top:10px;">
            <thead>
                <tr>
                    <th>#</th><th>Data</th><th>Horário</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessoes as $i => $ag)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $ag->inicio->format('d/m/Y') }}</td>
                        <td>{{ $ag->inicio->format('H:i') }} - {{ $ag->fim->format('H:i') }}</td>
                        <td>{{ ucfirst($ag->status->value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@else
    <table class="dados">
        <tr>
            <td class="label">Serviço:</td><td>{{ $servico->nome ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Início:</td><td>{{ $inicio ? $inicio->format('d/m/Y H:i') : '—' }}</td>
            <td class="label">Fim:</td><td>{{ $fim ? $fim->format('H:i') : '—' }}</td>
        </tr>
    </table>
@endif

<div class="section-title">Valores</div>
<table class="valores">
    @if($subtotal !== null)
        <tr><td class="label">Subtotal:</td><td class="valor">R$ {{ number_format($subtotal, 2, ',', '.') }}</td></tr>
    @endif
    @if($desconto > 0)
        <tr><td class="label">Desconto:</td><td class="valor desconto">-R$ {{ number_format($desconto, 2, ',', '.') }}</td></tr>
    @endif
    @if($acrescimo > 0)
        <tr><td class="label">Acréscimo:</td><td class="valor acrescimo">+R$ {{ number_format($acrescimo, 2, ',', '.') }}</td></tr>
    @endif
    <tr class="total"><td class="label">TOTAL:</td><td class="valor">R$ {{ number_format($valorTotal, 2, ',', '.') }}</td></tr>
</table>

@if($pagamento)
    <div class="pagamento-block">
        <strong>Pagamento</strong><br>
        Forma: {{ $pagamento->forma_pagamento?->value ? ucfirst($pagamento->forma_pagamento->value) : 'Fiado (a prazo)' }}<br>
        Status: {{ ucfirst($pagamento->status->value) }}<br>
        Pago: R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }} de R$ {{ number_format($pagamento->valor, 2, ',', '.') }}
        @if($pagamento->data_vencimento) <br>Vencimento: {{ $pagamento->data_vencimento->format('d/m/Y') }}@endif

        @if($pagamento->baixas->count())
            <div style="margin-top:8px;"><strong>Histórico de baixas:</strong></div>
            <table class="baixas">
                <thead>
                    <tr><th>Data</th><th>Forma</th><th class="text-end">Valor</th></tr>
                </thead>
                <tbody>
                    @foreach($pagamento->baixas as $baixa)
                        <tr>
                            <td>{{ $baixa->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ ucfirst($baixa->forma_pagamento?->value ?? '—') }}</td>
                            <td class="text-end">R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

<div class="assinatura">
    <div class="linha"></div>
    Assinatura do cliente
</div>

<div class="footer">
    Recibo gerado em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
