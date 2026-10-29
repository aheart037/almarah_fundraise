v19 - Redesigned payment result pages (Meezan Bank and UBL/Etisalat). Changed files only.

NEW   resources/views/partials/payment-result.php   shared result page component
EDIT  resources/views/public/donate/response.php    page the banks return the donor to
EDIT  resources/views/public/donate/status.php      receipt page /donation/{reference}
EDIT  assets/css/style.css   (also contains v16-v18 styles)
EDIT  assets/js/app.js       (also contains v16-v18 scripts)

No controller, route, gateway or database change: payment verification is untouched.
Both gateways and both entry points now use one component.

Upload over the existing files, keep folder names, then Ctrl+F5. Do not upload this README.
