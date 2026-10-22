<?php
/**
 * Email template list.
 *
 * @var array $templates
 * @var bool $mailConfigured
 * @var bool $designCurrent  every saved email was brought up to the shipped design
 */
?>
<div class="dash-head">
  <div>
    <h1>Email templates</h1>
    <p class="sub">Every transactional email the platform can send.</p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/email-queue')) ?>">Email queue</a>
    <a class="btn btn-brand" href="<?= e(base_url('admin/settings#smtp')) ?>">SMTP settings</a>
  </div>
</div>

<?php if (!$mailConfigured): ?>
  <div class="alert alert-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
    <div>
      <strong>SMTP is not configured.</strong>
      <p>Emails will be queued but cannot be delivered until a mail server is set up.</p>
    </div>
  </div>
<?php endif; ?>

<section class="panel">
  <h2 style="margin-top:0">Email design</h2>
  <?php if (!$designCurrent): ?>
    <p>Your saved emails still use the <b>previous design</b>. Apply the new design (wine header, gold accents, amount card and footer with both offices) to all of them in one step.</p>
  <?php else: ?>
    <p class="muted">Your emails use the current design. You can apply it again at any time, for example after changing the office details in <span class="mono">config/app.php</span>.</p>
  <?php endif; ?>
  <p class="muted" style="font-size:13px;margin-top:0">
    Only the look of each email is replaced. Subjects, plain-text versions and the on/off switch are kept, every
    <span class="mono">{{token}}</span> keeps working, and a copy of each old body is stored first. If you edited the wording of an
    email's HTML yourself, that edit is replaced by the standard wording.
  </p>
  <form method="post" action="<?= e(base_url('admin/email-templates/apply-design')) ?>"
        data-confirm="Apply the new design to all saved emails? A copy of each current version is kept.">
    <?= csrf_field() ?>
    <button class="btn <?= $designCurrent ? 'btn-light' : 'btn-brand' ?>" type="submit">Apply the new design to all emails</button>
  </form>
</section>

<section class="panel">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Event key</th><th>Name</th><th class="wrap">Subject</th><th>State</th><th>Updated</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($templates as $template): ?>
          <tr>
            <td class="mono"><?= e((string) $template['event_key']) ?></td>
            <td><?= e((string) $template['name']) ?></td>
            <td class="wrap"><?= e((string) $template['subject']) ?></td>
            <td>
              <?php if (!empty($template['enabled'])): ?>
                <span class="badge badge-green">enabled</span>
              <?php else: ?>
                <span class="badge badge-grey">disabled</span>
              <?php endif; ?>
            </td>
            <td><?= e(dt((string) $template['updated_at'], 'j M Y')) ?></td>
            <td><a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/email-templates/' . (string) $template['id'])) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="form-help mt-2">
    Tokens available in every template: <span class="mono">{{appName}}</span>, <span class="mono">{{year}}</span>,
    plus the values passed by the event such as <span class="mono">{{heading}}</span>, <span class="mono">{{body}}</span>,
    <span class="mono">{{cta_label}}</span>, <span class="mono">{{cta_url}}</span>, <span class="mono">{{reference}}</span>,
    <span class="mono">{{amount}}</span>, <span class="mono">{{donor_name}}</span>, <span class="mono">{{fundraiser_title}}</span>.
    Unknown tokens are replaced with an empty string.
  </p>
</section>
