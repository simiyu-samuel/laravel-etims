<?php

declare(strict_types=1);

namespace Flavytech\Etims\DTOs;

use Flavytech\Etims\Exceptions\EtimsValidationException;

/**
 * InvoiceDTO
 *
 * Represents a single invoice to be submitted to KRA eTIMS.
 *
 * Architecture Decision: We use plain PHP DTOs (not Laravel Models or Eloquent)
 * so the SDK is transport-agnostic. The host application maps its domain models
 * to these DTOs before calling the SDK. This keeps a clean boundary between
 * your business logic and KRA's API contract.
 *
 * The DTO is immutable after construction (readonly properties). This prevents
 * accidental mutation before the invoice is submitted.
 *
 * Usage:
 *   $invoice = InvoiceDTO::make([
 *       'invoice_number'  => 'INV-2024-001',
 *       'supplier_pin'    => 'P000000000A',
 *       'buyer_pin'       => 'P000000000B',
 *       'buyer_name'      => 'Acme Ltd',
 *       'total_amount'    => 11800.00,
 *       'vat_amount'      => 1800.00,
 *       'taxable_amount'  => 10000.00,
 *       'invoice_date'    => '2024-01-15',
 *       'invoice_type'    => 'S', // S=Sale, R=Credit Note, D=Debit Note
 *       'payment_type'    => '01',
 *       'items'           => [...InvoiceLineDTO objects],
 *   ]);
 */
