<?php

declare(strict_types=1);

use Flavytech\Etims\DTOs\InvoiceDTO;
use Flavytech\Etims\DTOs\InvoiceLineDTO;
use Flavytech\Etims\Exceptions\EtimsValidationException;

// ========================================================================================
// InvoiceDTO Unit Tests
// ========================================================================================

it('creates an invoice DTO from a valid array', function () {
    $invoice = InvoiceDTO::make([
        'invoice_number' => 'INV-2024-001',
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 11600.00,
        'vat_amount'     => 1600.00,
        'invoice_date'   => '2024-01-15',
        'invoice_type'   => 'S',
    ]);

    expect($invoice->invoiceNumber)->toBe('INV-2024-001')
        ->and($invoice->supplierPin)->toBe('P000000000A')
        ->and($invoice->totalAmount)->toBe(11600.00)
        ->and($invoice->vatAmount)->toBe(1600.00)
        ->and($invoice->invoiceType)->toBe('S')
        ->and($invoice->currency)->toBe('KES'); // default
});

it('throws a validation exception when required fields are missing', function () {
    InvoiceDTO::make([
        'invoice_number' => 'INV-001',
        // missing supplier_pin, buyer_pin, total_amount, vat_amount, invoice_date
    ]);
})->throws(EtimsValidationException::class);

it('throws a validation exception for invalid invoice type', function () {
    InvoiceDTO::make([
        'invoice_number' => 'INV-001',
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 100.00,
        'vat_amount'     => 16.00,
        'invoice_date'   => '2024-01-15',
        'invoice_type'   => 'X', // invalid
    ]);
})->throws(EtimsValidationException::class, 'invoice_type must be S');

it('serializes to correct KRA payload format', function () {
    $invoice = InvoiceDTO::make([
        'invcNo'       => 'INV-001',
        'tpin'         => 'P000000000A',
        'custTpin'     => 'P000000000B',
        'custNm'       => 'Test Buyer',
        'totAmt'       => 11600.00,
        'vatAmt'       => 1600.00,
        'taxblAmt'     => 10000.00,
        'salesDt'      => '2024-01-15',
        'cfmDt'        => '2024-01-15',
        'rcptTyCd'     => 'S',
        'pmtTyCd'      => '01',
        'salesTyCd'    => 'N',
        'salesSttsCd'  => '02',
        'totItemCnt'   => 1,
        'taxblAmtA'    => 0.0,
        'taxblAmtB'    => 10000.0,
        'taxRtB'       => 16.0,
        'taxAmtB'      => 1600.0,
        'prchrAcptcYn' => 'N',
        'receipt'      => [
            'custTin' => 'P000000000B',
            'custNm' => 'Test Buyer',
            'prchrAcptcYn' => 'N',
            'topMsg' => 'Thank You!',
            'btmMsg' => 'Come Again!',
        ],
        'regrId'       => 'admin',
        'regrNm'       => 'Admin User',
        'modrId'       => 'admin',
        'modrNm'       => 'Admin User',
        'items'        => [
            InvoiceLineDTO::make([
                'itemSeq'   => 1,
                'itemCd'    => 'ITEM-001',
                'itemNm'    => 'Test Widget',
                'qty'       => 2,
                'qtyUnitCd' => 'EA',
                'prc'       => 5000.00,
                'splyAmt'   => 10000.00,
                'dcRt'      => 0.0,
                'dcAmt'     => 0.0,
                'taxblAmt'  => 10000.00,
                'taxAmt'    => 1600.00,
                'totAmt'    => 11600.00,
                'taxTyCd'   => 'A',
                'itemClsCd' => '10101501',
                'pkg'       => 1,
                'pkgUnitCd' => 'EA',
            ]),
        ],
    ]);

    $payload = $invoice->toKraPayload();

    expect($payload)->toMatchArray([
        'invcNo'       => 'INV-001',
        'tpin'         => 'P000000000A',
        'custTpin'     => 'P000000000B',
        'custNm'       => 'Test Buyer',
        'salesTyCd'    => 'N',
        'rcptTyCd'     => 'S',
        'pmtTyCd'      => '01',
        'salesSttsCd'  => '02',
        'cfmDt'        => '2024-01-15',
        'salesDt'      => '2024-01-15',
        'stockRlsDt'   => '2024-01-15',
        'totItemCnt'   => 1,
        'taxblAmtB'    => 10000.00,
        'taxRtB'       => 16.0,
        'taxAmtB'      => 1600.00,
        'totTaxblAmt'  => 10000.00,
        'totTaxAmt'    => 1600.00,
        'totAmt'       => 11600.00,
        'prchrAcptcYn' => 'N',
        'receipt'      => [
            'custTin' => 'P000000000B',
            'custNm' => 'Test Buyer',
            'prchrAcptcYn' => 'N',
            'topMsg' => 'Thank You!',
            'btmMsg' => 'Come Again!',
        ],
    ]);
});

