# Standalone attendance kiosk

## Setup from HR, operate from a separate tablet

1. In the HR dashboard, open **Perangkat Kiosk** (`/hr/kiosk/devices`).
2. Enter a device name and choose **Terbitkan Kode Aktivasi**. The code is displayed once and expires in 10 minutes.
3. On the tablet, open the application's `/kiosk` URL. Use the same reachable HTTPS origin as the application; browser camera access works on HTTPS or localhost.
4. Enter the activation code. This browser now has access only to kiosk endpoints through a separate device credential. It does not need a dashboard login. If a dashboard account was already logged into this browser, activation logs it out.
5. The kiosk opens its camera preview automatically with browser permission. Stable-face triggering, cooldown and backend schedule checks continue to apply. The standalone UI no longer requires a participant checkbox; HR remains responsible for the notice/consent process described on screen. The standalone request does not fabricate a checkbox consent field; the legacy HR API and enrollment forms retain their own explicit consent validation.

Recognition results appear in a modal. Success shows the server-provided name/time and a late badge when applicable, and closes automatically after 4.5 seconds. Failure offers Coba Lagi, which allows the same person to retry after the existing cooldown and another stable detection. A success never re-arms the same face without departure. The header uses a centered logo without the former title/subtitle.

The operating screen uses `components/kiosk-layout.blade.php` and `kiosk/index.blade.php`, without the HR sidebar, account controls or HR correction links. People needing corrections contact HR rather than accessing HR management from the tablet.

## Device authorization

- `kiosk_devices` is a new additive table; existing users, employees, embeddings and attendance are unchanged.
- HR alone can issue/revoke devices. The table stores hashes of random pairing codes and device credentials. The one-time code flash used for HR display is encrypted in the session; activation input is excluded from flashed old input.
- Activation consumes the code in a transaction and sets a random 64-character credential in an encrypted, HttpOnly, SameSite=Strict cookie scoped to `/kiosk`. HTTPS requests receive a Secure cookie; plain HTTP support is for localhost development only.
- Device access lasts 30 days by default (`config/kiosk.php`). A cookie is a browser-held bearer credential, not hardware attestation; copied credentials are not a substitute for physically securing the tablet. Clear/revoke access when the tablet is lost or reassigned.
- Middleware independently validates device authorization on every kiosk request, even if an HR account is logged in. It never logs in a User or changes the User resolver. A device cookie cannot grant HR/Admin/Employee access.
- `FaceAttendanceService` rechecks the device under a database lock after inference. Revocation/expiry during recognition prevents an attendance write. Locks for standalone recognition are keyed per device, not per web session.
- CSRF, biometric request size limits, no-store headers, session expiry, rate limiting, encrypted embeddings and authenticated FastAPI access remain enabled.

## Management and compatibility

HR can revoke access from the device list at any time. This clears credentials/pending codes without deleting historical attendance or biometric enrollment. Access is checked again on polling and scan requests; an in-progress request is also checked before persistence.

The old `/hr/kiosk` entry redirects to device management. Existing HR-only API routes remain protected for compatibility, but the standalone UI only calls `/kiosk/*` with device authorization. Enrollment, corrections and other dashboard features remain in their existing role areas.

Run the additive migration on other deployments:

```
php artisan migrate --path=database/migrations/2026_11_18_120000_create_kiosk_devices_table.php
npm run build
```

No device is activated automatically by deployment. HR must issue a code for each tablet/browser. Live webcam and FastAPI model verification still need the target device; automated tests verify authorization, cookie scope, single-use expiry, revocation and the scan transaction with mocked extraction.
