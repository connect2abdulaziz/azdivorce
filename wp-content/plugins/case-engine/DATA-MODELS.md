# Case Engine — Data Models

All tables use the WordPress prefix (e.g. `FVp_`).

## Tables

| Table | Purpose |
|-------|---------|
| **az_intake_sessions** | One row per intake; stores `answers` JSON and `case_id` once a case is created. |
| **az_cases** | One row per case; links to `intake_session_id`; holds county, has_children, filing_date, role, status (e.g. paid). |
| **az_parties** | One row per person: petitioner, respondent, child; `case_id`; full_name, address, phone, email, dob, relationship (for children). |
| **az_intake_answers** | Structured key/value per case: `case_id`, `session_id`, `question_key`, `answer_value` (copied from session when case is created). |
| **az_document_states** | Per-case document tracking: `case_id`, `document_type`, `file_path`, `file_hash`, `version`, `status` (for Phase 4 PDF). |
| **az_payments** | One row per payment; `case_id`, amount, currency, status, stripe_payment_id, paid_at. |
| **az_audit_logs** | Action log: action, entity_type, entity_id, user_id, details (JSON), created_at. |

## Flow (graceful: data in tables as soon as Review is completed)

1. **Screens 1–9:** Data is saved into **az_intake_sessions** (answers JSON) on each Continue.
2. **Screen 10 (Review & Confirmation):** When the user clicks **Continue** after confirming, the handler:
   - Saves the session (merged answers, current_screen = 11).
   - Calls **create_from_session( $session_key, [ 'case_status' => 'pending_payment' ] )** so that **immediately**:
     - **az_cases** gets one row (status `pending_payment`).
     - **az_parties** gets petitioner, respondent, children.
     - **az_intake_answers** gets all key/value rows.
     - **az_intake_sessions** is updated with `case_id` and status `pending_payment`.
   - So structured data exists **before** payment; no dependency on the Payment button.
3. **Screen 11 (Payment):** When the user clicks **Proceed to Payment**:
   - If the session already has **case_id**, we only **mark_case_paid( case_id )**: update case status to `paid`, insert **az_payments**, update session to `completed` and current_screen 12.
   - If the session has no case_id (e.g. old run or missed step), we call **create_from_session()** with status `paid` (full create + payment).

So: **Continue on Review** → case and parties and intake_answers created; **Proceed to Payment** → case marked paid and session completed.
z