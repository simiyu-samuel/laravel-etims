# 🇰🇪 Laravel eTIMS SDK

[![Latest Version](https://img.shields.io/packagist/v/flavytech/laravel-etims.svg?style=flat-square)](https://packagist.org/packages/flavytech/laravel-etims)
[![PHP Version](https://img.shields.io/badge/php-8.3%2B-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-11%2B-red.svg?style=flat-square)](https://laravel.com)
[![Tests](https://github.com/flavytech/laravel-etims/actions/workflows/tests.yml/badge.svg)](https://github.com/flavytech/laravel-etims/actions)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

> **Production-grade Laravel SDK for Kenya's KRA eTIMS / Gava Connect API.**
> Built for POS systems, ERP platforms, and SaaS products operating in Kenya and East Africa.

---

## Why This SDK?

Integrating with KRA eTIMS is non-trivial in real production environments:

- The API goes down. Your invoices must not be lost.
- Networks in Kenya are intermittent. Retries must be smart, not dumb.
- POS systems process hundreds of transactions per hour. Your queue must be resilient.
- You may serve multiple businesses from one Laravel installation. Multi-tenancy must be first-class.

This SDK handles all of that so you can focus on your product.

---

## Features

| Feature | Status |
|---|---|
| Invoice submission (sync + async) | ✅ Phase 1 |
| Auth token management + auto-refresh | ✅ Phase 1 |
| Exponential backoff retry | ✅ Phase 1 |
| Idempotency protection | ✅ Phase 1 |
| Audit trail (DB) | ✅ Phase 1 |
| Laravel event system integration | ✅ Phase 1 |
| Sandbox/production mode switching | ✅ Phase 1 |
| Testing fake + assertion API | ✅ Phase 1 |
| KRA PIN validation | ✅ Phase 1 |
| Dead letter queue + failed invoice recovery | ✅ Phase 1 |
| Multi-tenant SaaS support | ✅ Phase 1 |
| Stock synchronization | 🔜 Phase 2 |
| Credit/debit note handling | 🔜 Phase 2 |
| Webhook handling | 🔜 Phase 2 |
| QR code + thermal receipt support | 🔜 Phase 3 |
| Branch management | 🔜 Phase 3 |

---

## Installation

```bash
composer require flavytech/laravel-etims
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=etims-config
php artisan vendor:publish --tag=etims-migrations
php artisan migrate
```

---

## Configuration

Add these variables to your `.env` file:

```dotenv
# Mode: sandbox | production
ETIMS_MODE=sandbox

# Your KRA-issued credentials
ETIMS_PIN=P000000000A
ETIMS_BRANCH_ID=00
ETIMS_DEVICE_SERIAL=your-device-serial
ETIMS_SECRET=your-api-secret

# Queue (recommended: use Redis)
ETIMS_QUEUE_CONNECTION=redis
ETIMS_QUEUE_NAME=etims
ETIMS_MAX_TRIES=5

# Optional tuning
ETIMS_TIMEOUT=30
ETIMS_LOG_CHANNEL=daily
```

> **Never commit real credentials to version control.** Use `.env` only.

---

## Quick Start

### Submitting an Invoice Synchronously

Use this when you need an immediate KRA response (e.g. a POS checkout flow):

```php
use Flavytech\Etims\Facades\Etims;
use Flavytech\Etims\DTOs\InvoiceDTO;
use Flavytech\Etims\DTOs\InvoiceLineDTO;

$invoice = InvoiceDTO::make([
    'invcNo'       => 'INV-2024-001',
    'tpin'         => config('etims.credentials.pin'),
    'custTpin'     => 'P000000000B',    // buyer's KRA PIN
    'custNm'       => 'Acme Ltd',
    'totAmt'       => 11600.00,
    'vatAmt'       => 1600.00,
    'taxblAmt'     => 10000.00,
    'salesDt'      => now()->toDateString(),
    'cfmDt'        => now()->toDateString(),
    'rcptTyCd'     => 'S',              // S = Sale
    'pmtTyCd'      => '01',
    'itemList'     => [
        InvoiceLineDTO::make([
            'itemSeq'    => 1,
            'itemCd'     => 'ITEM-001',
            'itemNm'     => 'Widget Pro',
            'qty'        => 2,
            'prc'        => 5000.00,
            'taxblAmt'   => 10000.00,
            'taxAmt'     => 1600.00,
            'totAmt'     => 11600.00,
            'taxTyCd'    => 'A',       // A = Standard 16% VAT
        ]),
    ],
]);

$response = Etims::submitInvoice($invoice);

if ($response->isSuccessful()) {
    // Print the KRA receipt number and QR code
    $receiptNumber = $response->receiptNumber;
    $qrCode        = $response->qrCode;
}
```

`InvoiceDTO::make()` accepts both the original snake_case keys and the KRA field names shown above.

### Queuing an Invoice (Recommended for Most Cases)

This is the preferred approach for production. The invoice is dispatched to a durable background job with automatic retries:

```php
// Fire and forget — returns immediately
Etims::queueInvoice($invoice);

// Your application continues while KRA processes in the background.
// Listen to events to know the outcome:
```

Listen to the outcome events in your `EventServiceProvider`:

```php
protected $listen = [
    \Flavytech\Etims\Events\InvoiceSubmitted::class => [
        \App\Listeners\GenerateKraReceipt::class,    // generate receipt PDF
        \App\Listeners\UpdateOrderStatus::class,     // mark order as fiscalized
    ],
    \Flavytech\Etims\Events\InvoiceFailed::class => [
        \App\Listeners\AlertOperationsTeam::class,   // notify via Slack/email
        \App\Listeners\FlagOrderForReview::class,    // mark in your system
    ],
    \Flavytech\Etims\Events\InvoiceQueued::class => [
        \App\Listeners\ShowPendingStatus::class,     // update POS UI immediately
    ],
];
```

### Validating a KRA PIN

```php
$result = Etims::validatePin('P000000000B');

if ($result->isValid()) {
    echo "Buyer: {$result->taxpayerName}";
} else {
    // PIN not found in KRA registry
    return back()->withError('Invalid buyer PIN. Please verify and try again.');
}
```

### Checking Invoice Status

```php
$status = Etims::getInvoiceStatus('INV-2024-001');

if ($status->isSuccessful()) {
    echo "Receipt: {$status->receiptNumber}";
} elseif ($status->isPending()) {
    echo "KRA is still processing this invoice.";
}
```

### Working with Failed Invoices

```php
// Get all permanently failed invoices for review
$failedInvoices = Etims::failedInvoices();

foreach ($failedInvoices as $invoice) {
    echo "{$invoice->invoice_number}: {$invoice->failure_reason}";
}

// Re-queue a failed invoice after fixing the issue
Etims::retryFailedInvoice($invoice->id);
```

---

## Invoice Types

| Code | Description | Use Case |
|---|---|---|
| `S` | Sale | Standard sales invoice |
| `R` | Credit Note | Refund / reversal of a sale |
| `D` | Debit Note | Additional charge on a sale |

## Tax Type Codes

| Code | Description | VAT Rate |
|---|---|---|
| `A` | Standard rated | 16% |
| `B` | Zero rated | 0% |
| `C` | VAT exempt | N/A |
| `D` | Non-VATable | N/A |
| `E` | Excisable (with VAT) | 16% + Excise |

## Payment Type Codes

| Code | Description |
|---|---|
| `CASH` | Cash payment |
| `CREDIT` | Credit terms |
| `MPESA` | M-Pesa mobile money |
| `BANK` | Bank transfer |
| `CHEQUE` | Cheque |
| `OTHER` | Other payment methods |

---

## Multi-Tenant SaaS Setup

When serving multiple KRA-registered businesses from one Laravel installation:

**Step 1:** Enable multi-tenancy in config:

```php
// config/etims.php
'multi_tenancy' => [
    'enabled'         => true,
    'tenant_resolver' => \App\Services\EtimsTenantResolver::class,
],
```

**Step 2:** Implement the `TenantResolverContract`:

```php
use Flavytech\Etims\Contracts\TenantResolverContract;

class EtimsTenantResolver implements TenantResolverContract
{
    public function resolve(): array
    {
        // Resolve from your tenancy system
        $tenant = app('currentTenant'); // e.g. spatie/laravel-multitenancy

        return [
            'pin'           => $tenant->kra_pin,
            'branch_id'     => $tenant->etims_branch_id,
            'device_serial' => $tenant->etims_device_serial,
            'secret'        => decrypt($tenant->etims_secret), // store encrypted!
            'mode'          => $tenant->etims_mode,            // per-tenant sandbox/prod
        ];
    }

    public function tenantId(): string|int
    {
        return app('currentTenant')->id;
    }
}
```

**Step 3:** Bind it in your `AppServiceProvider`:

```php
$this->app->bind(
    \Flavytech\Etims\Contracts\TenantResolverContract::class,
    \App\Services\EtimsTenantResolver::class
);
```

That's it. Every SDK call now automatically uses the correct credentials for the active tenant.

---

## Testing

The SDK provides a first-class fake for testing without any real HTTP calls.

```php
use Flavytech\Etims\Facades\Etims;

beforeEach(function () {
    Etims::fake();
});

it('fiscalizes an order on checkout', function () {
    $order = Order::factory()->create(['total' => 11600]);

    $this->post("/orders/{$order->id}/checkout");

    Etims::assertInvoiceSubmitted("INV-{$order->id}");
});

it('queues the invoice for background processing', function () {
    Queue::fake();

    Etims::queueInvoice(makeTestInvoice('INV-001'));

    Etims::assertInvoiceQueued('INV-001');
});

it('handles KRA downtime gracefully', function () {
    Etims::fake()->failWith(
        new \Flavytech\Etims\Exceptions\EtimsApiException('KRA is down', 503)
    );

    // Your app should still return 200 — it queues for retry
    $this->post('/checkout/1')->assertStatus(202);
});

it('validates correct invoice data', function () {
    $fake = Etims::fake();

    $this->post('/checkout/1');

    $fake->assertSubmittedMatching(
        fn($invoice) => $invoice->totalAmount === 11600.00
            && $invoice->invoiceType === 'S'
    );
});

it('rejects an invalid buyer PIN', function () {
    Etims::fake()->withInvalidPins(['P999999999Z']);

    $response = Etims::validatePin('P999999999Z');

    expect($response->isValid())->toBeFalse();
});
```

### Stubbing Specific Responses

```php
use Flavytech\Etims\DTOs\InvoiceResponseDTO;

$stubbedResponse = InvoiceResponseDTO::fromKraResponse([
    'resultCd' => '000',
    'resultMsg' => 'OK',
    'data' => [
        'rcptNo'    => 'RCPT-MY-TEST',
        'qrCodeUrl' => 'https://test.kra.go.ke/qr/test',
    ],
]);

Etims::fake()->respondTo('INV-SPECIFIC-001', $stubbedResponse);

$response = Etims::submitInvoice($invoice); // returns RCPT-MY-TEST
```

---

## Running the SDK Test Suite

```bash
composer test
composer test-coverage
composer analyse   # PHPStan static analysis
composer format    # PHP CS Fixer
```

---

## Queue Worker Setup

Run a dedicated worker for the eTIMS queue in production:

```bash
# Supervisor config for dedicated eTIMS worker
php artisan queue:work redis \
    --queue=etims \
    --tries=5 \
    --backoff=10,30,60,120,300 \
    --timeout=60 \
    --sleep=3
```

For failed job monitoring:

```bash
# View failed eTIMS jobs
php artisan queue:failed | grep etims

# Retry all failed jobs
php artisan queue:retry all
```

---

## Architecture Overview

```
Facade (Etims::)
    └── EtimsManager         (orchestration, idempotency, events, multi-tenancy)
            └── EtimsClient  (API contract implementation)
                    └── EtimsHttpClient  (HTTP, auth tokens, retries, logging)
                                └── KRA Gava Connect API

Async path:
    Etims::queueInvoice() → SubmitInvoiceJob → Queue Worker → EtimsClient
```

---

## Error Handling Reference

| Exception | Cause | Retryable |
|---|---|---|
| `EtimsApiException` | KRA API error or network failure | Depends on HTTP status |
| `EtimsAuthException` | Invalid credentials or expired token | No — fix credentials |
| `EtimsValidationException` | Invalid DTO data (client-side) | No — fix data |
| `EtimsIdempotencyException` | Duplicate invoice detected | No — already submitted |
| `EtimsConfigException` | Missing or invalid SDK config | No — fix config |

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Contributions welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## License

MIT. See [LICENSE](LICENSE).

---

> Built with care for the Kenyan and East African developer ecosystem. 🇰🇪
