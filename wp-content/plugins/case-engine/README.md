# Case Engine (AZ Divorce)

WordPress plugin for the **Arizona Divorce Document Automation Platform (AZ MVP)**. Handles intake flow, case lifecycle, and (later) document automation.

## Intake flow (12 screens)

1. **Welcome & Disclosure** — Checkbox gate; must accept to continue.
2. **Eligibility: Agreement Check (Gate #1)** — Yes → continue; No / Not sure → **STOP**.
3. **Eligibility: Response Filed (Gate #2)** — No → continue; Yes / I don't know → **STOP**.
4. **Basic Case Info** — County (default Maricopa), minor children, filing date, role (Petitioner / Joint).
5. **Issue-Specific Agreement Checks** — Property/debts; children (if applicable); spousal maintenance. Any "No" → **STOP**.
6. **Future Dispute Acknowledgment** — Checkbox; must accept to continue.
7. **Party Information (Petitioner)** — Name, address, phone, email, DOB (no SSN).
8. **Party Information (Respondent)** — Name, last known address, phone, email (optional).
9. **Children Information** — Repeatable: name, DOB, relationship (if applicable).
10. **Review & Confirmation** — Summary and confirmation checkbox.
11. **Payment** — Stripe Checkout (placeholder until Phase 3); case state → PAID.
12. **Next Steps** — Message and redirect to client dashboard.

**Principle:** Eligibility first → details later → payment before documents. No sensitive or detailed info until the case is confirmed uncontested.

## Usage

- Add shortcode **`[az_intake]`** to any page (e.g. **Intake** or **Start your divorce**).
- Activate the plugin; the table `{prefix}az_intake_sessions` is created on activation.
- See **INTAKE-SCREENS.md** for where the intake lives and how it affects the system.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- jQuery (included with WordPress)