it('accepts KRA-style keys in the InvoiceDTO factory', function () {
    $invoice = InvoiceDTO::make([
        'invcNo'   => 'INV-KRA-001',
        'tpin'     => 'P000000000A',
        'custTpin' => 'P000000000B',
        'totAmt'   => 250.00,
        'vatAmt'   => 34.48,
        'salesDt'  => '2024-01-15',
        'cfmDt'    => '2024-01-15',
        'items'    => [
            InvoiceLineDTO::make([
                'itemSeq'   => 1,
                'itemCd'    => 'ITEM-001',
                'itemNm'    => 'Widget Pro',
                'qty'       => 1,
                'prc'       => 250.00,
                'taxblAmt'  => 215.52,
                'taxAmt'    => 34.48,
                'totAmt'    => 250.00,
                'taxTyCd'   => 'A',
            ]),
        ],
    ]);

    expect($invoice->invoiceNumber)->toBe('INV-KRA-001')
        ->and($invoice->supplierPin)->toBe('P000000000A')
        ->and($invoice->buyerPin)->toBe('P000000000B')
        ->and($invoice->invoiceDate)->toBe('2024-01-15');
});

it('generates a deterministic idempotency key', function () {
    $invoiceA = InvoiceDTO::make([
        'invoice_number' => 'INV-001',
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 100.00,
        'vat_amount'     => 16.00,
        'invoice_date'   => '2024-01-15',
    ]);

    $invoiceB = InvoiceDTO::make([
        'invoice_number' => 'INV-001',
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 100.00,
        'vat_amount'     => 16.00,
        'invoice_date'   => '2024-01-15',
    ]);

    // Same data → same key
    expect($invoiceA->resolveIdempotencyKey())->toBe($invoiceB->resolveIdempotencyKey());
});

it('generates different idempotency keys for different invoices', function () {
    $invoiceA = InvoiceDTO::make([
        'invoice_number' => 'INV-001',
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 100.00,
        'vat_amount'     => 16.00,
        'invoice_date'   => '2024-01-15',
    ]);

    $invoiceB = InvoiceDTO::make([
        'invoice_number' => 'INV-002', // different number
        'supplier_pin'   => 'P000000000A',
        'buyer_pin'      => 'P000000000B',
        'total_amount'   => 200.00,   // different amount
        'vat_amount'     => 32.00,
        'invoice_date'   => '2024-01-15',
    ]);

    expect($invoiceA->resolveIdempotencyKey())->not->toBe($invoiceB->resolveIdempotencyKey());
});

it('accepts a custom idempotency key', function () {
    $invoice = InvoiceDTO::make([
        'invoice_number'  => 'INV-001',
        'supplier_pin'    => 'P000000000A',
        'buyer_pin'       => 'P000000000B',
        'total_amount'    => 100.00,
        'vat_amount'      => 16.00,
        'invoice_date'    => '2024-01-15',
        'idempotency_key' => 'my-custom-key-12345',
    ]);

    expect($invoice->resolveIdempotencyKey())->toBe('my-custom-key-12345');
});

it('serializes line items using KRA payload format', function () {
    $line = InvoiceLineDTO::make([
        'itemSeq'   => 1,
        'itemCd'    => 'ITEM-001',
        'itemNm'    => 'Test Widget',
        'qty'       => 2.0,
        'qtyUnitCd' => 'EA',
        'prc'       => 5000.00,
        'splyAmt'   => 10000.00,
        'dcRt'      => 0.0,
        'dcAmt'     => 0.0,
        'taxblAmt'  => 10000.00,
        'taxAmt'    => 1600.00,
        'totAmt'    => 11600.00,
        'taxTyCd'   => 'A',
        'itemClsCd' => '10101501',
        'pkg'       => 1,
        'pkgUnitCd' => 'EA',
    ]);

    $payload = $line->toKraPayload();

    expect($payload)->toMatchArray([
        'itemSeq'   => 1,
        'itemCd'    => 'ITEM-001',
        'itemNm'    => 'Test Widget',
        'qty'       => 2.0,
        'qtyUnitCd' => 'EA',
        'prc'       => 5000.00,
        'splyAmt'   => 10000.00,
        'dcRt'      => 0.0,
        'dcAmt'     => 0.0,
        'taxblAmt'  => 10000.00,
        'taxAmt'    => 1600.00,
        'totAmt'    => 11600.00,
        'taxTyCd'   => 'A',
        'itemClsCd' => '10101501',
        'pkg'       => 1,
        'pkgUnitCd' => 'EA',
    ]);
});
