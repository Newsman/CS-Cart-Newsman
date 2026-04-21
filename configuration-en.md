# Newsman Addon for CS-Cart - Configuration Guide

This guide walks you through every setting in the Newsman addon so you can connect your CS-Cart store to your Newsman account and start collecting subscribers, sending newsletters, and tracking customer behavior.

---

## Where to Find the Addon Settings

After installing and activating the addon, the Newsman settings page is available in two places:

- **Admin > Marketing > Newsman** - the main entry, added automatically by the addon.
- **Admin > Add-ons > Manage add-ons > Newsman > gear icon > Settings** - the standard CS-Cart addon settings page; here you'll find a few technical options that are not exposed on the main Newsman page.

All day-to-day configuration happens on the main Newsman page under **Marketing > Newsman**. The sections below describe every setting on that page, top to bottom.

---

## Getting Started - Connecting to Newsman

Before you can use any feature, you need to connect the addon to your Newsman account. There are two ways to do this:

### Option A: Quick Setup with OAuth (Recommended)

1. Go to **Admin > Marketing > Newsman**.
2. Click the **Connect with Newsman** button (also labeled **Reconnect** once a connection already exists).
3. You will be taken to the Newsman website. Log in if needed and grant access.
4. You will be redirected back to a Newsman admin page in CS-Cart where you choose your email list from a dropdown. Select the list you want to use and click **Save**.
5. That's it - your API Key, User ID, List, and Authentication Token are all configured automatically.

### Option B: Manual Setup

