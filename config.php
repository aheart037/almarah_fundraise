<?php

/**
 * ============================================================================
 *  ALMARAH FOUNDATION — YOUR CONFIGURATION FILE
 * ============================================================================
 *
 *  Fill in the three lines below with the database details from
 *  cPanel > Databases > MySQL Databases, then save this file.
 *
 *  Nothing else is required to get the site running. Leave a line empty if
 *  you are not sure — on cPanel the database name and user always start with
 *  your account name, for example almarf1_almarah_platform.
 *
 *  IMPORTANT: on cPanel the values are the FULL names cPanel created, including
 *  the account prefix. Do not shorten them.
 */

return [

    // 1. Database name      e.g. 'almarf1_almarah_platform'
    'db_name' => 'sophiyaw_almarah_platform',

    // 2. Database username  e.g. 'almarf1_almarah'
    'db_user' => 'sophiyaw_inv',

    // 3. Database password  the one you set in cPanel > MySQL Databases
    'db_pass' => 'Sameer@123',


    /* ------------------------------------------------------------------------
     *  OPTIONAL — you can leave all of these alone
     * ---------------------------------------------------------------------- */

    // Database server. 'localhost' is correct on almost every cPanel host.
    'db_host' => 'localhost',

    // Your website address, starting with https://
    //
    //   whole domain          'https://yourdomain.com'
    //   in a subfolder        'https://yourdomain.com/donate'
    //
    // Include the folder if the site is served from one — it is the address
    // used in emails and it is where the bank sends donors back to after a
    // payment. If you leave it empty the site works out its own address from
    // the browser, which is fine while you are setting things up.
    'site_url' => 'https://softex.pk/donate',

    // LEAVE THIS EMPTY.
    //
    // It exists for unusual hosting only. The application works out where your
    // web folder is by itself (the launcher records the folder it is served
    // from), so uploaded images are stored where the browser can display them
    // with no setting here at all.
    //
    // If your host is unusual and the setup page tells you to set it, this must
    // be a FOLDER ON THE SERVER, never a web address:
    //
    //   correct:  '/home/youruser/public_html/donate'
    //   wrong:    'https://yourdomain.com/donate'
    // (A value here was ' /home/sophiyaw/softex.pk/donate' — a folder that does
    // not hold the site, with a stray space in front of it. That sends every
    // uploaded image somewhere the browser cannot read, and makes the setup
    // page report the site's own assets as missing. Emptied, as advised.)
    'public_path' => '',

    // LEAVE THIS EMPTY TOO.
    //
    // Installation asks for no key: you open /setup, type your name, email and
    // a password, and press the button. This page closes itself for good as
    // soon as it has run once.
    //
    // If you have to leave the site reachable before you can install it, type
    // any word here (for example 'almond') and the setup page will ask for that
    // word before it will install. To remove the lock later, empty this line.
    'setup_lock' => '',

];
