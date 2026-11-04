<?php
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The invoice a client receives, as a PDF in the consultancy's own brand.
 *
 * Built from the local invoice, which mirrors Stripe's figures after sending,
 * so the amounts here are the ones Stripe is collecting. Stripe's own PDF
 * carries only the connected account's name and a generic layout.
 */
class InvoicePdf {

    public static function render(array $invoice, array $items, array $company): string
    {
        $brand    = ReportRenderer::brand($company);
        $accent   = ReportRenderer::accent($company);
        $logo     = ReportRenderer::logo($company);
        $fonts    = Main::lib_path().'/Fonts';
        $currency = strtoupper((string) $invoice['currency']);
        $symbol   = Money::symbol($invoice['currency']);
        $firm     = self::plain($company['company_name'] ?? '');
        $number   = self::plain($invoice['invoice_number']);
        $status   = (string) $invoice['invoice_status'];

        $e = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $money = function ($cents) use ($symbol) {
            return ((int) $cents < 0 ? '-' : '').$symbol.Money::figure(abs((int) $cents));
        };

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@font-face{font-family:"Sans";font-weight:normal;src:url("file://'.$fonts.'/source-sans-3-400-normal.ttf")}'
            .'@font-face{font-family:"Sans";font-weight:bold;src:url("file://'.$fonts.'/source-sans-3-600-normal.ttf")}'
            .'@page{margin:50pt 54pt 64pt 54pt}'
            .'body{font-family:"Sans";font-size:9.5pt;line-height:1.15;color:#1d2733}'
            .'table{width:100%;border-collapse:collapse}'
            .'td{vertical-align:top;padding:0}'
            .'.logo{height:50pt}'
            .'.firm{font-weight:bold;font-size:17pt;color:'.$brand.'}'
            .'.title{text-align:right;font-weight:bold;font-size:24pt;letter-spacing:3pt;color:'.$brand.';line-height:1}'
            .'.number{text-align:right;font-size:10pt;color:#55636f;margin-top:5pt}'
            .'.stamp{text-align:right;margin-top:6pt}'
            .'.stamp span{padding:2pt 8pt;font-weight:bold;font-size:8.5pt;letter-spacing:1.5pt;color:#ffffff}'
            .'.rule{height:3pt;background:'.$brand.';margin:16pt 0 0 0}'
            .'.rule2{height:1pt;background:'.$accent.';margin:0 0 20pt 0}'
            .'.label{font-size:7.5pt;font-weight:bold;letter-spacing:1.2pt;text-transform:uppercase;color:#7a8794;margin-bottom:5pt}'
            .'.party{font-size:9.5pt;line-height:1.15}'
            .'.party b{font-size:10.5pt}'
            .'.facts td{padding:0 0 3pt 0}'
            .'.facts td.k{color:#7a8794;width:46%}'
            .'.facts td.v{text-align:right;font-weight:bold}'
            .'.due{margin:24pt 0 22pt 0;background:'.$brand.';color:#ffffff}'
            .'.due td{padding:14pt 18pt;vertical-align:middle}'
            .'.due .k{font-size:7.5pt;font-weight:bold;letter-spacing:1.2pt;text-transform:uppercase;opacity:0.85}'
            .'.due .amount{font-weight:bold;font-size:22pt;line-height:1.1;margin-top:3pt}'
            .'.due .date{font-weight:bold;font-size:13pt;margin-top:3pt}'
            .'.due a.pay{display:inline-block;background:#ffffff;color:'.$brand.';font-weight:bold;font-size:10pt;padding:8pt 18pt;text-decoration:none}'
            .'.lines th{font-size:7.5pt;font-weight:bold;letter-spacing:1.2pt;text-transform:uppercase;color:#7a8794;text-align:left;padding:0 6pt 6pt 6pt;border-bottom:1pt solid #1d2733}'
            .'.lines td{padding:7pt 6pt;border-bottom:0.5pt solid #d6dce3}'
            .'.lines .n{text-align:right;white-space:nowrap}'
            .'.lines .idx{color:#7a8794;width:16pt}'
            .'.lines .desc{font-size:10pt}'
            .'.totals{width:46%;margin:12pt 0 0 54%}'
            .'.totals td{padding:2pt 6pt}'
            .'.totals td.v{text-align:right;white-space:nowrap}'
            .'.totals tr.grand td{border-top:1.5pt solid '.$brand.';padding-top:8pt;font-weight:bold;font-size:12pt;color:'.$brand.'}'
            .'.notes{margin-top:24pt;padding-top:10pt;border-top:0.5pt solid #d6dce3;page-break-inside:avoid}'
            .'.notes p{margin:0 0 6pt 0}'
            .'.muted{color:#55636f}'
            .'</style></head><body>';

        $html .= '<table><tr><td style="width:55%">'
            .($logo === null
                ? '<div class="firm">'.$e($firm).'</div>'
                : '<img class="logo" src="data:'.$logo['type'].';base64,'.base64_encode($logo['bytes']).'">')
            .'</td><td>'
            .'<div class="title">INVOICE</div>'
            .($number === '' ? '' : '<div class="number">'.$e($number).'</div>')
            .self::stamp($status)
            .'</td></tr></table>'
            .'<div class="rule"></div><div class="rule2"></div>';

        $html .= '<table><tr>'
            .'<td style="width:34%;padding-right:16pt"><div class="label">From</div><div class="party">'.self::lines(array(
                '<b>'.$e($firm).'</b>',
                $e(self::plain($company['address_1'] ?? '')),
                $e(self::plain($company['address_2'] ?? '')),
                $e(self::place($company['city'] ?? '', $company['state'] ?? '', $company['postal_code'] ?? '')),
                $e(self::plain($company['country'] ?? '')),
                $e(preg_replace('#^https?://#', '', self::plain($company['website'] ?? '')))
            )).'</div></td>'
            .'<td style="width:34%;padding-right:16pt"><div class="label">Bill to</div><div class="party">'.self::lines(array(
                '<b>'.$e(self::plain($invoice['client_name'])).'</b>',
                $e(self::attention($invoice)),
                $e(self::plain($invoice['client_address_1'] ?? '')),
                $e(self::plain($invoice['client_address_2'] ?? '')),
                $e(self::place($invoice['client_city'] ?? '', $invoice['client_state'] ?? '', $invoice['client_postal_code'] ?? '')),
                $e(self::plain($invoice['client_country'] ?? '')),
                $e(self::plain($invoice['client_billing_email'] !== '' ? $invoice['client_billing_email'] : $invoice['client_contact_email']))
            )).'</div></td>'
            .'<td><div class="label">Details</div><table class="facts">'.self::facts($invoice, $e).'</table></td>'
            .'</tr></table>';

        $html .= self::due_band($invoice, $money, $currency, $e);

        $has_discount = false;

        foreach ($items as $item) {
            if ((int) $item['discount_cents'] > 0) {
                $has_discount = true;
            }
        }

        $html .= '<table class="lines"><thead><tr>'
            .'<th class="idx"></th>'
            .'<th>Description</th>'
            .'<th class="n">Qty</th>'
            .'<th class="n">Unit price</th>'
            .($has_discount ? '<th class="n">Discount</th>' : '')
            .'<th class="n">Amount</th>'
            .'</tr></thead><tbody>';

        foreach ($items as $index => $item) {
            $discount = '';

            if ((int) $item['discount_cents'] > 0) {
                $discount = $item['discount_type'] === 'Percent'
                    ? Money::format_percent($item['discount_percent_bp']).'%'
                    : $money($item['discount_cents']);
            }

            $html .= '<tr>'
                .'<td class="idx">'.($index + 1).'</td>'
                .'<td class="desc">'.$e(self::plain($item['item_description'])).'</td>'
                .'<td class="n">'.$e(Money::format_quantity($item['quantity_milli'])).'</td>'
                .'<td class="n">'.$e($money($item['unit_amount_cents'])).'</td>'
                .($has_discount ? '<td class="n">'.$e($discount).'</td>' : '')
                .'<td class="n">'.$e($money($item['amount_cents'])).'</td>'
                .'</tr>';
        }

        $html .= '</tbody></table>';

        $discount_total = (int) $invoice['discount_cents'];
        $html .= '<table class="totals">';

        if ($discount_total > 0) {
            $html .= '<tr><td>Subtotal</td><td class="v">'.$e($money((int) $invoice['total_cents'] + $discount_total)).'</td></tr>'
                .'<tr><td>Discount</td><td class="v">-'.$e($money($discount_total)).'</td></tr>';
        }

        $html .= '<tr><td>Total</td><td class="v">'.$e($money($invoice['total_cents'])).'</td></tr>';

        if ((int) $invoice['amount_paid_cents'] > 0) {
            $html .= '<tr><td>Paid</td><td class="v">-'.$e($money($invoice['amount_paid_cents'])).'</td></tr>';
        }

        $html .= '<tr class="grand"><td>Amount due '.$e($currency).'</td><td class="v">'.$e($money($status === 'Void' ? 0 : $invoice['amount_due_cents'])).'</td></tr>'
            .'</table>';

        $footer = trim(self::plain($invoice['invoice_footer']));

        if ($footer !== '' || ($status === 'Open' && $invoice['hosted_invoice_url'] !== '')) {
            $html .= '<div class="notes"><div class="label">Notes</div>';

            if ($footer !== '') {
                $html .= '<p>'.$e($footer).'</p>';
            }

            if ($status === 'Open' && $invoice['hosted_invoice_url'] !== '') {
                $html .= '<p class="muted">Pay securely online by card or bank transfer at <a href="'.$e($invoice['hosted_invoice_url']).'" style="color:'.$brand.'">the payment page</a>. Please quote '.$e($number).' with any payment or question.</p>';
            }

            $html .= '</div>';
        }

        $html .= '</body></html>';

        $options = new Options();
        $options->setChroot(array(
            $fonts
        ));
        $options->setFontDir(sys_get_temp_dir());
        $options->setFontCache(sys_get_temp_dir());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter');
        $dompdf->render();

        $left  = $firm.(self::plain($company['website'] ?? '') === '' ? '' : '  ·  '.preg_replace('#^https?://#', '', self::plain($company['website'])));
        $right = ($number === '' ? 'Invoice' : 'Invoice '.$number);

        $dompdf->getCanvas()->page_script(function ($page, $total, $canvas, $metrics) use ($left, $right) {
            $font = $metrics->getFont('Sans');
            $grey = array(
                0.48,
                0.53,
                0.58
            );
            $width = $canvas->get_width();
            $label = $right.'  ·  Page '.$page.' of '.$total;
            $y = $canvas->get_height() - 38;
            $canvas->line(54, $y - 10, $width - 54, $y - 10, $grey, 0.4);
            $canvas->text(54, $y, $left, $font, 7.5, $grey);
            $canvas->text($width - 54 - $metrics->getTextWidth($label, $font, 7.5), $y, $label, $font, 7.5, $grey);
        });

        return $dompdf->output();
    }

