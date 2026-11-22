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

    public function render(array $report, array $metrics, string $from, string $to, bool $chart, ?string $name = null): string
    {
        $metrics = array_values(array_intersect(array_keys(self::LABELS), $metrics));
        $values = $report['values'];
        $monetary = ['sales', 'received', 'pending', 'cost', 'loss', 'result'];
        $format = fn ($value, string $key): string => $value === null ? 'Não disponível' : (
            in_array($key, $monetary, true)
                ? 'R$ '.number_format($value / 100, 2, ',', '.')
                : number_format($value, 0, ',', '.').' un.'
        );
        $date = fn (string $value): string => implode('/', array_reverse(explode('-', $value)));
        $title = trim($name ?? '') ?: 'Desempenho do negócio';
        $title = mb_strimwidth($title, 0, 50, '...', 'UTF-8');
        $draw = [];
        $pages = [];
        $text = function (int $x, int $y, string $value, int $size = 11, bool $bold = false, string $color = 'ink') use (&$draw): void {
            $rgb = match ($color) {
                'muted' => '0.39 0.44 0.50',
                'green' => '0.10 0.43 0.29',
                'red' => '0.70 0.18 0.18',
                default => '0.12 0.17 0.24',
            };
            $encoded = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
            $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', '', ' '], $encoded);
            $draw[] = "{$rgb} rg BT /".($bold ? 'F2' : 'F1')." {$size} Tf 1 0 0 1 {$x} {$y} Tm ({$escaped}) Tj ET";
        };
        $rule = function (int $y, bool $dashed = false) use (&$draw): void {
            $dash = $dashed ? '[2 3] 0 d' : '[] 0 d';
            $draw[] = "0.78 0.82 0.86 RG 0.6 w {$dash} 50 {$y} m 545 {$y} l S [] 0 d";
        };
        $header = function (string $section) use ($text, $rule, $date, $from, $to, $title): void {
            $text(50, 797, 'PLAQUINHAS NFC  /  RELATÓRIO GERENCIAL', 10, true, 'muted');
            $text(50, 760, $title, mb_strlen($title) > 42 ? 17 : 23, true);
            $text(50, 739, 'Período: '.$date($from).' a '.$date($to).'  |  BRL', 10, false, 'muted');
            $rule(723);
            $text(50, 700, $section, 13, true);
        };

        // Paginação separa os indicadores dos gráficos, que sempre aparecem se selecionados.
        $header('Tabela 1 - Indicadores selecionados');
        $rule(684);
        $text(50, 668, 'Indicador', 10, true);
        $text(367, 668, 'Valor / quantidade', 10, true);
        $rule(657);
        $y = 638;
        foreach ($metrics as $key) {
            $value = $values[$key] ?? null;
            $color = $key === 'result' && $value !== null ? ($value < 0 ? 'red' : 'green') : 'ink';
            $text(50, $y, self::LABELS[$key]);
            $text(367, $y, $format($value, $key), 11, true, $color);
            $rule($y - 9, true);
            $y -= 23;
        }
        if (in_array('result', $metrics, true)) {
            $text(50, $y, 'Margem gerencial sobre vendas');
            $margin = $report['margin'];
            $text(367, $y, $margin === null ? 'Não disponível' : number_format($margin, 1, ',', '.').'%', 11, true,
                $margin === null ? 'ink' : ($margin < 0 ? 'red' : 'green'));
            $y -= 24;
        }
        $y -= 15;
        $text(50, $y, 'NOTAS E LIMITAÇÕES', 10, true);
        $y -= 20;
        $notes = [];
        if (in_array('pending', $metrics, true)) {
            $notes[] = 'A receber: saldo atual das vendas no período; não representa saldo histórico.';
        }
        if (in_array('received', $metrics, true)) {
            $notes[] = 'Recebido: pagamentos pela data de recebimento; sem data: '.$report['undatedPayments'].'.';
        }
        if (array_intersect(['cost', 'manufactured', 'result'], $metrics)) {
            $notes[] = 'Fabricação: '.$report['undatedBatches'].' lote(s) sem data; '.$report['unknownCosts'].' sem custo no período.';
        }
        if (in_array('loss', $metrics, true)) {
            $notes[] = 'Perdas: estimativas por data da venda; '.$report['unknownLosses'].' defeito(s) sem valor.';
        }
        if (in_array('result', $metrics, true)) {
            $notes[] = 'Diferença = vendas menos custo de lotes; margem = diferença / vendas.';
            $notes[] = 'Indicador gerencial, não lucro contábil: não considera todas as despesas.';
            $notes[] = 'Perdas estimadas são exibidas à parte para evitar dupla contagem.';
        }
        foreach ($notes as $note) {
            $text(50, $y, $note, 9, false, 'muted');
            $y -= 16;
        }
        $pages[] = $draw;
        $draw = [];

        if ($chart) {
            $header('Gráficos comparativos dos indicadores');
            $y = 673;
            $groups = [
                ['keys' => array_values(array_intersect($monetary, $metrics)), 'title' => 'Valores monetários (R$)'],
                ['keys' => array_values(array_intersect(['sold', 'manufactured', 'defective'], $metrics)), 'title' => 'Quantidades (unidades)'],
            ];
            $charts = 0;
            foreach ($groups as $group) {
                if (count($group['keys']) === 0) {
                    continue;
                }
                $charts++;
                $text(50, $y, $group['title'], 12, true);
                $y -= 27;
                $numbers = array_map(fn (string $key): float => abs((float) ($values[$key] ?? 0)), $group['keys']);
                $maximum = max(1.0, ...$numbers);
                foreach ($group['keys'] as $key) {
                    $value = $values[$key] ?? null;
                    $text(50, $y, self::LABELS[$key], 10);
                    $text(415, $y, $format($value, $key), 9, true,
                        $key === 'result' && $value !== null ? ($value < 0 ? 'red' : 'green') : 'ink');
                    $y -= 17;
                    $draw[] = "0.92 0.94 0.96 rg 50 {$y} 477 8 re f";
                    if ($value !== null && (float) $value !== 0.0) {
                        $width = max(1, (int) round(477 * abs((float) $value) / $maximum));
                        $rgb = $value < 0 ? '0.70 0.18 0.18' : ($key === 'result' ? '0.10 0.43 0.29' : '0.19 0.36 0.50');
                        $draw[] = "{$rgb} rg 50 {$y} {$width} 8 re f";
                    }
                    $y -= 25;
                }
                $y -= 16;
            }
            if ($charts === 0) {
                $text(50, $y, 'Nenhum indicador foi selecionado para exibição gráfica.', 10, false, 'muted');
            } else {
                $text(50, max(75, $y - 6), 'Escalas independentes por grupo. Valores negativos em vermelho.', 9, false, 'muted');
            }
            $pages[] = $draw;
            $draw = [];
        }

        // Tabela adicional detalhada por empresa, com paginação automática.
        $rows = $report['companies'] ?? [];
        if (count($rows)) {
            $pageRows = array_chunk($rows, 20);
            foreach ($pageRows as $index => $chunk) {
                $header('Tabela 2 - Resumo por empresa'.(count($pageRows) > 1 ? ' (continuação '.($index + 1).')' : ''));
                $text(50, 667, 'Empresa', 9, true);
                $text(238, 667, 'Vendido', 9, true);
                $text(330, 667, 'Recebido', 9, true);
                $text(427, 667, 'A receber', 9, true);
                $text(519, 667, 'Un.', 9, true);
                $rule(654);
                $y = 630;
                foreach ($chunk as $row) {
                    $text(50, $y, mb_strimwidth($row['name'], 0, 31, '...', 'UTF-8'), 9);
                    $text(238, $y, $format($row['sales'], 'sales'), 8);
                    $text(330, $y, $format($row['received'], 'received'), 8);
                    $text(427, $y, $format($row['pending'], 'pending'), 8);
                    $text(519, $y, (string) $row['sold'], 9, true);
                    $rule($y - 9, true);
                    $y -= 26;
                }
                $text(50, max(67, $y - 8), 'Vendas por data da venda; recebimentos por data do pagamento.', 9, false, 'muted');
                $pages[] = $draw;
                $draw = [];
            }
        }

        $pageCount = count($pages);
        $streams = [];
        foreach ($pages as $index => $commands) {
            $draw = $commands;
            $rule(48);
            $text(50, 32, 'Plaquinhas NFC  |  Uso interno', 9, false, 'muted');
            $text(499, 32, ($index + 1).' / '.$pageCount, 9, false, 'muted');
            $streams[] = implode("\n", $draw)."\n";
        }

        $fontRegular = 3 + $pageCount * 2;
        $fontBold = $fontRegular + 1;
        $kids = [];
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        foreach ($streams as $index => $stream) {
            $pageId = 3 + $index * 2;
            $contentId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
