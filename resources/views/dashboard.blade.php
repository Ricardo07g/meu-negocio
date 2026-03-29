@extends('layouts.app')

@section('titulo', 'Dashboard - Meu Negócio')
@section('titulo-pagina', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Agendamentos Hoje</div>
                            <h5 class="fw-bold mb-0">-</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-primary rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-calendar text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Total Clientes</div>
                            <h5 class="fw-bold mb-0">-</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-success rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-users text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Receita do Mês</div>
                            <h5 class="fw-bold mb-0">-</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-warning rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-dollar-sign text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs-12 text-muted mb-1">Serviços Ativos</div>
                            <h5 class="fw-bold mb-0">-</h5>
                        </div>
                        <div class="wd-40 ht-40 bg-soft-info rounded-circle d-flex align-items-center justify-content-center">
                            <i class="feather-briefcase text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Bem-vindo ao Meu Negócio!</h5>
                </div>
                <div class="card-body">
                    <p>Sistema pronto para uso. Comece cadastrando seus <a href="{{ route('servicos.index') }}">serviços</a> e <a href="{{ route('clientes.index') }}">clientes</a>.</p>
                    <p><strong>Plano atual:</strong> {{ auth()->user()->rede->plano->nome }}</p>
                    <p><strong>Empresa:</strong> {{ auth()->user()->empresa->nome ?? 'N/A' }}</p>
                    <p><strong>Papel:</strong> {{ auth()->user()->getRoleNames()->first() ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
