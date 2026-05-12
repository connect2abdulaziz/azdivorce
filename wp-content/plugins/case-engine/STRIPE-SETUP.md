# Phase 3: Stripe setup

## 1. Add to `wp-config.php` (above "That's all, stop editing!")

```php
// Stripe (Phase 3) — use test keys for development
define( 'STRIPE_SECRET_KEY', 'sk_test_...' );
define( 'STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
define( 'STRIPE_WEBHOOK_SECRET', 'whsec_...' );
```

Get test keys from: Stripe Dashboard → Developers → API keys.  
Get webhook secret from: Developers → Webhooks → Add endpoint → after creating, click "Reveal" under Signing secret.

## 2. Webhook URL (Stripe Dashboard → Webhooks → Add endpoint)

Use **one** of these:

- **Recommended:** `https://yourdomain.com/?case_engine_stripe_webhook=1`
- Alternative: `https://yourdomain.com/wp-json/case-engine/v1/stripe-webhook`

Events to send: **checkout.session.completed**

## 3. Local testing (ngrok or Stripe CLI)

Stripe must POST to a **public** URL. For local (XAMPP):

- Use [Stripe CLI](https://stripe.com/docs/stripe-cli): `stripe listen --forward-to "http://localhost/legaldivorcedocs/?case_engine_stripe_webhook=1"` and put the printed `whsec_...` in `STRIPE_WEBHOOK_SECRET`, or
- Use ngrok to expose your local site and use that URL in Stripe Dashboard.

## 4. After adding keys

- Intake Screen 11 "Proceed to Payment (Stripe)" will create a Checkout Session and redirect to Stripe.
- After payment, Stripe redirects to Client Dashboard with `?payment=success` and sends a webhook; the webhook marks the case **paid**, inserts a row in `az_payments`, and marks the session completed.
- Idempotency: duplicate webhook events (same `event.id`) are ignored; all events are logged in `az_stripe_events` and `az_audit_logs`.

## 5. Default amount

Default charge is **$99.99** (9999 cents). To change it, use the filter in your theme or a small plugin:

```php
add_filter( 'case_engine_stripe_amount_cents', function( $cents, $case_id ) {
	return 14999; // $149.99
}, 10, 2 );
```
