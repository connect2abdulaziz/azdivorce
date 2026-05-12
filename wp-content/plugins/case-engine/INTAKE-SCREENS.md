# Intake Screens — Where They Live & How They Affect the System

## Where the intake is built

- **Plugin:** `wp-content/plugins/case-engine/`
- **Shortcode:** `[az_intake]` — add this to any WordPress page (e.g. **Intake** or **Start your divorce**).
- **Recommended URL:** Create a page titled "Start" or "Intake" and use the shortcode. Example: `/intake/` or `/start/`.

## Where it plays a role / what it affects

| Area | How the intake affects it |
|------|---------------------------|
| **Entry point** | User lands on the page with `[az_intake]`. All 12 screens are one flow; gates stop the user immediately on any contested signal (no further screens). |
| **Case Engine** | Screen 4 creates the **case shell** (county, children, role). Screen 7–9 fill **Parties** and **Children**. All answers are stored as **intake answers** and linked to an intake session (and later to a Case). |
| **Eligibility & automation** | Screens 1–3 and 5–6 are **gates**. If the user selects "No" / "Not sure" / "I don't know" where required, the flow **STOPs**: a stop message is shown and the user cannot proceed. No case is created for contested paths; automation never starts. |
| **Payment** | Screen 11 shows price and triggers **Stripe Checkout**. On successful payment, the system sets **case state → PAID** and unlocks document generation (Phase 4). |
| **Client dashboard** | Screen 12 shows "Next steps" and **redirects to the client dashboard**. There the user will see case status, secure downloads, and payment history (Phase 5). |
| **Admin** | Intake submissions (and created cases) can be listed in **Case Engine** admin (e.g. Cases menu). Stopped intakes are visible but not turned into cases. |

## Principle enforced in the flow

**Eligibility first → details later → payment before documents.**

- Sensitive or detailed info (party names, DOB, address, children) is only collected **after** all uncontested gates (Screens 1–6) are passed.
- Payment is required **before** any document generation or download.

## Screen order (summary)

1. Welcome & Disclosure (checkbox gate)  
2. Eligibility: Agreement check (Gate #1)  
3. Eligibility: Response filed (Gate #2)  
4. Basic case info (case shell)  
5. Issue-specific agreement checks (property/debts; children if applicable; spousal if applicable)  
6. Future dispute acknowledgment (checkbox)  
7. Petitioner info  
8. Respondent info  
9. Children info (if applicable)  
10. Review & confirmation  
11. Payment (Stripe → case state PAID)  
12. Next steps / redirect to dashboard  
