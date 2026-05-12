# Case Engine — RBAC Flow & Access Controls

## Overview

Access is controlled by **roles** and **capabilities**. We define custom capabilities, assign them to roles, and check them at every entry point (admin menu, admin actions, front-end dashboard, AJAX).

---

## Status: What’s Done vs What’s Needed

### Done

| Area | Implementation |
|------|-----------------|
| **Roles & capabilities** | Administrator, Case Manager, Client; all caps defined and assigned on activation. |
| **Admin menu** | Shown only to users with `case_engine_view_sessions` (Admin, Case Manager). |
| **Admin page load** | `current_user_can( VIEW_SESSIONS )` — 403 if no permission. |
| **Create case from session** | Link and action require `case_engine_create_case_from_session`; session list shows “Create case” only with that cap. |
| **Client dashboard** | Requires `case_engine_view_own_cases`. Guests see “Log in”; no cap = “You do not have permission”. |
| **Client: own cases only** | `get_own_cases()` and `get_case_for_user()` restrict by `session.user_id = current user`. Clients never see other users’ cases. |
| **Intake** | No role check (anonymous); session tied to `user_id` on save/restore; restore/save/payment/complete enforce session belongs to current user. |

### Needed (and implemented below)

| Area | Requirement |
|------|-------------|
| **Admin: view all cases** | List all cases (Admin/Case Manager) with `case_engine_view_cases`; separate from client “own” view. |
| **Admin: manage cases** | View case detail and edit case status/details with `case_engine_edit_cases`. |
| **Permission checks everywhere** | Every admin case view and action must check the appropriate cap (VIEW_CASES, EDIT_CASES). |

---

## 1. Roles

| Role | Who | Purpose |
|------|-----|---------|
| **Administrator** | Site owner / IT | Full access: all Case Engine features + WordPress admin. |
| **Case Manager** | Staff handling divorces | View intake sessions and cases; create case from session; edit case status. No plugin settings or user/role management. |
| **Client** | End user (petitioner) | View only **own** cases and dashboard (when logged in). Intake can be done **without** login; case is linked to `user_id` when known. |

---

## 2. Custom Capabilities

All Case Engine capabilities use the prefix `case_engine_` so they are clearly scoped.

| Capability | Who has it | What it controls |
|------------|------------|-------------------|
| `case_engine_view_sessions` | Admin, Case Manager | See Case Engine admin menu and list of intake sessions. |
| `case_engine_create_case_from_session` | Admin, Case Manager | Use "Create case" for a session that has no case yet. |
| `case_engine_view_cases` | Admin, Case Manager | List/view all cases (future: Cases submenu, case list). |
| `case_engine_edit_cases` | Admin, Case Manager | Change case status, edit case details (future). |
| `case_engine_view_own_cases` | Admin, Case Manager, Client | View **own** cases only (dashboard). For Client role this is the only case-related cap. |
| `case_engine_manage_settings` | Admin only | Case Engine settings (future). |

**Summary:**  
- **Administrator** gets all `case_engine_*` capabilities (we add them on activation).  
- **Case Manager** gets: `view_sessions`, `create_case_from_session`, `view_cases`, `edit_cases`, `view_own_cases`.  
- **Client** gets only: `view_own_cases` (and default `read`).

---

## 3. Where We Enforce (Access Control Points)

| Area | Check | Purpose |
|------|--------|---------|
| **Admin menu** | `case_engine_view_sessions` | Show "Case Engine" only to users who can at least view sessions. |
| **Admin page (load)** | `case_engine_view_sessions` | If user has no cap, show "You don’t have permission" and exit. |
| **Create case from session** | `case_engine_create_case_from_session` | Before creating case from session (link or GET param), require this cap. |
| **Admin: All cases list** | `case_engine_view_cases` | Show "All cases" table only to Admin/Case Manager. |
| **Admin: View/Manage case** | `case_engine_view_cases` / `case_engine_edit_cases` | View case detail; edit status (form) only with `edit_cases`. |
| **Client dashboard (front-end)** | `case_engine_view_own_cases` | Allow access to dashboard; list only cases where `user_id` = current user (or by session/email for guests). |
| **Intake (AJAX save/payment)** | No role check | Intake is anonymous (session + nonce). No login required. |
| **REST API (if added later)** | Per-endpoint cap | e.g. list cases → `case_engine_view_cases` or `case_engine_view_own_cases`. |

---

## 4. Implementation Flow

1. **On plugin activation**
   - Add all `case_engine_*` capabilities to the **Administrator** role.
   - Create **Case Manager** role (if it doesn’t exist) and assign: `case_engine_view_sessions`, `case_engine_create_case_from_session`, `case_engine_view_cases`, `case_engine_edit_cases`, `case_engine_view_own_cases`.
   - Create **Client** role (if it doesn’t exist) and assign: `read`, `case_engine_view_own_cases`.

2. **Admin menu**
   - Register the Case Engine menu with capability `case_engine_view_sessions` (instead of `manage_options`).

3. **Admin page**
   - At top of `case_engine_admin_page()`: if not `current_user_can('case_engine_view_sessions')`, `wp_die()` or redirect with "no permission".
   - Before creating case from session: require `case_engine_create_case_from_session`.

4. **Client dashboard (Phase 5)**
   - When rendering dashboard: require `case_engine_view_own_cases`.
   - Query cases where `user_id` = current user (and optionally match by session/email for guest flows).

5. **Future**
   - Cases list / edit UI: check `case_engine_view_cases` and `case_engine_edit_cases`.
   - Settings page: check `case_engine_manage_settings`.

---

## 5. Linking Clients to Cases

- **Intake:** Can be done without login; `az_intake_sessions.user_id` is 0.
- **When we have a logged-in user:** Set `user_id` on the session when they start or continue intake (optional enhancement).
- **Client dashboard:** "Own" cases = cases where `az_cases` is linked (e.g. via session) to the current user. For now we can link by: `az_intake_sessions.user_id` = current user and case came from that session. So "my cases" = cases whose `intake_session_id` points to a session with `user_id` = current user.

This way RBAC and "own cases" stay consistent as we add dashboard and login.

---

## 6. How to assign roles (WordPress)

- **Users → Add New** (or edit a user): set **Role** to **Case Manager** or **Client**.
- **Administrator** already has all Case Engine capabilities; no change needed.
- Case Managers see the **Case Engine** menu and can use "Create case" on sessions.
- Clients do not see the Case Engine admin menu; they see the **Client Dashboard** (front-end) where we enforce `case_engine_view_own_cases` and list only **own** cases (cases whose intake session has `user_id` = current user).

**Client Dashboard page:** The page at `/client-dashboard/` should contain the shortcode `[az_client_dashboard]`. New installs get this automatically. If the page was created before this was added, edit the page and add `[az_client_dashboard]` to show the cases list. Guests see “Log in”; users without the cap see “You do not have permission”; users with `case_engine_view_own_cases` see their cases (or “You have no cases yet” if none are linked to their account).
