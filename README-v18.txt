v18 - Charts and graphs on the admin and user dashboards. Changed files only.

NEW   resources/views/partials/charts.php   chart drawing (plain SVG, no library)
EDIT  app/Support/helpers.php               chart_fill_days() helper
EDIT  app/Http/Controllers/Admin/DashboardController.php       chart queries
EDIT  app/Http/Controllers/Fundraiser/DashboardController.php  chart queries
EDIT  resources/views/admin/dashboard.php
EDIT  resources/views/fundraiser/dashboard.php
EDIT  assets/css/style.css   (also contains v16 + v17 styles)
EDIT  assets/js/app.js       (also contains v16 + v17 scripts)

Admin: 30-day trend, payment outcomes donut, top fundraisers, gateway share.
User : 30-day trend, goal-progress ring, progress per fundraiser, outcomes donut.

Upload over the existing files, keep folder names, then Ctrl+F5. No database
change. Do not upload this README.
