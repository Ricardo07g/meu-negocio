@props(['cor', 'label'])
{{-- Badge de status "soft" (bg-soft-{cor} text-{cor}) usado nos cards financeiros e tabelas de parcelas/agendamentos. --}}
<span {{ $attributes->merge(['class' => "badge bg-soft-{$cor} text-{$cor}"]) }}>{{ $label }}</span>
