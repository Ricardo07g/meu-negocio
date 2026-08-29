<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Segmentos RFM (Recencia, Frequencia, Valor) — classificacao classica de varejo.
 *
 * Quem decide o segmento e o SQL, nao o modelo de IA: a inteligencia mora aqui, e a IA
 * so nomeia, explica e sugere acao em cima do que ja foi classificado.
 *
 * Os rotulos sao deliberadamente sobrios. "Campeoes" e "Sumidos" sao os termos de manual
 * de marketing, mas esta tela e um relatorio que o dono do negocio pode imprimir e mostrar
 * ao contador: nomenclatura de apresentacao vale mais que jargao simpatico.
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
            self::Campeao => 'Alto valor',
            self::Fiel => 'Recorrentes',
            self::Novo => 'Recém-conquistados',
            self::EmRisco => 'Em risco',
            self::Sumido => 'Inativos',
            self::Neutro => 'Eventuais',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Campeao => 'Compram com frequência, gastam acima da média e seguem ativos.',
            self::Fiel => 'Retornam com regularidade e mantêm o faturamento previsível.',
            self::Novo => 'Primeira compra recente — ainda não há histórico para avaliar.',
            self::EmRisco => 'Eram frequentes e pararam de retornar. Receita ameaçada.',
            self::Sumido => 'Sem compras há muito tempo. Provavelmente já perdidos.',
            self::Neutro => 'Compram esporadicamente, sem padrão definido.',
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
