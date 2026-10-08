<?php
/**
 * Platform settings: payment gateways, SMTP and site options.
 *
 * @var array $gateways       code => config (password removed, password_configured added)
 * @var array $gatewayStatus
 * @var array $mail
 * @var bool $mailConfigured
 * @var array $site
 * @var array $fundraiserCapabilities
 * @var array $queueStats
 */

$isSuper = app(\App\Services\AuthService::class)->isSuperAdmin();
$headerLogoUrl = site_brand_asset('site.header_logo');
$footerLogoUrl = site_brand_asset('site.footer_logo');
$faviconUrl = site_brand_asset('site.favicon');
?>
<div class="dash-head">
  <div>
    <h1>Settings</h1>
    <p class="sub">Payment credentials, email delivery and site options.</p>
  </div>
</div>

<nav class="tabs">
  <?php foreach (array_keys($gateways) as $code): ?>
    <a href="#gateway-<?= e($code) ?>"><?= e(ucfirst($code)) ?></a>
  <?php endforeach; ?>
  <a href="#smtp">Email / SMTP</a>
  <a href="#branding">Branding</a>
  <a href="#fundraiser-capabilities">Fundraiser controls</a>
  <a href="#site">Site &amp; donations</a>
  <a href="#queue">Queue</a>
</nav>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>
    Credentials are encrypted at rest with the application key and are never sent back to the browser.
    A blank password field keeps the value already stored.
  </p>
</div>

<?php if (!$isSuper): ?>
  <div class="alert alert-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
    <p>Only a super administrator can change payment and email credentials. You can still edit site options.</p>
  </div>
<?php endif; ?>