    private static function plain($value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function place($city, $state, $postal): string
    {
        $city_state = implode(', ', array_filter(array(
            self::plain($city),
            self::plain($state)
        ), 'strlen'));

        return trim($city_state.' '.self::plain($postal));
    }

    /** The person the invoice is for, when it is someone other than the company itself. */
    private static function attention(array $invoice): string
    {
        $name = self::plain($invoice['client_billing_name'] !== '' ? $invoice['client_billing_name'] : $invoice['client_contact_name']);

        return ($name === '' || $name === self::plain($invoice['client_name'])) ? '' : 'Attn: '.$name;
    }

    private static function lines(array $lines): string
    {
        return implode('<br>', array_filter($lines, function ($line) {
            return trim(strip_tags($line)) !== '';
        }));
    }

    private static function stamp(string $status): string
    {
        $stamps = array(
            'Paid'          => '#15803D',
            'Void'          => '#6B7280',
            'Uncollectible' => '#B91C1C'
        );

        if (!isset($stamps[$status])) {
            return '';
        }

        return '<div class="stamp"><span style="background:'.$stamps[$status].'">'.strtoupper($status === 'Uncollectible' ? 'Written off' : $status).'</span></div>';
    }

    private static function facts(array $invoice, callable $e): string
    {
        $facts = array();

        $issued = $invoice['finalized_at'] ?? null;
        $facts['Issued'] = date('F j, Y', strtotime($issued === null ? $invoice['date_created'] : $issued));

        if ($invoice['due_date'] !== null && $invoice['invoice_status'] !== 'Open') {
            $facts['Due'] = date('F j, Y', strtotime($invoice['due_date']));
        }

        $facts['Terms'] = (int) $invoice['due_days'] === 0 ? 'Due on receipt' : 'Net '.(int) $invoice['due_days'];

        $html = '';

        foreach ($facts as $label => $value) {
            $html .= '<tr><td class="k">'.$e($label).'</td><td class="v">'.$e($value).'</td></tr>';
        }

        return $html;
    }

    private static function due_band(array $invoice, callable $money, string $currency, callable $e): string
    {
        $status = (string) $invoice['invoice_status'];

        if ($status === 'Paid') {
            return '<table class="due"><tr>'
                .'<td><div class="k">Paid in full</div><div class="amount">'.$e($money($invoice['amount_paid_cents'])).' '.$e($currency).'</div></td>'
                .'<td style="text-align:right">'.($invoice['paid_at'] === null ? '' : '<div class="k">Paid on</div><div class="date">'.$e(date('F j, Y', strtotime($invoice['paid_at']))).'</div>').'</td>'
                .'</tr></table>';
        }

        if ($status === 'Void') {
            return '<table class="due" style="background:#6B7280"><tr>'
                .'<td><div class="k">Voided</div><div class="date">This invoice has been cancelled and is not payable.</div></td>'
                .'</tr></table>';
        }

        $pay = ($status === 'Open' && $invoice['hosted_invoice_url'] !== '')
            ? '<td style="text-align:right"><a class="pay" href="'.$e($invoice['hosted_invoice_url']).'">Pay online</a></td>'
            : '';

        return '<table class="due"><tr>'
            .'<td><div class="k">Amount due</div><div class="amount">'.$e($money($invoice['amount_due_cents'])).' '.$e($currency).'</div></td>'
            .($invoice['due_date'] === null ? '' : '<td><div class="k">Due by</div><div class="date">'.$e(date('F j, Y', strtotime($invoice['due_date']))).'</div></td>')
            .$pay
            .'</tr></table>';
    }

}