final class InvoiceDTO
{
    /**
     * @param InvoiceLineDTO[] $items
     */
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $supplierPin,
        public readonly string $buyerPin,
        public readonly float $totalAmount,
        public readonly float $vatAmount,
        public readonly float $taxableAmount,
        public readonly float $exemptAmount,
        public readonly string $currency,
        public readonly string $invoiceDate,
        public readonly string $invoiceType,       // S=Sale, R=Credit Note, D=Debit Note
        public readonly string $paymentType,       // 01, 02, MPESA, BANK, etc.
        public readonly array $items,
        public readonly ?string $originalInvoiceNumber = null, // for credit/debit notes
        public readonly ?string $buyerName = null,
        public readonly ?string $branchId = null,
        public readonly ?string $remarks = null,
        public readonly ?string $idempotencyKey = null,
        public readonly string $salesTypeCode = 'N',
        public readonly string $salesStatusCode = '02',
        public readonly ?string $confirmationDate = null,
        public readonly ?string $salesDate = null,
        public readonly ?string $stockReleaseDate = null,
        public readonly int $totalItemCount = 0,
        public readonly float $taxableAmountA = 0.0,
        public readonly float $taxableAmountB = 0.0,
        public readonly float $taxableAmountC = 0.0,
        public readonly float $taxableAmountD = 0.0,
        public readonly float $taxableAmountE = 0.0,
        public readonly float $taxRateA = 0.0,
        public readonly float $taxRateB = 0.0,
        public readonly float $taxRateC = 0.0,
        public readonly float $taxRateD = 0.0,
        public readonly float $taxRateE = 0.0,
        public readonly float $taxAmountA = 0.0,
        public readonly float $taxAmountB = 0.0,
        public readonly float $taxAmountC = 0.0,
        public readonly float $taxAmountD = 0.0,
        public readonly float $taxAmountE = 0.0,
        public readonly string $purchaseAcceptanceYn = 'N',
        public readonly ?array $receipt = null,
        public readonly ?string $regrId = null,
        public readonly ?string $regrNm = null,
        public readonly ?string $modrId = null,
        public readonly ?string $modrNm = null,
    ) {}

    /**
     * Named constructor for array-based creation (useful with form data or DB rows).
     *
     * @param array<string, mixed> $data
     * @throws EtimsValidationException
     */
    public static function make(array $data): self
    {
        self::validate($data);

        $get = static function (array $data, array $keys, mixed $default = null): mixed {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }

            return $default;
        };

        $invoiceDate = (string) $get($data, ['invoice_date', 'cfm_dt', 'cfmDt', 'sales_dt', 'salesDt']);
        $invoiceType = (string) $get($data, ['invoice_type', 'rcptTyCd'], 'S');
        if ($invoiceType === 'C') {
            $invoiceType = 'R';
        }
        $buyerPin = (string) $get($data, ['buyer_pin', 'cust_tpin', 'custTpin', 'custTin']);
        $buyerName = $get($data, ['buyer_name', 'cust_nm', 'custNm']);
        $items = $get($data, ['items', 'itemList'], []);
        if (is_array($items)) {
            $items = array_map(
                fn(mixed $item) => $item instanceof InvoiceLineDTO ? $item : InvoiceLineDTO::make((array) $item),
                $items
            );
        }

        $receipt = $get($data, ['receipt'], null);

        if (!is_array($receipt)) {
            $receipt = [
                'custTin' => $buyerPin,
                'custNm' => $buyerName,
                'prchrAcptcYn' => (string) $get($data, ['prchr_acceptance_yn', 'prchrAcptcYn'], 'N'),
                'topMsg' => $get($data, ['receipt_top_msg', 'receiptTopMsg'], 'Thank You!'),
                'btmMsg' => $get($data, ['receipt_bottom_msg', 'receiptBtmMsg'], 'Come Again!'),
            ];
        }

        return new self(
            invoiceNumber:         (string) $get($data, ['invoice_number', 'invcNo']),
            supplierPin:           (string) $get($data, ['supplier_pin', 'tpin']),
            buyerPin:              $buyerPin,
            totalAmount:           (float) $get($data, ['total_amount', 'totAmt']),
            vatAmount:             (float) $get($data, ['vat_amount', 'taxAmt', 'vatAmt']),
            taxableAmount:         (float) ($get($data, ['taxable_amount', 'taxblAmt'], null) ?? ((float) $get($data, ['total_amount', 'totAmt']) - (float) $get($data, ['vat_amount', 'taxAmt', 'vatAmt']))),
            exemptAmount:          (float) $get($data, ['exempt_amount', 'nontaxblAmt'], 0.0),
            currency:              (string) $get($data, ['currency', 'curCd'], 'KES'),
            invoiceDate:           $invoiceDate,
            invoiceType:           $invoiceType,
            paymentType:           (string) $get($data, ['payment_type', 'pmtTyCd'], '01'),
            items:                 $items,
            originalInvoiceNumber: (string) $get($data, ['original_invoice_number', 'orgInvcNo'], '') ?: null,
            buyerName:             $buyerName,
            branchId:              $get($data, ['branch_id', 'bhfId'], null),
            remarks:               $get($data, ['remarks', 'remark'], null),
            idempotencyKey:        $get($data, ['idempotency_key'], null),
            salesTypeCode:         (string) $get($data, ['sales_type_code', 'salesTyCd'], 'N'),
            salesStatusCode:       (string) $get($data, ['sales_status_code', 'salesSttsCd'], '02'),
            confirmationDate:      (string) $get($data, ['confirmation_date', 'cfmDt'], '') ?: null,
            salesDate:             (string) $get($data, ['sales_date', 'salesDt'], '') ?: $invoiceDate,
            stockReleaseDate:      (string) $get($data, ['stock_release_date', 'stockRlsDt'], '') ?: null,
            totalItemCount:        (int) $get($data, ['total_item_count', 'totItemCnt'], count($items)),
            taxableAmountA:        (float) $get($data, ['taxable_amount_a', 'taxblAmtA'], 0.0),
            taxableAmountB:        (float) $get($data, ['taxable_amount_b', 'taxblAmtB'], 0.0),
            taxableAmountC:        (float) $get($data, ['taxable_amount_c', 'taxblAmtC'], 0.0),
            taxableAmountD:        (float) $get($data, ['taxable_amount_d', 'taxblAmtD'], 0.0),
            taxableAmountE:        (float) $get($data, ['taxable_amount_e', 'taxblAmtE'], 0.0),
            taxRateA:              (float) $get($data, ['tax_rate_a', 'taxRtA'], 0.0),
            taxRateB:              (float) $get($data, ['tax_rate_b', 'taxRtB'], 0.0),
            taxRateC:              (float) $get($data, ['tax_rate_c', 'taxRtC'], 0.0),
            taxRateD:              (float) $get($data, ['tax_rate_d', 'taxRtD'], 0.0),
            taxRateE:              (float) $get($data, ['tax_rate_e', 'taxRtE'], 0.0),
            taxAmountA:            (float) $get($data, ['tax_amount_a', 'taxAmtA'], 0.0),
            taxAmountB:            (float) $get($data, ['tax_amount_b', 'taxAmtB'], 0.0),
            taxAmountC:            (float) $get($data, ['tax_amount_c', 'taxAmtC'], 0.0),
            taxAmountD:            (float) $get($data, ['tax_amount_d', 'taxAmtD'], 0.0),
            taxAmountE:            (float) $get($data, ['tax_amount_e', 'taxAmtE'], 0.0),
            purchaseAcceptanceYn:  (string) $get($data, ['purchase_acceptance_yn', 'prchrAcptcYn'], 'N'),
            receipt:               $receipt,
            regrId:                $get($data, ['regr_id', 'regrId'], null),
            regrNm:                $get($data, ['regr_nm', 'regrNm'], null),
            modrId:                $get($data, ['modr_id', 'modrId'], null),
            modrNm:                $get($data, ['modr_nm', 'modrNm'], null),
        );
    }

    /**
     * Generate a deterministic idempotency key for this invoice.
     *
     * The key is based on invoice_number + supplier_pin + total_amount.
     * This ensures the same invoice always produces the same key, even across
     * retries, while a genuinely different invoice gets a different key.
     */
    public function resolveIdempotencyKey(): string
    {
        return $this->idempotencyKey ?? md5(
            $this->invoiceNumber . $this->supplierPin . $this->totalAmount
        );
    }

    /**
     * Serialize to the KRA API payload format.
     *
     * This method knows the KRA field naming conventions so the rest of
     * your code can use clean PHP conventions (camelCase, descriptive names).
     *
     * @return array<string, mixed>
     */
    public function toKraPayload(): array
    {
        $items = array_map(fn(InvoiceLineDTO $item) => $item->toKraPayload(), $this->items);
        $receipt = $this->receipt ?? [
            'custTin' => $this->buyerPin,
            'custNm' => $this->buyerName,
            'prchrAcptcYn' => $this->purchaseAcceptanceYn,
            'topMsg' => 'Thank You!',
            'btmMsg' => 'Come Again!',
        ];

        return [
            'invcNo'       => $this->invoiceNumber,
            'tpin'         => $this->supplierPin,
            'custTpin'     => $this->buyerPin,
            'custNm'       => $this->buyerName,
            'salesTyCd'    => $this->salesTypeCode,
            'rcptTyCd'     => $this->invoiceType,
            'pmtTyCd'      => $this->paymentType,
            'salesSttsCd'  => $this->salesStatusCode,
            'cfmDt'        => $this->confirmationDate ?? $this->invoiceDate,
            'salesDt'      => $this->salesDate ?? $this->invoiceDate,
            'stockRlsDt'   => $this->stockReleaseDate ?? $this->confirmationDate ?? $this->invoiceDate,
            'totItemCnt'   => $this->totalItemCount ?: count($items),
            'taxblAmtA'    => $this->taxableAmountA,
            'taxblAmtB'    => $this->taxableAmountB,
            'taxblAmtC'    => $this->taxableAmountC,
            'taxblAmtD'    => $this->taxableAmountD,
            'taxblAmtE'    => $this->taxableAmountE,
            'taxRtA'       => $this->taxRateA,
            'taxRtB'       => $this->taxRateB,
            'taxRtC'       => $this->taxRateC,
            'taxRtD'       => $this->taxRateD,
            'taxRtE'       => $this->taxRateE,
            'taxAmtA'      => $this->taxAmountA,
            'taxAmtB'      => $this->taxAmountB,
            'taxAmtC'      => $this->taxAmountC,
            'taxAmtD'      => $this->taxAmountD,
            'taxAmtE'      => $this->taxAmountE,
            'totTaxblAmt'  => $this->taxableAmount,
            'totTaxAmt'    => $this->vatAmount,
            'totAmt'       => $this->totalAmount,
            'prchrAcptcYn' => $this->purchaseAcceptanceYn,
            'curCd'        => $this->currency,
            'nontaxblAmt'  => $this->exemptAmount,
            'remark'       => $this->remarks,
            'orgInvcNo'    => $this->originalInvoiceNumber,
            'receipt'      => $receipt,
            'itemList'     => $items,
            'regrId'       => $this->regrId,
            'regrNm'       => $this->regrNm,
            'modrId'       => $this->modrId,
            'modrNm'       => $this->modrNm,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @throws EtimsValidationException
     */
    private static function validate(array $data): void
    {
        $required = [
            ['invoice_number', 'invcNo'],
            ['supplier_pin', 'tpin'],
            ['buyer_pin', 'custTpin', 'custTin'],
            ['total_amount', 'totAmt'],
            ['vat_amount', 'taxAmt', 'vatAmt'],
            ['invoice_date', 'cfmDt', 'salesDt'],
        ];

        $missing = [];

        foreach ($required as $group) {
            $present = false;

            foreach ($group as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== '' && $data[$key] !== null) {
                    $present = true;
                    break;
                }
            }

            if (!$present) {
                $missing[] = $group[0];
            }
        }

        if (!empty($missing)) {
            throw new EtimsValidationException(
                'Missing required InvoiceDTO fields: ' . implode(', ', $missing)
            );
        }

        $invoiceType = $data['invoice_type'] ?? $data['rcptTyCd'] ?? 'S';
        if ($invoiceType === 'C') {
            $invoiceType = 'R';
        }

        if (!in_array($invoiceType, ['S', 'R', 'D'], true)) {
            throw new EtimsValidationException('invoice_type must be S (Sale), R (Credit Note), or D (Debit Note).');
        }
    }
}
