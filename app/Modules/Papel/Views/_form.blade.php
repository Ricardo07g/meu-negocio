@php
    $papel = $papel ?? null;
    $papelPermissoes = $papelPermissoes ?? [];
    $isAdmin = $papel && $papel->name === 'Admin';
@endphp

<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">{{ $papel ? 'Editar Papel' : 'Cadastrar Papel' }}</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Nome do Papel <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $papel->name ?? '') }}" required {{ $isAdmin ? 'readonly' : '' }}>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <h6 class="fw-bold mb-3">Permissões</h6>

        @foreach($permissoes as $modulo => $perms)
            <div class="mb-4">
                <label class="form-label fw-semibold text-uppercase fs-11 text-muted">{{ $modulo }}</label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($perms as $perm)
                        <div class="form-check">
                            <input type="checkbox" name="permissoes[]" value="{{ $perm->name }}" class="form-check-input"
                                id="perm_{{ $perm->id }}"
                                {{ in_array($perm->name, old('permissoes', $papelPermissoes)) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perm_{{ $perm->id }}">{{ explode('.', $perm->name)[1] ?? $perm->name }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<x-form-botoes :voltar="route('papeis.index')" />