<?php foreach ($gateways as $code => $config): ?>
  <?php $status = $gatewayStatus[$code] ?? []; ?>
  <section class="panel" id="gateway-<?= e($code) ?>">
    <div class="panel-head">
      <h2><?= e((string) ($config['label'] ?? ucfirst($code))) ?></h2>
      <div>
        <?php if (!empty($status['enabled']) && !empty($status['configured'])): ?>
          <span class="badge badge-green">ready</span>
        <?php elseif (!empty($status['enabled'])): ?>
          <span class="badge badge-amber">needs credentials</span>
        <?php else: ?>
          <span class="badge badge-grey">disabled</span>
        <?php endif; ?>
        <span class="badge badge-purple"><?= e(ucfirst((string) ($config['environment'] ?? 'sandbox'))) ?></span>
      </div>
    </div>

    <p class="panel-sub">
      <?= e((string) ($config['description'] ?? '')) ?>
      <?php if (!empty($config['documentation'])): ?>
        &middot; <a href="<?= e((string) $config['documentation']) ?>" target="_blank" rel="noopener">provider documentation</a>
      <?php endif; ?>
    </p>

    <form method="post" action="<?= e(base_url('admin/settings/gateway/' . $code)) ?>">
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="field">
          <label for="<?= e($code) ?>_enabled">Accept payments</label>
          <label class="checkbox-row">
            <input type="checkbox" id="<?= e($code) ?>_enabled" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
            <span>Enable this payment method on the donation form</span>
          </label>
        </div>

        <div class="field">
          <label for="<?= e($code) ?>_environment">Environment</label>
          <select class="select-field" id="<?= e($code) ?>_environment" name="environment">
            <option value="sandbox" <?= ($config['environment'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
            <option value="live" <?= ($config['environment'] ?? '') === 'live' ? 'selected' : '' ?>>Live (real money)</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="<?= e($code) ?>_username">Merchant username / ID</label>
          <input class="input" type="text" id="<?= e($code) ?>_username" name="username" autocomplete="off"
                 value="<?= e((string) ($config['username'] ?? '')) ?>">
        </div>

        <div class="field">
          <label for="<?= e($code) ?>_password">Merchant password</label>
          <input class="input" type="password" id="<?= e($code) ?>_password" name="password" autocomplete="new-password"
                 placeholder="<?= !empty($config['password_configured']) ? '•••••••• (stored — leave blank to keep)' : 'Not set' ?>">
          <span class="form-help">
            <?php if (!empty($config['password_configured'])): ?>
              A password is stored. It is never shown again — submit a new one to replace it.
            <?php else: ?>
              Stored encrypted. Never logged and never sent to the browser again.
            <?php endif; ?>
          </span>
        </div>
      </div>

      <?php foreach (['customer', 'store', 'terminal', 'merchant_id', 'currency_code', 'currency_name', 'currency'] as $field): ?>
        <?php if (!array_key_exists($field, $config)) { continue; } ?>
        <div class="form-row">
          <div class="field">
            <label for="<?= e($code . '_' . $field) ?>"><?= e(ucwords(str_replace('_', ' ', $field))) ?></label>
            <input class="input" type="text" id="<?= e($code . '_' . $field) ?>" name="<?= e($field) ?>"
                   value="<?= e((string) $config[$field]) ?>" autocomplete="off">
          </div>
          <div class="field">&nbsp;</div>
        </div>
      <?php endforeach; ?>

      <div class="form-row">
        <div class="field">
          <label for="<?= e($code) ?>_sandbox_url"><?= $code === 'etisalat' ? 'Sandbox REST endpoint' : 'Sandbox endpoint' ?></label>
          <input class="input mono" type="url" id="<?= e($code) ?>_sandbox_url" name="sandbox_url" value="<?= e((string) ($config['sandbox_url'] ?? '')) ?>">
          <?php if ($code === 'etisalat'): ?>
            <span class="form-help">UBL EPG uses the /epg/rest path. It is added automatically when only the host is configured.</span>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="<?= e($code) ?>_live_url"><?= $code === 'etisalat' ? 'Live REST endpoint' : 'Live endpoint' ?></label>
          <input class="input mono" type="url" id="<?= e($code) ?>_live_url" name="live_url" value="<?= e((string) ($config['live_url'] ?? '')) ?>">
          <span class="form-help">Payment-page hosts returned by the gateway are checked against its allowlist.</span>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="<?= e($code) ?>_timeout">Request timeout (seconds)</label>
          <input class="input" type="number" id="<?= e($code) ?>_timeout" name="timeout" min="5" max="120" value="<?= e((string) (int) ($config['timeout'] ?? 30)) ?>">
        </div>
        <div class="field">&nbsp;</div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit" <?= $isSuper ? '' : 'disabled' ?>>Save <?= e($code) ?> settings</button>
      </div>
    </form>

    <?php if ($isSuper): ?>
      <form method="post" action="<?= e(base_url('admin/settings/gateway/' . $code . '/test')) ?>"
            data-confirm="Ask the provider to authenticate with these credentials? No payment is created.">
        <?= csrf_field() ?>
        <button class="btn btn-outline-brand" type="submit">Test credentials</button>
        <span class="form-help">Authenticates against the provider with a harmless request. No money moves.</span>
      </form>
    <?php endif; ?>

    <details class="mt-3">
      <summary style="cursor:pointer;font-weight:700;color:var(--ink-2)">What the integration does</summary>
      <ul class="text-muted mt-2" style="line-height:1.8;font-size:14px">
        <?php foreach ((array) ($config['notes'] ?? []) as $note): ?>
          <li><?= e((string) $note) ?></li>
        <?php endforeach; ?>
        <?php if (empty($config['notes'])): ?>
          <li>Registration hands the donor to the provider&rsquo;s hosted payment page; we never see card details.</li>
          <li>The return trip is verified server-to-server before a donation is marked completed.</li>
          <li>Duplicate callbacks are detected and ignored, so a donation is never counted twice.</li>
        <?php endif; ?>
      </ul>
    </details>
  </section>
<?php endforeach; ?>

<section class="panel" id="smtp">
  <div class="panel-head">
    <h2>Email / SMTP</h2>
    <div>
      <?php if ($mailConfigured): ?>
        <span class="badge badge-green">configured</span>
      <?php else: ?>
        <span class="badge badge-amber">not configured</span>
      <?php endif; ?>
    </div>
  </div>
  <p class="panel-sub">Transactional email is queued in the database and delivered by the worker, so a mail outage never loses a receipt.</p>

  <form method="post" action="<?= e(base_url('admin/settings/smtp')) ?>">
    <?= csrf_field() ?>

    <div class="form-row">
      <div class="field">
        <label for="host">SMTP host</label>
        <input class="input" type="text" id="host" name="host" value="<?= e((string) $mail['host']) ?>" placeholder="smtp.example.com" autocomplete="off">
      </div>
      <div class="field">
        <label for="port">Port</label>
        <input class="input" type="number" id="port" name="port" min="1" max="65535" value="<?= e((string) $mail['port']) ?>" placeholder="587">
      </div>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="encryption">Encryption</label>
        <select class="select-field" id="encryption" name="encryption">
          <option value="tls" <?= $mail['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (recommended)</option>
          <option value="ssl" <?= $mail['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
          <option value="none" <?= $mail['encryption'] === 'none' ? 'selected' : '' ?>>None</option>
        </select>
      </div>
      <div class="field">
        <label for="username">SMTP username</label>
        <input class="input" type="text" id="username" name="username" value="<?= e((string) $mail['username']) ?>" autocomplete="off">
      </div>
    </div>

    <div class="field">
      <label for="password">SMTP password</label>
      <input class="input" type="password" id="password" name="password" autocomplete="new-password"
             placeholder="<?= !empty($mail['password_configured']) ? '•••••••• (stored — leave blank to keep)' : 'Not set' ?>">
      <span class="form-help">Stored encrypted with the application key. Never displayed again and never logged.</span>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="from_address">From address</label>
        <input class="input" type="email" id="from_address" name="from_address" value="<?= e((string) $mail['from_address']) ?>" placeholder="fundraising@almarah.org">
      </div>
      <div class="field">
        <label for="from_name">From name</label>
        <input class="input" type="text" id="from_name" name="from_name" value="<?= e((string) $mail['from_name']) ?>" placeholder="Almarah Foundation">
      </div>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="reply_to">Reply-to address</label>
        <input class="input" type="email" id="reply_to" name="reply_to" value="<?= e((string) $mail['reply_to']) ?>">
      </div>
      <div class="field">
        <label>Options</label>
        <label class="checkbox-row">
          <input type="checkbox" name="verify_peer" value="1" <?= !empty($mail['verify_peer']) ? 'checked' : '' ?>>
          <span>Verify the mail server's TLS certificate (keep this on)</span>
        </label>
        <label class="checkbox-row mt-1">
          <input type="checkbox" name="queue_enabled" value="1" <?= !empty($mail['queue_enabled']) ? 'checked' : '' ?>>
          <span>Queue emails for the worker (turn off to send inline)</span>
        </label>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-brand" type="submit" <?= $isSuper ? '' : 'disabled' ?>>Save SMTP settings</button>
    </div>
  </form>

  <?php if ($isSuper): ?>
    <div class="grid-2 mt-3">
      <form method="post" action="<?= e(base_url('admin/settings/smtp/test-connection')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline-brand" type="submit">Test connection</button>
        <p class="form-help">Opens a connection and authenticates. No email is sent.</p>
      </form>

      <form method="post" action="<?= e(base_url('admin/settings/smtp/send-test')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="recipient">Send a test email to</label>
          <div class="input-group">
            <input class="input" type="email" id="recipient" name="recipient" required value="<?= e((string) ($authUser['email'] ?? '')) ?>">
            <button class="btn btn-brand" type="submit">Send test</button>
          </div>
          <span class="form-help">The message is composed on the fly and never stored as a template.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>
</section>

<section class="panel" id="branding">
  <div class="panel-head">
    <div>
      <h2>Brand identity</h2>
      <p class="panel-sub">Update the logos and browser icon shown across the public site and account pages.</p>
    </div>
  </div>

  <form method="post" action="<?= e(base_url('admin/settings/branding')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="branding-grid">
      <article class="branding-item">
        <div class="branding-item__heading">
          <h3>Header logo</h3>
          <p>Shown in the main site navigation.</p>
        </div>
        <div class="branding-preview branding-preview--logo">
          <div class="branding-preview__fallback" id="header-logo-fallback" <?= $headerLogoUrl !== null ? 'hidden' : '' ?>>
            <span class="branding-preview__mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M24 5c4 5.3 6 9.4 6 13.5 0 3.8-2.6 6.4-6 6.4s-6-2.6-6-6.4C18 14.4 20 10.3 24 5Z" fill="currentColor"/><path d="M24 16c6 0 11.2 2.2 15 6v16a3 3 0 0 1-3 3H12a3 3 0 0 1-3-3V22c4-3.8 9.2-6 15-6Z" fill="currentColor"/><path d="m24 22 2 4 4.5.7-3.2 3.1.8 4.5-4.1-2.1-4.1 2.1.8-4.5-3.2-3.1 4.5-.7z" fill="#6b0f35"/></svg></span>
            <span class="branding-preview__word"><strong>ALMARAH</strong><small>Foundation</small></span>
          </div>
          <img class="branding-preview__image" id="header-logo-preview" src="<?= e($headerLogoUrl ?? 'data:,') ?>" alt="Header logo preview" <?= $headerLogoUrl === null ? 'hidden' : '' ?>>
        </div>
        <label class="branding-item__label" for="brand-header-logo">Upload a new header logo</label>
        <input class="input branding-file" type="file" id="brand-header-logo" name="header_logo" accept="image/png,image/jpeg,image/webp"
               data-branding-input data-branding-preview="header-logo-preview" data-branding-fallback="header-logo-fallback"
               data-branding-remove="remove-header-logo" data-current-src="<?= e($headerLogoUrl ?? '') ?>">
        <span class="form-help">PNG, JPG or WebP, up to 4 MB. A transparent, horizontal logo works best.</span>
        <?php if ($headerLogoUrl !== null): ?>
          <label class="checkbox-row branding-reset" for="remove-header-logo">
            <input type="checkbox" id="remove-header-logo" name="remove_header_logo" value="1">
            <span>Restore the built-in Almarah logo</span>
          </label>
        <?php endif; ?>
      </article>

      <article class="branding-item">
        <div class="branding-item__heading">
          <h3>Footer logo</h3>
          <p>Shown in the site footer.</p>
        </div>
        <div class="branding-preview branding-preview--logo">
          <div class="branding-preview__fallback" id="footer-logo-fallback" <?= $footerLogoUrl !== null ? 'hidden' : '' ?>>
            <span class="branding-preview__mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M24 5c4 5.3 6 9.4 6 13.5 0 3.8-2.6 6.4-6 6.4s-6-2.6-6-6.4C18 14.4 20 10.3 24 5Z" fill="currentColor"/><path d="M24 16c6 0 11.2 2.2 15 6v16a3 3 0 0 1-3 3H12a3 3 0 0 1-3-3V22c4-3.8 9.2-6 15-6Z" fill="currentColor"/><path d="m24 22 2 4 4.5.7-3.2 3.1.8 4.5-4.1-2.1-4.1 2.1.8-4.5-3.2-3.1 4.5-.7z" fill="#6b0f35"/></svg></span>
            <span class="branding-preview__word"><strong>ALMARAH</strong><small>Foundation</small></span>
          </div>
          <img class="branding-preview__image" id="footer-logo-preview" src="<?= e($footerLogoUrl ?? 'data:,') ?>" alt="Footer logo preview" <?= $footerLogoUrl === null ? 'hidden' : '' ?>>
        </div>
        <label class="branding-item__label" for="brand-footer-logo">Upload a new footer logo</label>
        <input class="input branding-file" type="file" id="brand-footer-logo" name="footer_logo" accept="image/png,image/jpeg,image/webp"
               data-branding-input data-branding-preview="footer-logo-preview" data-branding-fallback="footer-logo-fallback"
               data-branding-remove="remove-footer-logo" data-current-src="<?= e($footerLogoUrl ?? '') ?>">
        <span class="form-help">PNG, JPG or WebP, up to 4 MB. Use a light version if the logo is designed for a dark background.</span>
        <?php if ($footerLogoUrl !== null): ?>
          <label class="checkbox-row branding-reset" for="remove-footer-logo">
            <input type="checkbox" id="remove-footer-logo" name="remove_footer_logo" value="1">
            <span>Restore the built-in Almarah logo</span>
          </label>
        <?php endif; ?>
      </article>

      <article class="branding-item">
        <div class="branding-item__heading">
          <h3>Favicon</h3>
          <p>Shown in browser tabs and bookmarks.</p>
        </div>
        <div class="branding-preview branding-preview--favicon">
          <div class="branding-preview__favicon-fallback" id="favicon-fallback" <?= $faviconUrl !== null ? 'hidden' : '' ?>>
            <svg viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="10" fill="#a92d63"/><path d="M24 15.2c5.9 0 11.2 2.3 15.2 6.1v17.2a3 3 0 0 1-3 3H11.8a3 3 0 0 1-3-3V21.3A21.6 21.6 0 0 1 24 15.2z" fill="#f2c200"/></svg>
          </div>
          <img class="branding-preview__image" id="favicon-preview" src="<?= e($faviconUrl ?? 'data:,') ?>" alt="Favicon preview" <?= $faviconUrl === null ? 'hidden' : '' ?>>
        </div>
        <label class="branding-item__label" for="brand-favicon">Upload a PNG favicon</label>
        <input class="input branding-file" type="file" id="brand-favicon" name="favicon" accept="image/png,.png"
               data-branding-input data-branding-preview="favicon-preview" data-branding-fallback="favicon-fallback"
               data-branding-remove="remove-favicon" data-current-src="<?= e($faviconUrl ?? '') ?>">
        <span class="form-help">PNG only, up to 4 MB. A square image with a transparent background is recommended.</span>
        <?php if ($faviconUrl !== null): ?>
          <label class="checkbox-row branding-reset" for="remove-favicon">
            <input type="checkbox" id="remove-favicon" name="remove_favicon" value="1">
            <span>Restore the built-in favicon</span>
          </label>
        <?php endif; ?>
      </article>
    </div>

    <div class="form-actions branding-actions">
      <button class="btn btn-brand" type="submit">Save branding</button>
      <span class="form-help">Images update across the site immediately; file URLs are versioned to refresh browser caches.</span>
    </div>
  </form>
</section>

<section class="panel" id="fundraiser-capabilities">
  <div class="panel-head">
    <div>
      <h2>Fundraiser capabilities</h2>
      <p class="panel-sub">Control which tools are available to fundraiser accounts.</p>
    </div>
  </div>

  <div class="alert alert-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
    <p>These platform-wide controls apply immediately to existing and newly registered fundraiser accounts. Administrators retain access.</p>
  </div>

  <form method="post" action="<?= e(base_url('admin/settings/fundraiser-capabilities')) ?>">
    <?= csrf_field() ?>

    <div class="capability-options">
      <label class="checkbox-row capability-option">
        <input type="checkbox" name="manage_pages" value="1" <?= !empty($fundraiserCapabilities['manage_pages']) ? 'checked' : '' ?>>
        <span><b>Create and manage fundraiser pages</b><em>Allow fundraiser accounts to create drafts, edit details, submit pages for review, and pause their fundraisers.</em></span>
      </label>

      <label class="checkbox-row capability-option">
        <input type="checkbox" name="manage_teams" value="1" <?= !empty($fundraiserCapabilities['manage_teams']) ? 'checked' : '' ?>>
        <span><b>Manage fundraising teams</b><em>Allow fundraiser accounts to create teams, invite or remove members, and accept team invitations.</em></span>
      </label>

      <label class="checkbox-row capability-option">
        <input type="checkbox" name="publish_updates" value="1" <?= !empty($fundraiserCapabilities['publish_updates']) ? 'checked' : '' ?>>
        <span><b>Publish fundraiser updates</b><em>Allow fundraiser accounts to post and remove updates on their fundraiser pages.</em></span>
      </label>
    </div>

    <div class="form-actions">
      <button class="btn btn-brand" type="submit">Save fundraiser controls</button>
    </div>
  </form>
</section>

<section class="panel" id="site">
  <h2>Site &amp; donations</h2>
  <p class="panel-sub">Public contact details, moderation behaviour and donation limits.</p>

  <form method="post" action="<?= e(base_url('admin/settings/site')) ?>">
    <?= csrf_field() ?>

    <div class="field">
      <label for="tagline">Site tagline</label>
      <input class="input" type="text" id="tagline" name="tagline" value="<?= e((string) $site['tagline']) ?>">
    </div>

    <div class="form-row">
      <div class="field">
        <label for="support_email">Support email</label>
        <input class="input" type="email" id="support_email" name="support_email" value="<?= e((string) $site['support_email']) ?>">
      </div>
      <div class="field">
        <label for="support_phone">Support phone</label>
        <input class="input" type="text" id="support_phone" name="support_phone" value="<?= e((string) $site['support_phone']) ?>">
      </div>
    </div>

    <div class="field">
      <label for="footer_note">Footer note</label>
      <textarea class="textarea" id="footer_note" name="footer_note" rows="2"><?= e((string) $site['footer_note']) ?></textarea>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="donations_min">Minimum donation (PKR)</label>
        <input class="input" type="number" id="donations_min" name="donations_min" step="1" min="1"
               value="<?= e((string) (int) ($site['min_minor'] / 100)) ?>">
      </div>
      <div class="field">
        <label for="donations_max">Maximum donation (PKR)</label>
        <input class="input" type="number" id="donations_max" name="donations_max" step="1" min="1"
               value="<?= e((string) (int) ($site['max_minor'] / 100)) ?>">
      </div>
    </div>

    <div class="field">
      <label for="receipts_from">Receipts from address</label>
      <input class="input" type="email" id="receipts_from" name="receipts_from" value="<?= e((string) $site['receipts_from']) ?>" placeholder="Defaults to the From address above">
    </div>

    <div class="form-row">
      <div class="field">
        <label>Moderation</label>
        <label class="checkbox-row">
          <input type="checkbox" name="require_verification" value="1" <?= !empty($site['require_verification']) ? 'checked' : '' ?>>
          <span>Require a verified email before a fundraiser can be submitted</span>
        </label>
        <label class="checkbox-row mt-1">
          <input type="checkbox" name="auto_publish_updates" value="1" <?= !empty($site['auto_publish_updates']) ? 'checked' : '' ?>>
          <span>Publish fundraiser updates immediately</span>
        </label>
      </div>
      <div class="field">
        <label>Environment</label>
        <p class="text-muted" style="font-size:14px;margin:0">
          Application environment: <b><?= e((string) config('app.env')) ?></b><br>
          Debug mode: <b><?= config('app.debug') ? 'on' : 'off' ?></b><br>
          The application key is held in <span class="mono">.env</span> and never shown here.
        </p>
      </div>
    </div>

    <button class="btn btn-brand" type="submit">Save site settings</button>
  </form>
</section>

<section class="panel" id="queue">
  <h2>Queue worker</h2>
  <p class="panel-sub">Add this to cron so queued email and payment reconciliation happen automatically.</p>

  <pre class="code"># every minute — send queued email
* * * * * cd <?= e(str_replace("'", "", app()->basePath())) ?> && php bin/console mail:work --once >> storage/logs/mail.log 2>&1

# every five minutes — reconcile payments the bank never confirmed
*/5 * * * * cd <?= e(str_replace("'", "", app()->basePath())) ?> && php bin/console payments:reconcile >> storage/logs/payments.log 2>&1

# hourly — housekeeping
0 * * * * cd <?= e(str_replace("'", "", app()->basePath())) ?> && php bin/console maintenance:prune >> storage/logs/maintenance.log 2>&1</pre>

  <dl class="kv mt-3">
    <dt>Queued</dt><dd><?= e((string) (int) ($queueStats['queued'] ?? 0)) ?></dd>
    <dt>Sending</dt><dd><?= e((string) (int) ($queueStats['sending'] ?? 0)) ?></dd>
    <dt>Sent</dt><dd><?= e((string) (int) ($queueStats['sent'] ?? 0)) ?></dd>
    <dt>Failed</dt><dd><?= e((string) (int) ($queueStats['failed'] ?? 0)) ?></dd>
  </dl>

  <p class="mt-2"><a class="link-arrow" href="<?= e(base_url('admin/email-queue')) ?>">Open the email queue</a></p>
</section>
