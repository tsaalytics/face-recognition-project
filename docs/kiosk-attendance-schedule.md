# Kiosk automation: audit and Step 1

## Step 2: always-on preview and automatic trigger

- Opening the authenticated HR kiosk provisions the existing 15-minute session automatically. Status polling uses a protected GET endpoint; renewal uses the existing CSRF-protected POST start route while the kiosk is visible. Explicit pause clears the session and remains paused across reloads. No public recognition endpoint is added.
- Preview opens automatically through getUserMedia on HTTPS/localhost. The browser may ask for camera permission if it has not already been granted. Denial shows an explicit retry control rather than repeatedly requesting permission.
- The standalone kiosk now scans automatically without a participant checkbox, as requested in the later UI revision. It retains the on-screen HR privacy notice and never fabricates a checkbox consent value. Legacy HR API and enrollment consent controls remain. There is no manual Start Recognition or Scan action.
- A pinned MediaPipe face detector runs locally at most once every 250ms. It is a presence trigger only; employee identification still uses the existing Laravel/FastAPI buffalo_m pipeline. The runtime and model are served locally, not from a third-party CDN. Frame exposure/texture is checked locally; the existing Python quality validation remains authoritative. These checks are neither liveness nor identity proof.
- One well-framed face must remain stable for 1000ms. A standalone request contains one transient JPEG, never a browser identity/mode/timestamp. Recognition is serialized in the browser and protected by a per-device cache lock in Laravel (per-session on the legacy HR API). Use a shared lock-capable cache driver for multi-node deployment.
- After every result, the kiosk waits at least 6500ms and requires an empty frame for 1200ms before another recognition. Remaining in frame, multiple faces, brief detector dropouts, failed requests and unknown faces cannot cause an automatic request loop. Rate-limit Retry-After extends cooldown. Face absence is not identity tracking: the backend still rejects duplicate attendance when a person returns.
- Hidden/unloaded pages stop tracks, release the detector, clear canvas pixels and suppress stale async results. Visibility restoration reloads local detection automatically. Permission revocation and detector loading errors stop scanning and show retry. Jeda Kiosk explicitly stops the camera and server session.
- The clock extrapolates from server time; schedule mode is refreshed from the server every 15 seconds. Backend schedule checks remain authoritative across boundaries. No recognition is attempted while the latest server mode is closed.
- Model/WASM integrity and deployment details are in public/vendor/kiosk-face-detector/README.md. A real webcam/browser and running FastAPI still require live verification; deterministic tests use mock camera/detector outputs, not real participant faces.

## Existing system audit

- The kiosk routes require authentication and the HR role, CSRF on writes, a server session expiry, and rate limits. These controls are preserved.
- The current browser flow starts a 15-minute session manually, requests camera access after consent/action, captures a single transient frame, and sends a chosen attendance type. Automatic camera start, stable-face triggering and cooldown are still pending Step 2.
- FaceExtractionClient calls the internal authenticated FastAPI endpoint. FastAPI loads InsightFace buffalo_m, returns a normalized embedding, and applies quality checks. Matching stays in Laravel against encrypted enrolled embeddings. No model, threshold, API authentication or image-storage behavior changes are needed for Step 1.
- Attendance already has an employee/date/session unique constraint. FaceAttendanceService locks the HR and employee records before reading/writing attendance inside a transaction. Existing HR-approved corrections and leave records remain protected.
- Previously, attendance type came from the browser and checkout always searched today's date. Only shift_start and late_grace_minutes were configured.

## Step 1 implementation

`config/attendance.php` owns the timezone, regular session, on-time cutoff and schedule boundaries. `AttendanceSchedule::current()` resolves server time to local business time and returns the mode, business date, current timestamp and check-in status.

| Asia/Jakarta time | Mode | Attendance date / status |
| --- | --- | --- |
| 00:00:00–04:59:59 | check_out | Previous calendar date |
| 05:00:00–05:59:59 | closed | No attendance write |
| 06:00:00–08:00:00 | check_in | Today; database status present (On Time) |
| 08:00:01–16:59:59 | check_in | Today; database status late |
| 17:00:00–23:59:59 | check_out | Today; retain the check-in status |

- Boundaries operate at second precision, consistent with the existing attendance timestamp storage.
- FaceAttendanceController validates consent and the image only. Browser type, employee_id and timestamps are not accepted as authority; legacy type fields are ignored.
- FaceAttendanceService rejects closed hours before inference and resolves the schedule again after inference and employee locking. This second resolution is authoritative for the saved time and business date.
- Duplicate check-ins remain check-in attempts and are rejected; they never become checkout automatically. Checkout requires a real earlier check-in for the resolved date and cannot create a record when it is missing.
- Checkout after midnight checks approved leave and protected corrections for the previous working date. It does not fall back to a check-in on the current date.
- Database names remain present/late and source remains face_recognition. No migration, data reset or authorization change is required.

## Current staged UI behavior

Step 1 changes backend rules. The existing kiosk's manual session/camera controls and type selector remain until Step 2; choosing a type no longer overrides the server schedule. Step 2 will replace these controls with the automatic camera/scan state machine and expose the current server mode. Recognition/UI refinements and real device verification follow in later steps.

## Tests and scope

AttendanceScheduleTest covers timezone conversion, configurable boundaries and invalid schedules. FaceAttendanceTest covers actual authenticated scan requests with mocked extraction: time boundaries, prior-date checkout, closed-hour AI avoidance, duplicates, forged browser data, absent check-in, inference crossing a boundary, recognition failure, and the existing enrollment/session/leave protections.

This MVP supports one regular work schedule. Multiple shifts, holidays and individual employee schedules are not supported. Closed hours apply every calendar day; a prior-date checkout means the preceding calendar date, without a holiday calendar. The system does not provide liveness detection. Automated tests do not substitute for live camera/FastAPI/model verification.
