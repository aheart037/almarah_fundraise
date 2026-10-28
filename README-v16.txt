v16 - Fundraiser form redesign (changed files only; apply on top of v15)

1. resources/views/fundraiser/fundraisers/form.php
   Campaign field removed. New 4-step layout (story, cause, goal, cover photo),
   live character counters, quick goal amounts, quick end dates, cover photo
   preview, sticky save panel and tips. Same field names as before.
2. app/Http/Controllers/Fundraiser/FundraiserController.php
   Editing no longer unlinks a fundraiser from a campaign an admin set.
3. assets/css/style.css  (appended: "Fundraiser create / edit form (v16)")
4. assets/js/app.js      (added initFundraiserForm)

Upload over the existing files. No database change. Do not upload this README.
Note: style.css and app.js are changed, so hard-refresh (Ctrl+F5) once.
Links like /dashboard/fundraisers/create?campaign=3 still attach that campaign quietly.
