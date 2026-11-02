v20 - Admin Reports module (13 reports + PDF export). Changed/new files only.

INSTALL
 1. Upload the files over your site, keeping the folder names. Do not upload this README.
 2. No database change. No Composer package needed: the PDF writer (FPDF 1.9, free
    licence, see app/Support/Pdf/FPDF-LICENSE.txt) ships inside app/Support/Pdf.
 3. Hard-refresh once (Ctrl+F5) so the new CSS/JS load.
 4. Sign in as an admin: Admin sidebar -> Reports.

NEW
  app/Services/ReportService.php             all 13 reports (queries, filters, totals)
  app/Services/ReportPdfService.php          PDF document builder
  app/Http/Controllers/Admin/ReportController.php
  app/Support/Pdf/                            FPDF + its font definitions
  resources/views/admin/reports/show.php      one page template for every report
  resources/views/partials/charts.php         (same file as v18; included for safety)
  tests/                                      test suite (see below)
CHANGED
  routes/admin.php                            3 read-only GET routes, admin role required
  resources/views/layouts/admin.php           expandable Reports sidebar menu
  assets/css/style.css, assets/js/app.js      (cumulative with v16-v19)
  composer.json                               adds:  composer test:reports

URLS (all require login + admin/super_admin role; server-checked)
  /admin/reports                      Overview
  /admin/reports/{donations|payments|campaigns|fundraisers|teams|donors|approvals|
                  reconciliation|refunds|financial|receipts|audit}
  /admin/reports/{report}/pdf         PDF download (same filters as the page)

TESTS (never run these against your live database: they WIPE the database they use)
  Create an empty database whose name contains "test", then:
    TEST_DB_NAME=almarah_test TEST_DB_USER=... TEST_DB_PASS=... php tests/reports_test.php
  189 checks: hand-calculated totals, no double counting, currency separation,
  filters/sorting/pagination, hostile input, role checks, PDF validity/pagination.
  The tests/ folder is blocked from the web by .htaccess; you do not need to upload it
  to the live site.

LIMITATIONS (also printed on each report and in its PDF)
  - Bank transfers are not recorded anywhere in the system, so they cannot be reported or
    reconciled; the reconciliation report covers gateway payments only.
  - Gateway fees and bank settlement/payout data are not recorded: "Net" = donations - refunds.
  - Campaigns have no goal of their own; the report compares raised money with the combined
    goals of the fundraisers in the campaign.
  - Approval submit/decision times and reviewer come from the audit log, so pages reviewed
    before audit logging began show "-" for those columns.
  - Donations carry no receipt number or receipt PDF: the donation reference is used.
  - PDFs use Latin fonts: Urdu/Arabic text appears as "?" in the PDF (correct on screen).
  - PDFs contain at most 5,000 rows (the PDF says so when rows are cut).
  - Currencies are never converted; each report has a currency filter (default PKR).
