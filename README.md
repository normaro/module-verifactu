# Verifactu for Quaderno (Magento 2)

Sends Magento invoices and credit memos to [Quaderno](https://quaderno.io), a certified
Verifactu provider, so that each sale in Spain gets a full invoice (factura completa), a receipt
(factura simplificada) or a credit note (factura rectificativa) registered with the AEAT.
The shop does not become an invoicing-software (SIF) producer: issuing the invoices and sending
the records to the AEAT is done by Quaderno.

**A paid Quaderno account with the Verifactu integration enabled is required.** Verifactu
cannot be switched off again in a Quaderno account: test in a Quaderno sandbox first.

Status: **v0.1, pre-release.** Tested against the Quaderno sandbox; not yet used in production.

## Requirements

- Magento Open Source / Adobe Commerce **2.4.7 or 2.4.8**
- PHP 8.1–8.3 (2.4.7) or 8.2–8.4 (2.4.8)
- Magento cron running, and the queue consumer `verifactu.document.send` (see below)
- A Quaderno account with the Verifactu integration active (sandbox for testing)

Before going live, go through the [installation checklist](docs/install-checklist.md)
(Quaderno account, checkout NIF field, VIES groups, cron and consumer).

## Installation

```bash
composer require normaro/module-verifactu
bin/magento module:enable Normaro_Verifactu
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### The queue consumer must run

Documents are sent by the consumer `verifactu.document.send` (MessageQueue, database
connection, no RabbitMQ needed), never during checkout or an admin save. Either:

- Magento cron (`* * * * * bin/magento cron:run`) with the cron consumers runner enabled (the
  Magento default). If `app/etc/env.php` has a `cron_consumers_runner.consumers` allow-list, add
  `verifactu.document.send` to it; or
- a supervised process: `bin/magento queue:consumers:start verifactu.document.send`.

Cron is needed in both cases: the job `verifactu_recheck` (every 10 minutes) retries failed
sends and fetches the AEAT state. Without a consumer every document stays `queued`.

## Configuration

**Stores › Configuration › Sales › Verifactu**

| Field | Notes |
|---|---|
| Enabled | per store view |
| Environment | Sandbox or Live; the module refuses a live API host in sandbox mode and the reverse |
| API URL | your account URL, e.g. `https://<account>.sandbox-quadernoapp.com/api/` |
| Private API key | stored encrypted |
| Send invoices | **On capture** (default: only paid invoices) or **On invoice** (every invoice) |
| Simplificada threshold, EUR | must equal the receipts threshold in Quaderno; Spanish orders without NIF at or above it are blocked |
| Debug logging | writes every Quaderno request and response to `var/log/verifactu.log` |
| IVA mapping | every product tax class used in orders, and the shipping tax class, mapped to a Quaderno tax code (standard / reduced / exempt) |

Recommended Magento setup:

- catalog prices including IVA; the IVA rate sent is the rate Magento charged on the line;
- a NIF / VAT ID field at checkout (billing address `vat_id`); make it mandatory for Spanish
  orders at or above the simplificada threshold and for every order to Canarias, Ceuta and
  Melilla (postcodes 35, 38, 51, 52);
- EU B2B: VIES validation on, and the "Group for Invalid VAT ID" and "Validation Error Group"
  must not be the 0 % group (Quaderno does not check VAT numbers);
- Magento's own invoice and credit memo emails: disable them (Sales Emails › Invoice / Credit
  Memo) or change their templates; they show the Magento number, not the Verifactu invoice, and
  are sent before Quaderno issues it (the config section shows the same warning);
- do not filter mail from `noreply@quadernoapp.com`: its "Review pending documents" email is the
  only signal about a draft outside the API.

## What it does

- **Invoice → Quaderno invoice or receipt.** Customer name, NIF / VAT number, billing address,
  lines with the amount after discount and the IVA rate, shipping as its own line. Quaderno
  decides invoice vs receipt and assigns series, number and issue date.
- **Credit memo → credit note** linked to the original invoice or receipt.
- Stores the Quaderno number, type, AEAT state, verification link, AEAT QR URL and PDF link;
  shown on the invoice / credit memo page, in the sales grids, in **Sales › Operations ›
  Verifactu Documents** (filter, mass resend) and in the customer's order view.
- **Checks before sending** (the document is `blocked` with the reason and an admin
  notification, nothing is sent):
  - Spanish order without NIF at or above the threshold (no lawful simplificada);
  - order to Canarias, Ceuta or Melilla without NIF (a full invoice is required, RF art. 2.2.d);
  - NIF with a wrong check letter;
  - billing address without street, city, postcode or country;
  - lines that do not add up to the grand total, or a tax class without IVA mapping;
  - a credit memo whose invoice is blocked, a draft or rejected (it waits while the invoice is
    still being sent), a second credit memo of the same invoice, or a credit memo above the
    invoice total.
- **Retries** on network errors and Quaderno 5xx / 429 (5, 15, 60 minutes, then hourly, 6
  attempts); a retry never creates a duplicate.
- **Resend** button after fixing the data.

Data sent to Quaderno: customer name, company, NIF / VAT number, billing address, email, order
lines (description, SKU, amount, IVA rate), payment method, Magento order and invoice numbers.

## Limitations of v0.1

- One credit memo per invoice: Quaderno links only one refund to an invoice through the API.
- Adjustment refunds / fees and gift cards are blocked (lines do not add up).
- Seller established in Canarias (IGIC), recargo de equivalencia, País Vasco / Navarra (TicketBAI)
  and SII taxpayers are not supported.
- No webhooks: the AEAT state is polled by cron.

## Installation service and support

Installation, IVA mapping and a check of the first invoices, and a support subscription for
Magento releases and AEAT changes are offered by the author: see https://normaro.es.

## License

[OSL-3.0](LICENSE). Source and issues: https://github.com/normaro/magento-verifactu
