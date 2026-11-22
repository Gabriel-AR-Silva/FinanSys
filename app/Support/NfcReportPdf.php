<?php

namespace App\Support;

class NfcReportPdf
{
    private const LABELS = [
        'sales' => 'Valor vendido',
        'received' => 'Recebido',
        'pending' => 'Saldo a receber',
        'cost' => 'Custo de fabricação informado',
        'loss' => 'Perdas estimadas informadas',
        'result' => 'Diferença entre vendas e custos',
        'sold' => 'Plaquinhas vendidas',
        'manufactured' => 'Plaquinhas fabricadas',
        'defective' => 'Plaquinhas defeituosas',
    ];

    public function render(array $report, array $metrics, string $from, string $to, bool $chart): string
    {
        $values = $report['values'];
        $metrics = array_values(array_intersect(array_keys(self::LABELS), $metrics));
        $money = ['sales', 'received', 'pending', 'cost', 'loss', 'result'];
        $format = fn ($value, $key): string => $value === null ? 'Não disponível' : (in_array($key, $money, true)
            ? 'R$ '.number_format($value / 100, 2, ',', '.')
            : number_format($value, 0, ',', '.').' un.');
        $date = fn (string $value): string => implode('/', array_reverse(explode('-', $value)));
        $draw = [];
        $text = function (int $x, int $y, string $value, int $size = 11, bool $bold = false) use (&$draw): void {
            $encoded = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
            $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
            $font = $bold ? 'F2' : 'F1';
            $draw[] = "0.12 0.17 0.24 rg BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm ({$escaped}) Tj ET";
        };
        $line = function (int $y) use (&$draw): void {
            $draw[] = "0.65 0.69 0.73 RG 0.6 w 50 {$y} m 545 {$y} l S";
        };
        $text(50, 790, 'PLAQUINHAS NFC  /  RELATÓRIO GERENCIAL', 10, true);
        $text(50, 756, 'Desempenho do negócio', 25, true);
        $text(50, 733, 'Período: '.$date($from).' a '.$date($to).'  |  Moeda: BRL', 11);
        $line(718);
        $text(50, 694, 'Tabela 1 – Indicadores selecionados', 13, true);
        $line(681);
        $text(50, 666, 'Indicador', 10, true);
        $text(378, 666, 'Valor / quantidade', 10, true);
        $line(657);
        $y = 639;
        foreach ($metrics as $key) {
            $text(50, $y, self::LABELS[$key]);
            $text(378, $y, $format($values[$key], $key), 11, true);
            $y -= 21;
        }
        if (in_array('result', $metrics, true)) {
            $text(50, $y, 'Margem gerencial sobre vendas');
            $text(378, $y, $report['margin'] === null ? 'Não disponível' : number_format($report['margin'], 1, ',', '.').'%', 11, true);
            $y -= 21;
        }
        $line($y + 9);
        $text(50, $y - 8, 'Fonte: registros do módulo Plaquinhas NFC.', 9);
        $y -= 34;
        $notes = [];
        if (in_array('pending', $metrics, true)) {
            $notes[] = 'A receber: saldo atual das vendas realizadas no período, não saldo histórico.';
        }
        if (in_array('received', $metrics, true)) {
            $notes[] = 'Recebido: pagamentos pela data de recebimento; sem data: '.$report['undatedPayments'].'.';
        }
        if (array_intersect(['cost', 'manufactured', 'result'], $metrics)) {
            $notes[] = 'Fabricação: '.$report['undatedBatches'].' lote(s) sem data; '.$report['unknownCosts'].' sem custo no período.';
        }
        if (in_array('loss', $metrics, true)) {
            $notes[] = 'Perdas: estimativas atuais por data da venda; '.$report['unknownLosses'].' defeito(s) sem valor.';
        }
        if (in_array('result', $metrics, true)) {
            $notes[] = 'Diferença = vendas menos custos dos lotes; margem = diferença / vendas.';
            $notes[] = 'Não é lucro contábil: não há apropriação de estoque nem despesas completas.';
            $notes[] = 'Perdas estimadas são separadas para evitar dupla contagem.';
        }
        foreach ($notes as $note) {
            $text(50, $y, $note, 9);
            $y -= 13;
        }

        if ($chart) {
            $drawChart = function (array $keys, string $title) use (&$draw, &$y, $values, $text): void {
                if (count($keys) < 2) {
                    return;
                }
                $y -= 18;
                $text(50, $y, $title, 11, true);
                $y -= 16;
                $max = max(1, ...array_map(fn ($key) => $values[$key] ?? 0, $keys));
                foreach ($keys as $key) {
                    $text(50, $y, self::LABELS[$key], 9);
                    $width = (int) round(270 * max(0, $values[$key] ?? 0) / $max);
                    if ($width > 0) {
                        $bottom = $y - 2;
                        $draw[] = "0.19 0.36 0.50 rg 260 {$bottom} {$width} 8 re f";
                    }
                    $y -= 15;
                }
            };
            $drawChart(array_values(array_intersect(['sales', 'received', 'pending', 'cost', 'loss'], $metrics)), 'Figura 1 – Valores monetários (escala comum em R$)');
            $drawChart(array_values(array_intersect(['sold', 'manufactured', 'defective'], $metrics)), 'Comparação de quantidades (unidades)');
            $text(50, $y - 5, 'Fonte: indicadores selecionados na Tabela 1.', 9);
        }
        $line(48);
        $text(50, 32, 'Relatório gerencial  |  Uso interno', 9);
        $text(510, 32, '1 / 1', 9);

        $stream = implode("\n", $draw)."\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $id = $index + 1;
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
