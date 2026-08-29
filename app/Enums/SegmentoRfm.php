<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Segmentos RFM (Recencia, Frequencia, Valor) — classificacao classica de varejo.
 *
 * Quem decide o segmento e o SQL, nao o modelo de IA: a inteligencia mora aqui, e a IA
 * so nomeia, explica e sugere acao em cima do que ja foi classificado.
 */
enum SegmentoRfm: string
{
    case Campeao = 'campeao';
    case Fiel = 'fiel';
    case Novo = 'novo';
    case EmRisco = 'em_risco';
    case Sumido = 'sumido';
    case Neutro = 'neutro';

    public function label(): string
    {
        return match ($this) {
            self::Campeao => 'Campeões',
            self::Fiel => 'Fiéis',
            self::Novo => 'Novos',
            self::EmRisco => 'Em risco',
            self::Sumido => 'Sumidos',
            self::Neutro => 'Ocasionais',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Campeao => 'Compram muito e compraram há pouco tempo.',
            self::Fiel => 'Compram com regularidade e seguem ativos.',
            self::Novo => 'Compraram uma vez, recentemente.',
            self::EmRisco => 'Eram frequentes e pararam de aparecer.',
            self::Sumido => 'Sem compras há bastante tempo.',
            self::Neutro => 'Compram de vez em quando, sem padrão claro.',
        };
    }

    /** Classe de cor do badge Duralux (bg-{cor}). */
    public function cor(): string
    {
        return match ($this) {
            self::Campeao => 'success',
            self::Fiel => 'primary',
            self::Novo => 'info',
            self::EmRisco => 'warning',
            self::Sumido => 'danger',
            self::Neutro => 'secondary',
        };
    }

    /** Ordem de exibicao: do mais valioso ao mais frio. */
    public static function ordenados(): array
    {
        return [self::Campeao, self::Fiel, self::Novo, self::Neutro, self::EmRisco, self::Sumido];
    }
}
