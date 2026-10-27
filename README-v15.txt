ALMARAH FUNDRAISE - v15 (changed files only)
=============================================

HOW TO INSTALL
1. Upload the files in this zip over your site folder, keeping the same
   folder names (they replace the v14 copies). Do NOT upload this README.
2. DELETE the file "error_log" from the top of your site folder (it is also
   removed from git: run  git rm --cached error_log  if it is still tracked).
3. config.php and your database are NOT touched. No migration is needed.
4. Open https://fundraise.almarah.org/robots.txt and /sitemap.xml to check.
5. In Google Search Console: add the property, then submit
   https://fundraise.almarah.org/sitemap.xml

WHAT CHANGED
Payment bug (from your own error_log)
  app/Payments/Http/CurlHttpClient.php
    "Undefined constant CURLE_PEER_FAILED_VERIFICATION" crashed every failed
    gateway call and hid the real reason. Fixed. If payments still fail you
    will now see the true cause (e.g. "TLS peer verification failed").

Security
  .htaccess                 - forces HTTPS (301); only index.php and the 3
                              payment files may run as PHP; blocks error_log,
                              config.php, router.php; no PHP in assets/uploads;
                              gzip + browser caching.
  app/Core/Response.php     - HSTS, Content-Security-Policy, Permissions-Policy,
                              COOP, hides X-Powered-By, no shared caching of
                              pages that carry CSRF tokens.
  config/security.php       - new CSP default; per-IP login limit (25 / 15 min).
  app/Services/AuthService.php - login throttled per IP as well as per account.
  app/Core/Session.php      - session id never in URLs, longer session ids.
  .gitignore                - logs can no longer be committed.

SEO
  resources/views/layouts/public.php - canonical URL, robots meta, Open Graph +
      Twitter cards (with default image), theme-color, JSON-LD (NGO, WebSite
      + search box, BreadcrumbList, page data). Account, donation, payment and
      404 pages and filtered list pages are set to noindex.
  app/Http/Controllers/Public/SeoController.php (new) + routes/web.php
      - /robots.txt and /sitemap.xml generated from the database
        (published fundraisers, active campaigns and teams).
  FundraiserController, CampaignController, TeamController, PageController
      - breadcrumbs, article type, FAQPage schema, noindex on 404s.
  DonationController, DonationStatusController - noindex on 404s.

NOTES
- Site folder install (e.g. /donate)? Then robots.txt must also exist at the
  domain root; this build serves it inside the folder.
- Behind Cloudflare/proxy: HTTPS redirect already honours X-Forwarded-Proto
  and CF-Visitor, so no loop.
- Strongly recommended, outside the code: rotate the DB password and SMTP /
  gateway secrets if the old error_log or config.php was ever public.
