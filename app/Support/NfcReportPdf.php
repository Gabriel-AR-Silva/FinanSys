<?php

namespace App\Support;

class NfcReportPdf
{
    private const LABELS = [
        'sales' => 'Valor vendido',
        'received' => 'Recebido',
        'pending' => 'Saldo a receber',
        'cost' => 'Custo de fabricação informado',
        'loss' => 'Perdas estimadas',
        'result' => 'Diferença entre vendas e custos',
        'sold' => 'Plaquinhas vendidas',
        'manufactured' => 'Plaquinhas fabricadas',
        'defective' => 'Plaquinhas defeituosas',
    ];

    public function render(array $report, array $metrics, string $from, string $to, bool $chart, ?string $name = null, bool $includeReinvestments = true): string
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
        $compact = fn ($value, bool $currency): string => $value === null ? 'N/D' : ($currency
            ? 'R$ '.number_format($value / 100, abs($value) >= 100000 ? 0 : 2, ',', '.')
            : number_format($value, 0, ',', '.'));
        $colors = [
            'blue' => '0.18 0.43 0.75', 'teal' => '0.09 0.60 0.58',
            'violet' => '0.48 0.37 0.79', 'orange' => '0.86 0.54 0.21',
            'green' => '0.11 0.54 0.37', 'red' => '0.75 0.26 0.28',
            'surface' => '0.96 0.97 0.985', 'border' => '0.87 0.90 0.94',
        ];
        $draw = [];
        $pages = [];
        $text = function (float $x, float $y, string $value, int $size = 11, bool $bold = false, string $color = 'ink') use (&$draw): void {
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
        $rect = function (float $x, float $y, float $w, float $h, string $color = 'surface', bool $outline = false) use (&$draw, $colors): void {
            $fill = $colors[$color] ?? $color;
            $draw[] = "{$fill} rg ".($outline ? $colors['border'].' RG 0.6 w ' : '')."{$x} {$y} {$w} {$h} re ".($outline ? 'B' : 'f');
        };
        $stroke = function (float $x1, float $y1, float $x2, float $y2, string $color = 'border', float $width = 1) use (&$draw, $colors): void {
            $draw[] = ($colors[$color] ?? $color)." RG {$width} w {$x1} {$y1} m {$x2} {$y2} l S";
        };
        $arc = function (float $cx, float $cy, float $ro, float $ri, float $start, float $end, string $color) use (&$draw, $colors): void {
            $sections = max(1, (int) ceil(abs($end - $start) / (M_PI / 2)));
            $delta = ($end - $start) / $sections;
            $segments = [];
            $segments[] = ($cx + $ro * cos($start)).' '.($cy + $ro * sin($start)).' m';
            foreach ([['radius' => $ro, 'direction' => 1], ['radius' => $ri, 'direction' => -1]] as $ring) {
                if ($ring['direction'] < 0) {
                    $segments[] = ($cx + $ri * cos($end)).' '.($cy + $ri * sin($end)).' l';
                }
                for ($i = 0; $i < $sections; $i++) {
                    $a = $ring['direction'] > 0 ? $start + $delta * $i : $end - $delta * $i;
                    $b = $a + $delta * $ring['direction'];
                    $radius = $ring['radius'];
                    $k = (4 / 3) * tan(($b - $a) / 4);
                    $x1 = $cx + $radius * (cos($a) - $k * sin($a));
                    $y1 = $cy + $radius * (sin($a) + $k * cos($a));
                    $x2 = $cx + $radius * (cos($b) + $k * sin($b));
                    $y2 = $cy + $radius * (sin($b) - $k * cos($b));
                    $x = $cx + $radius * cos($b);
                    $y = $cy + $radius * sin($b);
                    $segments[] = "{$x1} {$y1} {$x2} {$y2} {$x} {$y} c";
                }
            }
            $draw[] = $colors[$color].' rg '.implode(' ', $segments).' h f';
        };
        $header = function (string $section) use ($text, $rule, $date, $from, $to, $title): void {
            $text(50, 797, 'NFC  /  PAINEL GERENCIAL', 10, true, 'muted');
            $text(50, 760, $title, mb_strlen($title) > 42 ? 17 : 23, true);
            $text(50, 739, 'Período: '.$date($from).' a '.$date($to).'  |  BRL', 10, false, 'muted');
            $rule(723);
            $text(50, 700, $section, 13, true);
        };

        // Cards compactos em três colunas substituem a lista de barras horizontais.
        $header('Visão geral  /  indicadores selecionados');
        foreach ($metrics as $index => $key) {
            $x = 50 + 169 * ($index % 3);
            $y = 615 - 73 * intdiv($index, 3);
            $rect($x, $y, 158, 64, 'surface', true);
            $color = $key === 'result' && ($values[$key] ?? null) < 0 ? 'red' : ['blue', 'teal', 'violet'][$index % 3];
            $rect($x, $y + 60, 158, 4, $color);
            $text($x + 10, $y + 42, self::LABELS[$key], 9, false, 'muted');
            $text($x + 10, $y + 16, $compact($values[$key] ?? null, in_array($key, $monetary, true)), 13, true,
                $key === 'result' && $values[$key] !== null ? ($values[$key] < 0 ? 'red' : 'green') : 'ink');
        }

        if ($chart) {
            // Não renderizar painéis quando não há composição ou evolução útil.
            $pieces = [];
            $caption = '';
            $soldTypes = $report['soldTypes'] ?? [];
            $saleSplit = $report['salePaymentSplit'] ?? [];
            if (in_array('sold', $metrics, true) && array_sum($soldTypes) > 0) {
                $pieces = ['Normais' => $soldTypes['standard'] ?? 0, 'Personalizadas' => $soldTypes['custom'] ?? 0];
                $caption = 'Tipos de plaquinhas vendidas';
            } elseif ((in_array('sales', $metrics, true) || in_array('pending', $metrics, true)) && array_sum($saleSplit) > 0) {
                $pieces = ['Pago até hoje' => $saleSplit['paid'] ?? 0, 'A receber' => $saleSplit['pending'] ?? 0];
                $caption = 'Vendas do período: pagos e pendentes';
            } elseif (in_array('received', $metrics, true)) {
                $names = ['pix' => 'Pix', 'cash' => 'Dinheiro', 'credit' => 'Crédito', 'debit' => 'Débito'];
                foreach (($report['paymentMethods'] ?? []) as $method => $amount) {
                    $label = $names[$method] ?? ucfirst((string) $method);
                    $pieces[$label] = ($pieces[$label] ?? 0) + $amount;
                }
                $caption = 'Recebimentos por forma de pagamento';
            }
            $pieces = array_filter($pieces, fn ($n): bool => $n > 0);
            $sum = array_sum($pieces);
            $hasDonut = $sum > 0;

            $timeline = $report['timeline'] ?? [];
            $series = [];
            foreach ($metrics as $key) {
                if ($key === 'result' && ($values[$key] ?? null) === null) {
                    continue;
                }
                $hasEvolution = false;
                foreach ($timeline as $point) {
                    if (abs((float) ($point[$key] ?? 0)) > 0) {
                        $hasEvolution = true;
                        break;
                    }
                }
                if ($hasEvolution === false || (count($series) > 0 && in_array($series[0], $monetary, true) !== in_array($key, $monetary, true))) {
                    continue;
                }
                $series[] = $key;
                if (count($series) === 2) {
                    break;
                }
            }
            $hasLine = count($series) > 0;

            if ($hasDonut) {
                $donutWidth = $hasLine ? 241 : 495;
                $donutCenter = $hasLine ? 170 : 290;
                $rect(50, 154, $donutWidth, 300, '1 1 1', true);
                $text(64, 429, 'COMPOSIÇÃO', 11, true);
                $text(64, 407, mb_strimwidth($caption, 0, 50, '...', 'UTF-8'), 9, false, 'muted');
                $start = M_PI / 2;
                $pieColors = ['blue', 'teal', 'violet', 'orange'];
                $i = 0;
                foreach ($pieces as $amount) {
                    $end = $start + 2 * M_PI * $amount / $sum;
                    $arc($donutCenter, 315, 72, 45, $start, $end, $pieColors[$i % count($pieColors)]);
                    $start = $end;
                    $i++;
                }
                $text($donutCenter - 22, 313, number_format($sum / (in_array('sold', $metrics, true) && array_sum($soldTypes) > 0 ? 1 : 100), 0, ',', '.'), 16, true);
                $text($donutCenter - 12, 297, in_array('sold', $metrics, true) && array_sum($soldTypes) > 0 ? 'un.' : 'R$', 10, false, 'muted');
                $i = 0;
                $legendY = 216;
                foreach ($pieces as $label => $amount) {
                    $rect(64, $legendY - 2, 8, 8, $pieColors[$i % count($pieColors)]);
                    $text(77, $legendY, mb_strimwidth((string) $label, 0, 28, '...', 'UTF-8'), 9);
                    $text($hasLine ? 244 : 460, $legendY, number_format($amount / $sum * 100, 0, ',', '.').'%', 9, true, 'muted');
                    $legendY -= 18;
                    if (++$i === 3) {
                        break;
                    }
                }
            }

            if ($hasLine) {
                $lineX = $hasDonut ? 303 : 50;
                $lineWidth = $hasDonut ? 242 : 495;
                $rect($lineX, 154, $lineWidth, 300, '1 1 1', true);
                $text($lineX + 14, 429, 'EVOLUÇÃO NO PERÍODO', 11, true);
                $max = 1.0;
                foreach ($timeline as $point) {
                    foreach ($series as $key) {
                        $max = max($max, abs((float) ($point[$key] ?? 0)));
                    }
                }
                $plotX = $lineX + 22;
                $plotY = 235;
                $plotW = $hasDonut ? 195 : 440;
                $plotH = 137;
                for ($i = 0; $i < 4; $i++) {
                    $gridY = $plotY + $plotH * $i / 3;
                    $stroke($plotX, $gridY, $plotX + $plotW, $gridY);
                }
                foreach ($series as $seriesIndex => $key) {
                    $color = ['blue', 'teal'][$seriesIndex];
                    $legendX = $plotX + ($hasDonut ? 111 : 198) * $seriesIndex;
                    $rect($legendX, 396, 8, 8, $color);
                    $text($legendX + 12, 398, mb_strimwidth(self::LABELS[$key], 0, $hasDonut ? 18 : 30, '...', 'UTF-8'), 8);
                    $previous = null;
                    foreach ($timeline as $pointIndex => $point) {
                        $x = $plotX + $plotW * $pointIndex / max(1, count($timeline) - 1);
                        $y = $plotY + $plotH * max(0, (float) ($point[$key] ?? 0)) / $max;
                        if ($previous) {
                            $stroke($previous[0], $previous[1], $x, $y, $color, 2.2);
                        }
                        $rect($x - 2.5, $y - 2.5, 5, 5, $color);
                        $previous = [$x, $y];
                    }
                }
                $text($plotX, 218, $timeline[0]['label'], 9, false, 'muted');
                $text($plotX + $plotW - 35, 218, $timeline[count($timeline) - 1]['label'], 9, false, 'muted');
                $text($lineX + 14, 182, 'Até 8 pontos no período selecionado.', 8, false, 'muted');
            }
            if ($hasDonut || $hasLine) {
                $text(50, 112, 'VISÃO GERENCIAL', 10, true);
                $text(50, 93, 'Os valores respeitam os filtros escolhidos. Consulte as notas nas páginas seguintes.', 9, false, 'muted');
            }
        }
        $pages[] = $draw;
        $draw = [];

        // A antiga página de colunas foi removida: mesmo com valores presentes,
        // o renderizador podia produzir um PDF com a área do gráfico em branco.
        // Mantemos os cards e gráficos de composição/evolução na primeira página.

        $header('Detalhamento  /  notas do relatório');
        $text(50, 670, 'INDICADOR', 10, true);
        $text(374, 670, 'VALOR', 10, true);
        $rule(656);
        $y = 636;
        foreach ($metrics as $key) {
            $value = $values[$key] ?? null;
            $text(50, $y, self::LABELS[$key], 10);
            $text(374, $y, $format($value, $key), 10, true,
                $key === 'result' && $value !== null ? ($value < 0 ? 'red' : 'green') : 'ink');
            $rule($y - 10);
            $y -= 25;
        }
        if (in_array('result', $metrics, true)) {
            $text(50, $y, 'Margem gerencial sobre vendas', 10);
            $margin = $report['margin'];
            $text(374, $y, $margin === null ? 'Não disponível' : number_format($margin, 1, ',', '.').'%', 10, true,
                $margin === null ? 'muted' : ($margin < 0 ? 'red' : 'green'));
            $y -= 26;
        }
        $cash = $report['cash'] ?? [];
        if ($includeReinvestments && (($cash['received_cents'] ?? 0) > 0 || ($cash['reinvested_cents'] ?? 0) > 0 || ($cash['expenses_cents'] ?? 0) > 0)) {
            $y -= 17;
            $text(50, $y, 'MOVIMENTAÇÃO DO CAIXA NFC NO PERÍODO', 11, true);
            $y -= 23;
            $text(50, $y, 'Recebido nas vendas', 10);
            $text(374, $y, $format($cash['received_cents'], 'received'), 10, true);
            $y -= 21;
            $text(50, $y, 'Retirado para reinvestimento', 10);
            $text(374, $y, $format($cash['reinvested_cents'], 'received'), 10, true, 'red');
            $y -= 21;
            $text(50, $y, 'Outras despesas do caixa', 10);
            $text(374, $y, $format($cash['expenses_cents'] ?? 0, 'received'), 10, true, 'red');
            $y -= 21;
            $text(50, $y, 'Movimento líquido do período', 10);
            $text(374, $y, $format($cash['net_cents'], 'received'), 10, true,
                $cash['net_cents'] < 0 ? 'red' : 'green');
            $y -= 20;
            $rows = $report['reinvestmentRows'] ?? [];
            foreach (array_slice($rows, 0, 2) as $row) {
                $label = $date($row['spent_on']).' · '.mb_strimwidth((string) $row['description'], 0, 38, '...', 'UTF-8');
                $text(50, $y, $label, 9, false, 'muted');
                $text(374, $y, $format($row['amount_cents'], 'received'), 9, false, 'muted');
                $y -= 17;
            }
            if (count($rows) > 2) {
                $text(50, $y, 'E mais '.(count($rows) - 2).' reinvestimento(s) no período.', 9, false, 'muted');
                $y -= 17;
            }
        }

        $y -= 17;
        $text(50, $y, 'OBSERVAÇÕES E LIMITAÇÕES', 11, true);
        $y -= 23;
        $notes = $report['scope'] ?? [];
        if ($notes !== []) {
            $notes[] = 'Recorte de vendas: custos, avarias de fabricação e caixa geral não são atribuídos.';
        }
        if (($report['scope'] ?? []) === [] && (($cash['reinvested_cents'] ?? 0) > 0 || ($cash['received_cents'] ?? 0) > 0 || ($cash['expenses_cents'] ?? 0) > 0)) {
            $notes[] = 'Movimento líquido = recebimentos menos reinvestimentos e outras despesas. Não é lucro.';
            if ($report['undatedPayments'] > 0) {
                $notes[] = 'Pagamentos sem data não entram no movimento do período: '.$report['undatedPayments'].'.';
            }
        }
        if (in_array('pending', $metrics, true)) {
            $notes[] = 'A receber: saldo atual das vendas do período, não saldo histórico.';
        }
        if (in_array('received', $metrics, true)) {
            $notes[] = 'Recebido: pagamentos pela data de recebimento; sem data: '.$report['undatedPayments'].'.';
        }
        if (array_intersect(['cost', 'manufactured', 'result'], $metrics)) {
            $notes[] = 'Fabricação: '.$report['undatedBatches'].' lote(s) sem data; '.$report['unknownCosts'].' sem custo no período.';
        }
        if (in_array('loss', $metrics, true)) {
            $notes[] = 'Perdas: R$ 25,00 por unidade quando não houver outro valor informado.';
            $notes[] = 'Data da avaria; defeitos antigos sem data usam a data da venda. Não é saída de caixa.';
        }
        if (in_array('sold', $metrics, true)) {
            $notes[] = 'Vendas do período: '.($report['usableSold'] ?? $values['sold']).' unidade(s) atualmente sem defeito.';
        }
        if (in_array('manufactured', $metrics, true)) {
            $notes[] = 'Fabricação do período: '.($report['usableManufactured'] ?? $values['manufactured']).' unidade(s) atualmente aproveitáveis.';
        }
        if (in_array('result', $metrics, true)) {
            $notes[] = 'Diferença = vendas menos custos de lotes; margem = diferença / vendas.';
            $notes[] = 'Não é lucro contábil: não considera estoque nem todas as despesas.';
            $notes[] = 'Lotes sem data podem impedir o cálculo da diferença e da margem.';
        }
        if ($chart && (in_array('sales', $metrics, true) || in_array('pending', $metrics, true))) {
            $notes[] = 'No donut, pago até hoje pode incluir pagamentos após o período.';
        }
        foreach ($notes as $note) {
            if ($y < 84) {
                $pages[] = $draw;
                $draw = [];
                $header('Detalhamento  /  notas (continuação)');
                $text(50, 670, 'OBSERVAÇÕES E LIMITAÇÕES', 11, true);
                $y = 646;
            }
            $text(50, $y, mb_strimwidth($note, 0, 91, '...', 'UTF-8'), 9, false, 'muted');
            $y -= 18;
        }
        $pages[] = $draw;
        $draw = [];

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