1. Log in to your Newsman account at [newsman.app](https://newsman.app).
2. Go to your account settings and copy your **API Key** and **User ID**.
3. In CS-Cart, go to **Admin > Marketing > Newsman**.
4. Paste your **API Key** and **User ID** in the corresponding fields.
5. Click **Save**. A green indicator will confirm the connection is successful.
6. Now select an **Email List** from the dropdown; the list is populated from your Newsman account.
7. Optionally select a **Segment** inside that list.
8. Select a **CS-Cart mailing list** (required) to define which CS-Cart subscribers participate in two-way sync (see below).
9. Click **Save** again.

---

## Reconnect with Newsman OAuth

If you need to reconnect the addon to a different Newsman account, or if your credentials have changed, go to **Admin > Marketing > Newsman** and click the **Reconnect** button at the bottom of the settings page. This starts the same OAuth flow described above and updates your API Key, User ID, List, and Authentication Token with the new credentials.

---

## Settings Page Sections

The **Admin > Marketing > Newsman** page is organized into sections. Each section is described below.

### Account

- **User ID** (required) - Your Newsman User ID. Filled automatically by OAuth.
- **API Key** (required) - Your Newsman API Key. Filled automatically by OAuth.
- **Connection status** - A colored indicator shows whether your API Key and User ID currently authenticate successfully with Newsman:
  - Green dot: Connected to Newsman.
  - Red dot: Could not connect to Newsman. Please check your credentials or try again later.

### General

- **Email List** (required) - The Newsman list where subscribers from this CS-Cart store will be added. The dropdown is populated from your Newsman account.
- **Segment** - Optionally narrow the sync to a specific segment inside the selected list. Leave empty to sync with the whole list.
- **CS-Cart mailing list** (required) - The CS-Cart built-in mailing list used for two-way subscriber sync with Newsman:
  - Only subscribers of the selected CS-Cart mailing list are pushed to Newsman.
  - Newsman subscribe/unsubscribe webhooks affect only this list.
  - If you select **Any list (no restriction)**, subscribe and unsubscribe actions are ignored - pick a list here to enable syncing.
- **Double Opt-in** - When enabled, new subscribers receive a confirmation email from Newsman and are only added to the list after they click the link inside. Recommended for higher deliverability.
- **Send User IP** - When a visitor subscribes or places an order, the addon can send the client IP address to Newsman (helpful for analytics and anti-abuse). If turned off, the addon will use the **Server IP** value below as a fallback.
- **Server IP** - A fallback IP address used when **Send User IP** is disabled. You can usually leave this empty; the addon will detect the server IP automatically.

### Remarketing

- **Enable Remarketing** - Turn on the Newsman remarketing tracker on the storefront (product views, category views, cart updates, checkout, orders).
- **Remarketing ID** - Your Newsman Remarketing ID (copy it from **newsman.app > Integrations > NewsMAN Remarketing**).
- **Remarketing ID Status** - A colored indicator shows whether the Remarketing ID is currently set:
  - Green dot: Remarketing ID is valid.
  - Red dot: Remarketing ID is not set.
- **Anonymize IP** - When enabled, the last octet of the visitor's IP is masked before tracking. Use this to comply with stricter privacy policies.
- **Send Telephone** - When enabled, the customer's phone number (when present on an order or profile) is included in remarketing events.
- **Theme Cart Compatibility** (enabled by default) - Controls how the remarketing tracker detects cart changes:
  - **Enabled**: the tracker polls the Newsman cart endpoint in the background; works on any theme, but adds a lightweight recurring request.
  - **Disabled**: the tracker reads the cart JSON rendered directly inside the mini-cart DOM (no polling). Turn this off only if your theme's mini-cart block re-renders on every cart change - otherwise cart updates may be missed.

### Developer

- **Log Level** - How verbose the addon log files are. Increase this while troubleshooting; reduce it on production.
  - **None**: nothing is logged.
  - **Error**: only errors (default).
  - **Warning**, **Notice**, **Info**, **Debug**: progressively more detail.
  - Logs are written to `var/newsman_logs/` inside your CS-Cart installation.
- **Log Retention (days)** (default 30) - How many days of log files the addon keeps. Older files are cleaned up automatically by the cron job.
- **API Timeout (seconds)** (default 30) - Timeout for individual requests to the Newsman API. Increase if you see timeout errors on slow networks.
- **Enable IP Restriction** - When enabled, remarketing tracking scripts only render for the IP address configured below. Use this while testing a change without affecting real visitors.
- **Developer IP** - The IP address that receives tracking scripts when **Enable IP Restriction** is on.

### Export Authorization

These settings secure the Newsman export endpoints (product feed, coupons export, order history, etc.) that Newsman calls into your store.

- **Authentication Token** - The token Newsman uses to authenticate its calls to your store. Shown masked (`*****XX`). The token is rotated automatically when the API Key, User ID, Email List, or OAuth connection changes - you don't need to manage it manually.
- **Header Name** - Optional custom HTTP header name used on top of the authentication token (alphanumeric, separated by hyphens). Also set the same value in **newsman.app > E-Commerce > Coupons > Authorisation Header name** and in the corresponding Feed > Header Authorization fields.
- **Header Key** - Optional value for the custom HTTP header defined above (alphanumeric, separated by hyphens). Also set the same value in **newsman.app > E-Commerce > Coupons > Authorisation Header value** and the Feed equivalents.

---

## Saving Changes

Scroll to the bottom of the Newsman settings page and click **Save**. After saving, clear the CS-Cart cache via **Admin > Administration > Storage > Clear Cache** so that any template changes (for example, remarketing scripts being enabled) are picked up by the storefront.

---

## Addon Add-ons > Manage add-ons Settings

A few extra options are exposed on the standard CS-Cart addon settings page (accessible via **Admin > Add-ons > Manage add-ons > Newsman > gear icon > Settings**). These mirror the options on the main Newsman page and are kept in the CS-Cart storage for addon-level settings access by hooks:

- **API Key**, **User ID**, **CS-Cart mailing list to sync**
- **Double Opt-in**, **Send User IP**
- **Enable Remarketing**, **Anonymize IP**, **Send Telephone**, **Theme Cart Compatibility**
- **Log Level**, **API Timeout (seconds)**, **Log Retention (days)**
- **Export Auth Header Name**, **Export Auth Header Key**
- **Use Developer IP**, **Developer IP Address**

You generally do not need to edit anything here - it's safer to edit settings on the main Newsman page under **Marketing**.

---

## Troubleshooting

- **"Could not connect to Newsman"** - Double-check the API Key and User ID. If they are correct but the error persists, click **Reconnect** to run the OAuth flow again.
- **Subscribe/unsubscribe from the newsletter doesn't reach Newsman** - Make sure a **CS-Cart mailing list** is selected in the General section. The sync is intentionally restricted to a single CS-Cart mailing list, so newsletter subscribers added to other lists are skipped.
- **Remarketing scripts don't appear on the storefront** - Confirm **Enable Remarketing** is on, the **Remarketing ID** is set (green status), and the CS-Cart template cache has been cleared.
- **Need more detail in logs** - Set **Log Level** to **Debug** temporarily; log files live in `var/newsman_logs/`.

---

## Storefronts and Multi-Vendor

If your CS-Cart installation uses storefronts (or Multi-Vendor Plus / Ultimate), Newsman is configured per storefront. Open each storefront's admin and configure the Newsman addon separately. Sync and remarketing are scoped to the storefront where they originate, so each storefront can be attached to a different Newsman list.
