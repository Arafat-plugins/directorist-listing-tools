# Social Login Tab — Documentation

This document describes the **Social Login** tab in Directorist Listing Tools: what it does, when it appears, how credential checks work, and how to fix common issues.

---

## Table of contents

1. [Overview](#overview)
2. [When the tab appears](#when-the-tab-appears)
3. [Where to find it](#where-to-find-it)
4. [What the tab does](#what-the-tab-does)
5. [How the checks work (behind the scenes)](#how-the-checks-work-behind-the-scenes)
6. [Result meanings](#result-meanings)
7. [Common issues and fixes](#common-issues-and-fixes)
8. [Technical reference](#technical-reference)

---

## Overview

The **Social Login** tab runs **real-time credential checks** for the **Directorist Social Login** extension. It does **not** only check the format of the Client ID or App ID. It calls **Google’s and Facebook’s APIs** with your stored credentials and interprets the **actual JSON error responses** to tell you:

- Whether the **Client ID / App ID really exists** (not just “looks like” one).
- Whether the credential is **valid but misconfigured** (e.g. redirect URI or domain not added).
- Whether the app is **Live** or in **Development mode** (Facebook).

So you get **real errors from the providers**, not guesses from format or HTML.

---

## When the tab appears

The Social Login tab **only shows** when the **Directorist Social Login** extension is active:

- Plugin slug: `directorist-social-login/directorist-social-login.php`
- Or the class `Directorist_Social_Login` exists.

If the extension is deactivated, the tab is hidden from the Listing Settings tab bar and the submenu.

---

## Where to find it

1. In **WordPress Admin**: **Directorist** → **Listing Settings** (or the main Directorist menu item for listings).
2. In the **tab bar** at the top of the Listing Settings page, click **Social Login**.
3. The page shows:
   - Stored **Google Client ID** and **Facebook App ID** (or “not set”).
   - A **“Run live credential check”** button (and an automatic check on page load).
   - **Results** for Google and Facebook with status and detail text.

---

## What the tab does

On load (and when you click **Run live credential check**), the plugin:

1. Reads the **current credentials** from Directorist options:
   - **Google:** `google_api` (Client ID)
   - **Facebook:** `atbdp_fb_app_id` (App ID)
   - **Social Login enabled:** `enable_social_login`

2. Sends **real HTTP requests** to Google and Facebook (see below).

3. Parses the **JSON responses** and maps them to:
   - **Valid** — credential exists and is usable (or valid with a small config step).
   - **Warning** — credential is valid but something is missing (e.g. redirect URI / domain).
   - **Error** — credential does not exist, is deleted, or is misconfigured (e.g. Development mode).

4. Displays one card per provider with:
   - **Badge:** Working / Action needed / Error
   - **Message:** Short summary (e.g. “Google Client ID is valid and recognised” or “Facebook says: this App ID does not exist”).
   - **Detail:** Exact next steps or the provider’s error message.

No “hello” or placeholder logic — only real API calls and real error codes.

---

## How the checks work (behind the scenes)

### Google

**Goal:** Know if the **Client ID exists** in Google Cloud Console (not just format).

**Method:** Use the **OAuth 2.0 Token endpoint** (`https://oauth2.googleapis.com/token`):

1. **POST** with:
   - `grant_type=authorization_code`
   - `client_id` = your stored Google Client ID
   - `client_secret` = a **fake** value (e.g. `dlt_test_intentionally_invalid`)
   - `code` = a **fake** authorization code
   - `redirect_uri` = same as the one the Social Login plugin uses (dashboard with `?directorist-google-login`)

2. **Interpret the JSON response:**
   - `error === "invalid_client"` → **Client ID does not exist** (deleted or wrong). Google returns this **only** when the `client_id` is not recognised.
   - `error === "invalid_grant"` → **Client ID exists**. Google recognised the client and rejected the fake code — expected.
   - `error === "redirect_uri_mismatch"` → **Client ID exists**, but the `redirect_uri` (or JavaScript origin) is not in the OAuth client’s authorised list.
   - `error === "unauthorized_client"` → **Client ID exists**; app type or config issue.

So: **real-time check** = one server-side POST to Google; **valid vs invalid** is decided by Google’s own `error` field, not by format or HTML.

**Redirect URI used in the test:**  
Same as Directorist Social Login: dashboard URL with `?directorist-google-login` (from `ATBDP_Permalink::get_dashboard_page_link()` when available).

---

### Facebook

**Goal:** Know if the **App ID exists** and, if possible, whether the app is **Live** or in **Development** mode.

**Method:** Two steps.

#### Step 1 — Does the App ID exist?

Use the **App access token** endpoint:

- **GET** `https://graph.facebook.com/oauth/access_token`
  - `client_id` = your stored App ID  
  - `client_secret` = a **fake** value  
  - `grant_type` = `client_credentials`

**Interpret the JSON response:**

- `error.code === 101` (or message like “application does not exist” / “invalid app id”) → **App ID does not exist**.
- Any other error (e.g. `code === 1`, “Invalid client_secret”) → **App ID exists**; Facebook recognised the app and rejected the wrong secret.

So: **real-time check** = one server-side GET; **valid vs invalid** is decided by Facebook’s error code/message.

#### Step 2 — Is the app Live or Development?

- **GET** `https://graph.facebook.com/v18.0/{APP_ID}?fields=id,name`  
  (no token — public app info.)

- If the response contains `id` and `name` for your App ID → app is **public/Live**.
- If the response is an error (e.g. permissions, “not set up”, “development”) → app is in **Development mode** or not fully set up.

So: **real-time check** = one more GET; **Live vs Development** is inferred from whether public app info is returned.

---

## Result meanings

### Google

| Badge        | Meaning |
|-------------|--------|
| **Working** | Client ID exists; Google returned `invalid_grant` or `unauthorized_client` for the test (expected). You may still need to add your site to **Authorised JavaScript origins**. |
| **Action needed** | Client ID exists but Google returned `redirect_uri_mismatch`. Add the login URI and your site origin in Google Cloud Console. |
| **Error**   | Google returned `invalid_client` — **Client ID does not exist** (wrong, deleted, or never created). |

### Facebook

| Badge        | Meaning |
|-------------|--------|
| **Working** | App ID exists and public app info returned (app is Live). |
| **Action needed** | App ID exists but we couldn’t confirm Live status; check App Domains and Live mode. |
| **Error**   | Either: (1) **App ID does not exist** (Facebook error 101), or (2) **App is in Development mode** (only admins/testers can log in). |

---

## Common issues and fixes

### Google

- **“Client ID does not exist”**  
  Copy the Client ID again from **Google Cloud Console** → **APIs & Services** → **Credentials** → **OAuth 2.0 Client IDs**. Ensure you’re using the correct project and the ID wasn’t deleted.

- **“Client ID is valid but the login URI / JavaScript origin is not authorised”**  
  In the same OAuth client in Google Cloud Console:
  - Add your **Authorised redirect URIs** (e.g. `https://yoursite.com/dashboard/?directorist-google-login`).
  - Add your **Authorised JavaScript origins** (e.g. `https://yoursite.com`).

### Facebook

- **“App ID does not exist”**  
  Use the App ID from **developers.facebook.com** → **Your Apps** → select the app → **Dashboard** (App ID). Ensure the app wasn’t removed.

- **“App is in Development mode”**  
  In **developers.facebook.com** → Your App → use the **“In development”** toggle to switch the app to **Live**. Only then can normal visitors use “Continue with Facebook”.

- **“Domain not in App Domains”**  
  In **developers.facebook.com** → Your App → **Settings** → **Basic** → **App Domains**, add your site’s domain (e.g. `yoursite.com`).

---

## Technical reference

### Files

| File | Purpose |
|------|--------|
| `includes/class-social-login-diagnostics.php` | Main class: credential checks, AJAX handler, page render. |
| `includes/helpers.php` | `dlt_is_social_login_active()` and adding the Social Login tab to the tab bar. |
| `includes/class-admin-menu.php` | Registering the Social Login submenu and `render_social_login_page()`. |
| `includes/class-plugin-loader.php` | Loading and instantiating the diagnostics class only when Social Login is active. |
| `assets/admin.css` | Styles for `.dlt-sld-*` (cards, badges, status, notices). |

### Directorist options used

Credentials are read via Directorist’s options (or `atbdp_option`):

- `google_api` — Google OAuth 2.0 Client ID
- `atbdp_fb_app_id` — Facebook App ID
- `enable_social_login` — whether Social Login is enabled

### AJAX

- **Action:** `dlt_social_login_credentials_check`
- **Nonce action:** `dlt_social_login_check_nonce`
- **Response:** JSON `{ success: true, data: { google: {...}, facebook: {...}, enable_social_login: bool } }`  
  Each provider object: `{ ok: true|false|'warning', status: string, message: string, detail: string }`.

### Capability

Only users who can access Listing Tools (`manage_options` or `dlt_manage_listing_tools`) can open the Social Login tab and run the check.

---

## Summary

- The **Social Login** tab appears only when **Directorist Social Login** is active.
- It runs **live checks** by calling **Google’s token endpoint** and **Facebook’s token + graph endpoints** with your stored Client ID / App ID.
- **Google:** validity is determined by the `error` field in the token response (`invalid_client` = ID does not exist).
- **Facebook:** validity is determined by the error code in the token response (e.g. 101 = App ID does not exist); a second request checks Live vs Development.
- Results are **real** (from the providers’ APIs), not based on format or HTML parsing. The tab tells you exactly which credential is wrong and what to fix.
