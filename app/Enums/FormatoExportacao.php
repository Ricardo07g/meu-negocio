<?php

declare(strict_types=1);

namespace App\Enums;

enum FormatoExportacao: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
        };
    }

    public function extensao(): string
    {
        return $this->value;
    }

    public function mime(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
