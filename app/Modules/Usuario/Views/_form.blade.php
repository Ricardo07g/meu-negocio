@php
    $entidade = $entidade ?? null;
    $empresasMarcadas = old('empresas', $empresasSelecionadas ?? []);
    $papelSelecionado = old('papel', $entidade?->getRoleNames()->first());
    $tituloCard = $entidade ? 'Editar Usuário' : 'Cadastrar Usuário';
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ $tituloCard }}</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $entidade?->nome) }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $entidade?->email) }}" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">
                    Senha
                    @if($entidade)
                        <small class="text-muted">(deixe em branco para manter a atual)</small>
                    @else
                        <span class="text-danger">*</span>
                    @endif
                </label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $entidade ? '' : 'required' }}>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Perfil de Acesso <span class="text-danger">*</span></span>
                    @can('papel.criar')
                        <a href="{{ route('perfis-acesso.create') }}" class="fs-12 text-primary text-decoration-none">
                            <i class="feather-plus me-1"></i>Novo perfil
                        </a>
                    @endcan
                </label>
                <select name="papel" class="form-select @error('papel') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($papeis as $papel)
                        <option value="{{ $papel }}" {{ $papelSelecionado == $papel ? 'selected' : '' }}>{{ $papel }}</option>
                    @endforeach
                </select>
                @if (count($papeis) <= 1)
                    <small class="text-muted d-block mt-1">
                        <i class="feather-info me-1"></i>
                        Apenas o perfil Admin esta disponivel.
                        @can('papel.criar')
                            <a href="{{ route('perfis-acesso.index') }}">Crie perfis customizados</a>
                            (Recepcao, Financeiro, etc) com permissoes especificas.
                        @endcan
                    </small>
                @endif
                @error('papel') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-check">
                    <input type="hidden" name="atende" value="0">
                    <input type="checkbox" name="atende" value="1" class="form-check-input" id="atende" {{ old('atende', $entidade?->atende) ? 'checked' : '' }}>
                    <label class="form-check-label" for="atende">Este usuário realiza atendimentos</label>
                </div>
            </div>
        </div>

        {{-- Empresas com acesso (pivot empresa_usuario). Admin acessa todas automaticamente; nao-admin precisa de >= 1. --}}
        <div class="row" id="bloco-empresas-acesso">
            <div class="col-12">
                <label class="form-label d-block">Empresas com acesso <span class="text-danger" data-papel-required>*</span></label>
                <small class="text-muted d-block mb-2" data-papel-admin-msg style="display:none !important;">Admin acessa todas as empresas da rede automaticamente.</small>
                <div class="row">
                    @foreach($empresas as $empresa)
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="empresas[]" value="{{ $empresa->id }}" class="form-check-input" id="empresa-{{ $empresa->id }}" {{ in_array($empresa->id, $empresasMarcadas) ? 'checked' : '' }}>
                                <label class="form-check-label" for="empresa-{{ $empresa->id }}">{{ $empresa->nome }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('empresas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @error('empresas.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        // Esconde/mostra bloco de empresas conforme papel = Admin (Admin nao precisa de pivot).
        (function () {
            const selectPapel = document.querySelector('select[name="papel"]');
            const bloco = document.getElementById('bloco-empresas-acesso');
            if (!selectPapel || !bloco) return;
            const checkboxes = bloco.querySelectorAll('input[name="empresas[]"]');
            const requiredMark = bloco.querySelector('[data-papel-required]');
            const adminMsg = bloco.querySelector('[data-papel-admin-msg]');
            const grid = bloco.querySelector('.row');
            const aplicar = () => {
                const ehAdmin = selectPapel.value === 'Admin';
                if (ehAdmin) {
                    checkboxes.forEach(cb => { cb.disabled = true; });
                    if (requiredMark) requiredMark.style.display = 'none';
                    if (adminMsg) adminMsg.style.display = 'block';
                    if (grid) grid.style.display = 'none';
                } else {
                    checkboxes.forEach(cb => { cb.disabled = false; });
                    if (requiredMark) requiredMark.style.display = '';
                    if (adminMsg) adminMsg.style.display = 'none';
                    if (grid) grid.style.display = '';
                }
            };
            selectPapel.addEventListener('change', aplicar);
            aplicar();
        })();
    </script>
@endpush
