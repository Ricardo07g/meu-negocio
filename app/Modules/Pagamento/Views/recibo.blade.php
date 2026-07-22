<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante Recebimento #{{ $pagamento->id }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #212529; margin: 0; }
        .empresa { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 18px; }
        .empresa-nome { font-size: 18pt; font-weight: bold; }
        .empresa-info { font-size: 10pt; color: #555; margin-top: 4px; }
        .recibo-title { text-align: center; font-size: 14pt; font-weight: bold; letter-spacing: 2px; margin: 10px 0 18px; padding: 6px; border: 1px solid #333; }
        .section-title { font-size: 11pt; font-weight: bold; background: #eee; padding: 5px 8px; margin: 14px 0 8px; border-left: 3px solid #333; }
        .dados { width: 100%; border-collapse: collapse; }
        .dados td { padding: 3px 0; font-size: 10pt; vertical-align: top; }
        .dados .label { color: #666; width: 130px; }
        .valores { width: 100%; margin-top: 10px; }
        .valores td { padding: 4px 8px; font-size: 10pt; }
        .valores .label { text-align: right; color: #555; }
        .valores .valor { text-align: right; width: 140px; }
        .valores .total td { border-top: 2px solid #333; font-weight: bold; font-size: 12pt; padding-top: 8px; }
        .pago { color: #27ae60; }
        .pendente { color: #c0392b; }
        table.baixas { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.baixas thead th { background: #333; color: white; padding: 6px 8px; font-size: 10pt; text-align: left; }
        table.baixas tbody td { padding: 6px 8px; font-size: 10pt; border-bottom: 1px solid #ddd; }
        .text-end { text-align: right; }
        .assinatura { margin-top: 50px; text-align: center; font-size: 10pt; }
        .assinatura .linha { border-top: 1px solid #333; width: 60%; margin: 0 auto 4px; padding-top: 4px; }
        .footer { margin-top: 30px; text-align: center; font-size: 8pt; color: #999; }
    </style>
</head>
<body>

@php
    $valorPago = $pagamento->valorPago();
    $totalRecebido = $pagamento->totalRecebidoLiquido();
    $saldo = $pagamento->saldoRestante();
    $status = $pagamento->status->value;
    $origem = $pagamento->agendamento ? 'Serviço único' : ($pagamento->vendaEtapas ? 'Serviço em etapas' : ($pagamento->vendaProduto ? 'Venda de produto' : 'Outro'));
    $origemDesc = $pagamento->agendamento?->servico?->nome
        ?? $pagamento->vendaEtapas?->servico?->nome
        ?? ($pagamento->vendaProduto ? $pagamento->vendaProduto->itens->pluck('descricao')->implode(', ') : '—');
@endphp

<div class="empresa">
    <div class="empresa-nome">{{ $empresa->nome ?? '—' }}</div>
    <div class="empresa-info">
        @if($empresa->documento ?? null) CNPJ/CPF: {{ $empresa->documento }}<br>@endif
        @if($empresa->telefone ?? null) Telefone: {{ $empresa->telefone }}@endif
        @if(($empresa->telefone ?? null) && ($empresa->email ?? null)) &nbsp;|&nbsp;@endif
        @if($empresa->email ?? null) E-mail: {{ $empresa->email }}@endif
    </div>
</div>

<div class="recibo-title">COMPROVANTE DE RECEBIMENTO Nº {{ str_pad($pagamento->id, 6, '0', STR_PAD_LEFT) }}</div>

<div class="section-title">Dados do recebimento</div>
<table class="dados">
    <tr>
        <td class="label">Data de emissão:</td><td>{{ now()->format('d/m/Y H:i') }}</td>
        <td class="label">Data do lançamento:</td><td>{{ $pagamento->created_at->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td class="label">Status:</td><td class="{{ $status === 'pago' ? 'pago' : 'pendente' }}">{{ ucfirst($status) }}</td>
        <td class="label">Origem:</td><td>{{ $origem }}</td>
    </tr>
    <tr>
        <td class="label">Condição:</td><td>{{ $pagamento->condicao_pagamento->label() }}</td>
        <td class="label">Mês de referência:</td><td>{{ $pagamento->mes_referencia->format('m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Descrição:</td><td colspan="3">{{ $origemDesc }}</td>
    </tr>
</table>

<div class="section-title">Cliente</div>
<table class="dados">
    <tr>
        <td class="label">Nome:</td><td>{{ $pagamento->cliente->nome ?? '—' }}</td>
        <td class="label">CPF:</td><td>{{ $pagamento->cliente->cpf ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Telefone:</td><td>{{ $pagamento->cliente->telefone ?? '—' }}</td>
        <td class="label">E-mail:</td><td>{{ $pagamento->cliente->email ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">Valores</div>
<table class="valores">
    <tr><td class="label">Valor total:</td><td class="valor">R$ {{ number_format((float) $pagamento->valor_total, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Principal quitado:</td><td class="valor">R$ {{ number_format($valorPago, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Recebido líquido (c/ multa, juros, desconto):</td><td class="valor pago">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</td></tr>
    @if($saldo > 0)
        <tr class="total"><td class="label">Saldo devedor:</td><td class="valor pendente">R$ {{ number_format($saldo, 2, ',', '.') }}</td></tr>
    @else
        <tr class="total"><td class="label">TOTAL RECEBIDO:</td><td class="valor pago">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</td></tr>
    @endif
</table>

<div class="section-title">Parcelas</div>
<table class="baixas">
    <thead>
        <tr><th>#</th><th>Vencimento</th><th>Status</th><th>Forma</th><th class="text-end">Valor</th><th class="text-end">Pago</th></tr>
    </thead>
    <tbody>
        @foreach($pagamento->parcelas as $parcela)
            <tr>
                <td>{{ $parcela->numero }}/{{ $parcela->total }}</td>
                <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                <td>{{ $parcela->statusEfetivo()->label() }}</td>
                <td>{{ $parcela->forma_pagamento_nome ?? '—' }}</td>
                <td class="text-end">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                <td class="text-end">R$ {{ number_format($parcela->valor_pago, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@php $baixasRec = $pagamento->parcelas->flatMap->baixas->sortBy('data'); @endphp
@if($baixasRec->count())
    <div class="section-title">Histórico de recebimentos</div>
    <table class="baixas">
        <thead>
            <tr><th>Data</th><th>Forma</th><th>Observação</th><th class="text-end">Valor</th></tr>
        </thead>
        <tbody>
            @foreach($baixasRec as $baixa)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                    <td>{{ $baixa->forma_pagamento_nome ?? '—' }}</td>
                    <td>{{ $baixa->observacao ?? '—' }}</td>
                    <td class="text-end">R$ {{ number_format((float) $baixa->valor, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="assinatura">
    <div class="linha"></div>
    Assinatura do responsável
</div>

<div class="footer">Comprovante gerado em {{ now()->format('d/m/Y H:i') }}</div>

</body>
</html>
