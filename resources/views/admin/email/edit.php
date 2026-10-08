<?php
/**
 * Edit one email template with a live preview of the rendered output.
 *
 * @var array $template
 * @var array $preview
 * @var array $tokens
 */
?>
<div class="dash-head">
  <div>
    <h1><?= e((string) $template['name']) ?></h1>
    <p class="sub"><span class="mono"><?= e((string) $template['event_key']) ?></span></p>
  </div>
  <a class="btn btn-light" href="<?= e(base_url('admin/email-templates')) ?>">Back to templates</a>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Template</h2>

    <form method="post" action="<?= e(base_url('admin/email-templates/' . (string) $template['id'])) ?>">
      <?= csrf_field() ?>

      <div class="field">
        <label for="subject">Subject<span class="req">*</span></label>
        <input class="input" type="text" id="subject" name="subject" required maxlength="255" value="<?= e((string) $template['subject']) ?>">
      </div>

      <div class="field">
        <label for="body_html">HTML body<span class="req">*</span></label>
        <textarea class="textarea mono" id="body_html" name="body_html" rows="18" required><?= e((string) $template['html_body']) ?></textarea>
        <span class="form-help">Use <span class="mono">{{token}}</span> placeholders. Values are HTML-escaped for you except in explicitly unescaped tokens.</span>
      </div>

      <div class="field">
        <label for="body_text">Plain-text body</label>
        <textarea class="textarea mono" id="body_text" name="body_text" rows="8"><?= e((string) $template['text_body']) ?></textarea>
        <span class="form-help">Always provide this: some mail clients and spam filters expect a text alternative.</span>
      </div>

      <div class="checkbox-row">
        <input type="checkbox" id="enabled" name="enabled" value="1" <?= !empty($template['enabled']) ? 'checked' : '' ?>>
        <label for="enabled">Send this email</label>
      </div>

      <div class="form-actions mt-2">
        <button class="btn btn-brand btn-lg" type="submit">Save template</button>
      </div>
    </form>

    <form method="post" action="<?= e(base_url('admin/email-templates/' . (string) $template['id'] . '/reset')) ?>"
          data-confirm="Replace this template with the default design? Your current text will be lost.">
      <?= csrf_field() ?>
      <button class="btn btn-light" type="submit">Reset to default design</button>
      <span class="form-help">Restores the branded layout shipped with the platform, with tokens still in place for editing.</span>
    </form>
  </section>

  <section class="panel">
    <h2>Preview</h2>
    <p class="panel-sub">Rendered with sample data. Real emails receive the actual event values.</p>

    <div class="email-preview">
      <div class="ep-head">
        <b>Subject:</b> <?= e((string) ($preview['subject'] ?? '')) ?>
      </div>
      <div class="ep-body">
        <?= (string) ($preview['html'] ?? '<p class="text-muted">Preview unavailable.</p>') ?>
      </div>
    </div>

    <h3 class="mt-3" style="font-size:16px">Plain-text version</h3>
    <pre class="code"><?= e((string) ($preview['text'] ?? '')) ?></pre>

    <h3 class="mt-3" style="font-size:16px">Available tokens</h3>
    <p class="form-help">Write these inside double braces. Values are escaped for the HTML body automatically; a token with no value becomes an empty string.</p>
    <p>
      <?php foreach ($tokens as $token): ?>
        <span class="mono badge badge-grey" style="margin:0 4px 6px 0">{{<?= e($token) ?>}}</span>
      <?php endforeach; ?>
    </p>
  </section>
</div>
