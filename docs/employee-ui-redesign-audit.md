# Employee UI redesign: audit and directory review

## Step 3: integrated profile forms (current behavior)

- The employee create/edit page now provides one white card with a responsive grid, required labels, field errors, account-link options, and a reusable face-enrollment section. Profile and camera are separate, non-nested forms inside the same card.
- A normal profile submit preserves the original HTML redirect and never calls AI. The optional enrollment submit first saves the profile using JSON negotiation on the existing store/update endpoints, then calls the existing face API. Responses expose only employee business ID, permitted URLs and enrollment version; freshly saved profiles are refreshed to hydrate database defaults.
- On successful profile creation, the browser updates its URL and switches the profile form to PUT for that saved employee before sending images. Failed AI therefore leaves the employee saved and retries update the same employee. An existing page retains its original enrollment version, so stale re-enrollment cannot silently overwrite a concurrent change.
- Camera consent, transient samples, CSRF, HR policy checks, encryption, AI model and safe replacement service remain in place. Enrollment metadata and audit history are displayed without reading raw vectors into the view.
- Main navigation and directory no longer expose a standalone enrollment page. Existing GET bookmarks redirect into employee management/edit; POST/DELETE API routes remain unchanged. Delete returns to the integrated section. The old Blade files are retained as unused historical templates rather than deleting routes or data.
- Automated tests cover optional profile-only creation, profile persistence after AI failure, failed re-enrollment preserving the old encrypted vector, legacy redirects and client request ordering. Live camera/AI verification still requires the actual browser and model service.

## Step 1: existing behavior

- Laravel Blade views use the shared workspace layout and compact list components. HR and Admin directories share AdminMonitoringService filtering and server pagination.
- EmployeePolicy allows HR to create/update employees, Admin and HR to read, and an Employee to read only their own profile. Role middleware separately protects directory routes.
- EmployeeProfileService owns writes and transactions. EmployeeIdGenerator generates business_id from four random digits and YYYYMMDD birth date, with uniqueness checks and database collision handling. employee_number is the existing NIK field; business_id is the displayed employee ID.
- FaceEnrollmentController and FaceEnrollmentService already authorize HR, validate samples and enrollment versions, call FastAPI through FaceExtractionClient, and store encrypted embeddings through EmployeeFaceEmbedding. Extraction requires buffalo_m and a validated 512-dimensional response. Images are decoded in memory rather than saved as files.
- Re-enrollment validates and extracts new samples before a transaction replaces embeddings. Failed extraction leaves the prior enrollment intact. The attendance pipeline uses these existing services/configuration and is outside this change.
- Standalone navigation currently links to hr.enrollment; camera/API routes use hr.faces.*. Keep these links working until the optional enrollment section replaces them during the form integration stage.

## Step 2: delivered scope

- Both employee directory tables now use components/employee-table.blade.php: ID Karyawan, Nama, Divisi, Email, Status, Aksi. Sensitive profile fields and account-link details have moved out of the list.
- Shared action-link and icon components provide accessible eye/pencil links, native title tooltips, and permission checks. HR can read/edit; Admin can read only. No deletion action is added.
- Added GET-only hr.employees.show and admin.employees.show actions because there was no existing employee detail destination. Both call EmployeePolicy::view, return private/no-store pages, and render only explicit profile fields. This is functional support for the eye link; the requested full identity/detail layout is deferred to Step 4.
- Employee tables use compact white headers/rows, thin dividers, small outlined status badges, subtle hover and responsive horizontal scroll. Existing filtering, row limits, pagination and generation rules are preserved.
- Create/edit forms and face enrollment workflows are deliberately unchanged until directory review. The standalone enrollment button/sidebar remain until their replacement exists; removing them now would interrupt access.

## Verification and limits

- Added EmployeeDirectoryPresentationTest to cover column/data visibility, actual action destinations, detail content, guest/Employee restrictions, Admin edit denial, and private response headers.
- Existing module and face enrollment/attendance regression tests cover the unchanged workflows; these are automated tests, not a live camera or FastAPI model verification.
- Visual review in a real browser still depends on the browser connection being available. No screenshot-based approval is implied by the source changes.
