# Installation checklist

What has to be true in Quaderno and in Magento before the module goes live. Go through it in a
Quaderno **sandbox** account first, then repeat the Quaderno part in the live account.
Decisions marked *(adviser)* are tax decisions: agree them with your asesor / gestor.

## 1. Is the shop in scope?

- [ ] The seller is **not** in País Vasco or Navarra (they use TicketBAI, not Verifactu).
- [ ] The seller is **not** an SII taxpayer (SII taxpayers are outside Verifactu).
- [ ] The seller is **not** established in Canarias, Ceuta or Melilla, and does not apply
      recargo de equivalencia: not supported in v0.1.

## 2. Quaderno

- [ ] Paid Quaderno plan; the account's NIF and business name match the AEAT census exactly.
- [ ] The company's electronic certificate (PKCS#12) uploaded.
- [ ] Verifactu activated. **It cannot be switched off again**: test in a sandbox account first.
- [ ] A unique number prefix (series) per document type: invoices, receipts, credit notes.
- [ ] Selling to consumers: receipts (simplified invoices) enabled, with the threshold
      *(adviser)*: €400 in general, up to €3,000 for retail sales (RF art. 4).
- [ ] Mail from `noreply@quadernoapp.com` is not filtered. Its "[Action required] Review pending
      documents" email is the only signal outside the API that a document stayed a draft (see 5).

## 3. Magento: module configuration

**Stores › Configuration › Sales › Verifactu**

- [ ] Environment, API URL and private API key of the right account (sandbox first).
- [ ] *Send invoices*: on capture (default, only paid invoices) or on every invoice *(adviser)*.
- [ ] *Simplificada threshold* equals the receipt threshold in Quaderno.
- [ ] *IVA mapping*: one row for every product tax class used in orders **and** for the
      shipping tax class, each with its Spanish IVA rate.

## 4. Magento: shop settings

- [ ] Catalog prices include IVA, and the tax rules charge 0 % to Canarias, Ceuta, Melilla,
      EU B2B with a valid VAT number, and exports *(adviser)*.
- [ ] Checkout has a NIF / VAT ID field (billing address `vat_id`).
- [ ] The NIF is **mandatory** for Spanish orders at or above the simplificada threshold. Without
      it the module blocks the invoice: a simplified invoice is not allowed there.
- [ ] The NIF is **mandatory for every order** with a billing postcode in Canarias, Ceuta or
      Melilla (35xxx, 38xxx, 51xxx, 52xxx), at any amount: these sales need a full invoice.
      Without it the module blocks the invoice.
- [ ] The billing address requires street, city, postcode and country (Quaderno keeps an
      invoice without them as a draft; the module blocks it first).
- [ ] EU B2B: VIES validation on (Stores › Configuration › Customers › Customer Configuration ›
      Create New Account Options › Enable Automatic Assignment to Customer Group). Only
      "Group for Valid VAT ID - Intra-Union" may be the 0 % B2B group; "Group for Invalid VAT
      ID" and "Validation Error Group" must **not** be. Quaderno does not check VAT numbers and
      takes the 0 % as sent.
- [ ] Magento's own invoice and credit memo emails to customers are disabled (Stores ›
      Configuration › Sales › Sales Emails › Invoice / Credit Memo › Enabled = No), or their
      templates no longer look like an invoice. They carry the Magento number, not the Verifactu
      one, and are sent before Quaderno issues the invoice; an invoice has one original (RF
      art. 14.1). The same applies to "Print Invoice" in the customer account and the admin.
      The legal invoice is Quaderno's (PDF and AEAT link in the customer's order view).
- [ ] Decide how the customer receives Quaderno's invoice. Quaderno emails documents created
      through the API only when **Settings › Document preferences › Billing › "Autosend all
      automatic invoices and credit notes"** is on (off by default); "Attach all invoices and
      credit notes as PDF in your emails" adds the PDF. With it off, the customer finds the PDF
      and the AEAT link in the order view only. Check the email template and language in
      Settings › Email templates.
- [ ] Gift cards: not supported in v0.1. Orders paid partly with a gift card are blocked
      (totals do not add up), and the module cannot tell a multi-purpose card sold as a
      product from an ordinary product *(adviser before selling gift cards)*.

## 5. Running

- [ ] Magento cron runs every minute (`bin/magento cron:run`): the job `verifactu_recheck`
      retries failed sends and refreshes the AEAT state.
- [ ] The queue consumer `verifactu.document.send` runs: through cron with the cron consumers
      runner (the Magento default; if `app/etc/env.php` has a `cron_consumers_runner.consumers`
      allow-list, add `verifactu.document.send`), or as a supervised
      `bin/magento queue:consumers:start verifactu.document.send`. Without it every document
      stays `queued` and nothing reaches Quaderno.
- [ ] Someone checks **Sales › Operations › Verifactu Documents** and the admin notifications:
      `blocked` and `draft` rows need the customer data fixed, then **Resend**. On a Quaderno
      "Review pending documents" email, filter the grid by status `draft`.

## 6. Before going live

- [ ] In the sandbox: a B2C order below the threshold (receipt), a B2B order with NIF (invoice),
      a full credit memo; each reaches `issued` with a verification link.
- [ ] Switch environment, API URL and key to the live account; place one real low-value order
      and check the document in Quaderno and its AEAT state.

## Not covered by v0.1

One credit memo per invoice; adjustment refunds and fees; gift cards; a full invoice replacing
an earlier simplified invoice (do it by hand in Quaderno); webhooks (the AEAT state is polled).
