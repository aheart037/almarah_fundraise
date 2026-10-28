v17 - Elegant animated dashboards (user + admin). Changed files only.

assets/css/style.css   - new section "Dashboard polish + motion (v17)". This file
                         ALSO contains the v16 form styles, so it replaces v16's copy.
assets/js/app.js       - initDashboardFX / initDashboardClicks added. Also contains
                         the v16 form script, so it replaces v16's copy.
resources/views/fundraiser/dashboard.php
                       - time-of-day greeting + goal progress bars in "Your fundraisers".

Upload over the existing files, then hard-refresh (Ctrl+F5). No database change.
Admin pages need no view changes: they pick the effects up from the CSS/JS.
Visitors who set "reduce motion" on their device get the pages without animation.
Do not upload this README.
