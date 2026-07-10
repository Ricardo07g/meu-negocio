<?php

declare(strict_types=1);

namespace App\Modules\Arquivo\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $rede_id
 * @property int|null $empresa_id
 * @property string $anexavel_type
 * @property int $anexavel_id
 * @property string $colecao
 * @property string $disco
 * @property string $caminho
 * @property string|null $caminho_thumb
 * @property string $nome_original
 * @property string $extensao
 * @property string $mime
 * @property int $tamanho
 * @property int|null $largura
 * @property int|null $altura
 * @property string|null $hash
 * @property int $ordem
 * @property bool $principal
 * @property array<string, mixed>|null $metadados
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url
 * @property-read string|null $thumb_url
 */
class Arquivo extends BaseModel
{
    protected $table = 'arquivos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'colecao',
        'disco',
        'caminho',
        'caminho_thumb',
        'nome_original',
        'extensao',
        'mime',
        'tamanho',
        'largura',
        'altura',
        'hash',
        'ordem',
        'principal',
        'metadados',
    ];

    protected $appends = ['url', 'thumb_url'];

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
            'largura' => 'integer',
            'altura' => 'integer',
            'ordem' => 'integer',
            'principal' => 'boolean',
            'metadados' => 'array',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function anexavel(): MorphTo
    {
        return $this->morphTo();
    }

    // █████╗  ██████╗ ██████╗███████╗███████╗███████╗ ██████╗ ██████╗ ███████╗
    // ██╔══██╗██╔════╝██╔════╝██╔════╝██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝
    // ███████║██║     ██║     █████╗  ███████╗███████╗██║   ██║██████╔╝███████╗
    // ██╔══██║██║     ██║     ██╔══╝  ╚════██║╚════██║██║   ██║██╔══██╗╚════██║
    // ██║  ██║╚██████╗╚██████╗███████╗███████║███████║╚██████╔╝██║  ██║███████║
    // ╚═╝  ╚═╝ ╚═════╝ ╚═════╝╚══════╝╚══════╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝

    public function ehImagem(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disco)->url($this->caminho);
    }

    public function getThumbUrlAttribute(): ?string
    {
        $caminho = $this->caminho_thumb ?: ($this->ehImagem() ? $this->caminho : null);

        return $caminho ? Storage::disk($this->disco)->url($caminho) : null;
    }
}
