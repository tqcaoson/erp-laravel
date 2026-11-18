<?php

namespace App\Modules\Sales\Services;

/**
 * Tính tiền dùng chung cho báo giá và đơn bán.
 *
 * Quy ước: mỗi dòng có gross = qty × unit_price, chiết khấu theo dòng
 * (discount_rate %), thuế tính trên tiền sau chiết khấu (tax_rate %).
 * line_total = net + thuế. Tổng đơn = subtotal − discount_amount + tax_amount.
 */
class DocumentTotals
{
    /**
     * @param  array<int, array{qty: float, unit_price: float, discount_rate?: float, tax_rate?: float}> $lines
     * @return array{lines: array, subtotal: float, tax_amount: float, total_amount: float}
     */
    public static function compute(array $lines, float $discountAmount = 0): array
    {
        $computedLines = [];
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discountRate = (float) ($line['discount_rate'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 0);

            $gross = $qty * $unitPrice;
            $net = $gross * (1 - $discountRate / 100);
            $tax = $net * $taxRate / 100;

            $computedLines[] = array_merge($line, [
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
                'tax_rate' => $taxRate,
                'line_total' => round($net + $tax, 2),
            ]);

            $subtotal += $net;
            $taxAmount += $tax;
        }

        return [
            'lines' => $computedLines,
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($subtotal - $discountAmount + $taxAmount, 2),
        ];
    }
}
