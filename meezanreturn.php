<?php

declare(strict_types=1);

/**
 * Almarah Foundation – Meezan Bank return page.
 *
 * The counterpart of meezanreturn.php in the Meezan Pay plugin and the
 * default "Return Url" of the Meezan settings in Admin → Settings. Meezan
 * sends the donor's browser back here with its own ?orderId=… appended.
 *
 * The stored payment is found by that orderId and verified SERVER-TO-SERVER
 * with getOrderStatus.do (OrderStatus 2 = paid) before anything is counted;
 * the result card is then shown in the site's layout. Nothing depends on the
 * donor's session. The handling is shared with fundraise-response.php, which
 * stays the return page for Etisalat.
 */

require __DIR__ . '/fundraise-response.php';
