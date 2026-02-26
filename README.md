# Restaurant Desktop Sync (Windows + WordPress)

Installable Windows WPF desktop app that polls a WordPress restaurant site for WooCommerce orders + bookings, notifies staff, and prints to Epson-compatible receipt workflows.

## Solution structure

- `RestaurantDesktopApp/` - .NET 8 WPF desktop shell (MVVM UI tabs: Orders, Bookings, Logs, Settings)
- `RestaurantDesktopApp.Core/` - models, polling engine, repositories, auth + retry logic
- `RestaurantDesktopApp.Printing/` - receipt formatting + printer adapter strategy
- `RestaurantDesktopApp.Tests/` - xUnit tests for parsing, detection/idempotency, receipt formatting
- `wordpress-plugin/restaurant-sync-api/` - companion WP plugin exposing normalized booking endpoints

## Features implemented

- HTTPS WordPress connectivity for WooCommerce orders and booking endpoints.
- Configurable auth mode:
  - WooCommerce API key/secret (basic auth over HTTPS)
  - WordPress app password (same header strategy)
- Local encrypted secret storage via DPAPI wrapper.
- SQLite state store for seen/printed IDs and print failure state.
- Polling engine with retry/backoff and idempotent new item detection.
- Desktop popup + optional sound on new orders/bookings.
- Auto print:
  - Orders default 2 copies.
  - Bookings default 1 copy.
- Manual `Test Print`.
- Printing adapter strategy:
  - `WindowsDriver` mode (default)
  - `EpsonEposNetwork` mode (optional, endpoint/IP settings)

## Setup

1. Open `RestaurantDesktop.sln` in Visual Studio 2022+ on Windows.
2. Restore NuGet packages and build.
3. Run `RestaurantDesktopApp`.
4. In **Settings** tab configure:
   - Base URL (HTTPS)
   - Auth mode + credentials
   - Poll interval (5-30s recommended)
   - Printer and printing mode
   - Auto-print toggles and copies
5. Save settings and leave app running.

## WooCommerce credentials

1. WordPress Admin -> WooCommerce -> Settings -> Advanced -> REST API.
2. Create key (Read access enough for pull-only).
3. Put Consumer Key in `Username / Consumer Key`.
4. Put Consumer Secret in `Password / Consumer Secret`.

## WordPress App Password

1. WordPress Admin -> Users -> Profile -> Application Passwords.
2. Create app password.
3. Username = WP username, Password = app password.
4. Set auth mode to `AppPassword`.

## Bookings integration

- If your booking plugin has a REST API, point app logic to that source (implement new `IBookingSource`).
- Fallback plugin included:
  - Copy `wordpress-plugin/restaurant-sync-api` into `/wp-content/plugins/`.
  - Activate **Restaurant Sync API**.
  - Endpoint examples:
    - `GET /wp-json/restaurant-sync/v1/bookings?after=2026-01-01T00:00:00Z`
    - `GET /wp-json/restaurant-sync/v1/bookings/123`

## Printer configuration

### Windows driver mode

- Install Epson printer driver in Windows.
- Set `PrintingMode` to `WindowsDriver`.
- Choose installed printer name.
- Use `Test Print`.

### Epson ePOS network mode

- Set `PrintingMode` to `EpsonEposNetwork`.
- Set printer IP and endpoint path (default `cgi-bin/epos/service.cgi`).
- Use `Test Print`.

## Reliability model

- Local SQLite table `sync_items` tracks each order/booking by `(id, type)`.
- Already seen items are skipped in future polls.
- Print failures are persisted with error text.
- Reprint/retry hooks can use the stored statuses.

## Tests

Run in a machine with .NET SDK installed:

```bash
dotnet test RestaurantDesktopApp.Tests/RestaurantDesktopApp.Tests.csproj
```

## Troubleshooting

- **401/403 from WP**: confirm credentials + HTTPS URL + user permissions.
- **No bookings**: install/enable plugin or switch booking source implementation.
- **Printer not outputting**: verify mode, printer name/IP, and test print first.
- **Duplicate prints**: check SQLite file under `%LOCALAPPDATA%/RestaurantDesktopApp/state.db`.
