<?php

declare(strict_types=1);

namespace Flavytech\Etims\DTOs;

/**
 * InvoiceLineDTO
 *
 * Represents a single line item within an invoice.
 *
 * KRA's payload uses OSCU/eTIMS field names, so this DTO keeps the SDK's
 * convenience names at the boundary while serializing to the exact wire format.
 *
 * The SDK does not auto-calculate taxes — the host application is responsible
 * for correct tax classification and totals.
 *
 * Tax Type Codes (KRA):
 *   A = Standard rate (16% VAT)
 *   B = Zero rated
 *   C = Exempt
 *   D = Non-VATable (e.g. insurance, financial services)
 *   E = Excisable goods with VAT
 *
 * Usage:
 *   $line = InvoiceLineDTO::make([
 *       'item_number'      => 1,
 *       'item_code'        => 'ITEM-001',
 *       'item_name'        => 'Widget Pro',
 *       'quantity'         => 2,
 *       'unit_price'       => 5000.00,
 *       'discount_amount'  => 0.00,
 *       'taxable_amount'   => 10000.00,
 *       'vat_amount'       => 1600.00,
 *       'total_amount'     => 11600.00,
 *       'tax_type_code'    => 'A',
 *   ]);
 */
final class InvoiceLineDTO
{
    public function __construct(
        public readonly int $itemNumber,
        public readonly string $itemCode,
        public readonly string $itemName,
        public readonly float $quantity,
        public readonly string $unitOfMeasure,
        public readonly float $unitPrice,
        public readonly float $discountAmount,
        public readonly float $taxableAmount,
        public readonly float $vatAmount,
        public readonly float $totalAmount,
        public readonly string $taxTypeCode,  // A, B, C, D, E
        public readonly ?string $itemCategory = null,
        public readonly ?string $barcode = null,
        public readonly ?float $supplyAmount = null,
        public readonly float $discountRate = 0.0,
        public readonly float $packageQuantity = 1.0,
        public readonly ?string $packageUnitCode = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function make(array $data): self
    {
        $get = static function (array $data, array $keys, mixed $default = null): mixed {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }

            return $default;
        };

        return new self(
            itemNumber:    (int) $get($data, ['item_number', 'itemSeq']),
            itemCode:      (string) $get($data, ['item_code', 'itemCd']),
            itemName:      (string) $get($data, ['item_name', 'itemNm']),
            quantity:      (float) $get($data, ['quantity', 'qty']),
            unitOfMeasure: (string) $get($data, ['unit_of_measure', 'qtyUnitCd'], 'EA'),
            unitPrice:     (float) $get($data, ['unit_price', 'prc']),
            discountAmount: (float) $get($data, ['discount_amount', 'dcAmt'], 0.0),
            taxableAmount:  (float) $get($data, ['taxable_amount', 'taxblAmt']),
            vatAmount:      (float) $get($data, ['vat_amount', 'taxAmt', 'vatAmt']),
            totalAmount:    (float) $get($data, ['total_amount', 'totAmt']),
            taxTypeCode:    (string) $get($data, ['tax_type_code', 'taxTyCd'], 'A'),
            itemCategory:   $get($data, ['item_category', 'itemClsCd']),
            barcode:        $get($data, ['barcode', 'bcd']),
            supplyAmount:   $get($data, ['supply_amount', 'splyAmt']),
            discountRate:   (float) $get($data, ['discount_rate', 'dcRt'], 0.0),
            packageQuantity: (float) $get($data, ['package_quantity', 'pkg'], 1.0),
            packageUnitCode: $get($data, ['package_unit_code', 'pkgUnitCd']),
        );
    }

    /**
     * Serialize to KRA API payload format.
     *
     * @return array<string, mixed>
     */
    public function toKraPayload(): array
    {
        $supplyAmount = $this->supplyAmount ?? max(0.0, $this->taxableAmount + $this->discountAmount);

        return [
            'itemSeq'     => $this->itemNumber,
            'itemCd'      => $this->itemCode,
            'itemNm'      => $this->itemName,
            'qty'         => $this->quantity,
            'qtyUnitCd'   => $this->unitOfMeasure,
            'prc'         => $this->unitPrice,
            'splyAmt'     => $supplyAmount,
            'dcRt'        => $this->discountRate,
            'dcAmt'       => $this->discountAmount,
            'taxblAmt'    => $this->taxableAmount,
            'vatAmt'      => $this->vatAmount,
            'taxAmt'      => $this->vatAmount, // alias
            'totAmt'      => $this->totalAmount,
            'taxTyCd'     => $this->taxTypeCode,
            'itemClsCd'   => $this->itemCategory,
            'bcd'         => $this->barcode,
            'pkg'         => $this->packageQuantity,
            'pkgUnitCd'   => $this->packageUnitCode,
        ];
    }
}
