<?php
/**
 * Donation checkout form.
 *
 * @var array $target
 * @var string $targetSlug
 * @var string $targetType
 * @var array $presets   minor units
 * @var int $selectedMinor
 * @var array $gateways
 * @var bool $gatewayReady
 * @var string $currency
 * @var int $minMinor
 * @var int $maxMinor
 * @var string $donorName
 * @var string $donorEmail
 * @var string $donorPhone
 */

$record = $target['record'] ?? [];
$title = (string) ($record['title'] ?? $record['name'] ?? 'Almarah Foundation');
$isGeneral = ($target['kind'] ?? '') === 'general';
$raised = (int) ($record['raised_minor'] ?? 0);
$goal = (int) ($record['goal_minor'] ?? 0);
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$returnUrl = base_url('donate/' . $targetSlug) . ($targetType !== 'fundraiser' ? '?type=' . urlencode($targetType) : '');
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Secure Donation</span>
    <h1>Donate to <?= e($title) ?></h1>
    <p>You will be taken to the bank&rsquo;s own secure page to enter your card details. We never see or store them.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="fr-layout">
      <div>
        <?php if (!$gatewayReady): ?>
          <div class="alert alert-warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
            <div>
              <strong>Online payments are not available right now.</strong>
              <p>Our payment gateway credentials have not been configured yet, so no donation can be taken through this form. Please contact <a href="<?= e(base_url('support')) ?>">our team</a> to give another way.</p>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('donate/' . $targetSlug)) ?>" class="panel" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="type" value="<?= e($targetType) ?>">
          <input type="hidden" name="amount" id="amountValue" data-amount-input value="<?= e(number_format($selectedMinor / 100, 2, '.', '')) ?>">

          <fieldset>
            <legend>1. Choose an amount</legend>

            <div class="amount-grid">
              <?php foreach ($presets as $preset): ?>
                <button class="amount-btn <?= (int) $preset === $selectedMinor ? 'active' : '' ?>" type="button" data-amount="<?= e(number_format((int) $preset / 100, 0, '.', '')) ?>">
                  <?= e(money_short((int) $preset, $currency)) ?>
                </button>
              <?php endforeach; ?>
            </div>

            <div class="field mt-2 <?= error_for('amount') !== '' ? 'is-invalid' : '' ?>">
              <label for="amountCustom">Or enter your own amount (<?= e($currency) ?>)<span class="req">*</span></label>
              <div class="input-group">
                <span class="prefix">Rs</span>
                <input class="input" type="number" inputmode="decimal" id="amountCustom" name="amount_custom"
                       min="<?= e((string) (int) ($minMinor / 100)) ?>" max="<?= e((string) (int) ($maxMinor / 100)) ?>" step="1"
                       placeholder="2500" data-amount-custom value="<?= old('amount_custom') ?>">
              </div>
              <span class="form-help">Minimum <?= e(money($minMinor, $currency)) ?>, maximum <?= e(money($maxMinor, $currency)) ?> per donation.</span>
              <?php if (error_for('amount') !== ''): ?><span class="field-error"><?= error_for('amount') ?></span><?php endif; ?>
            </div>
          </fieldset>

          <fieldset>
            <legend>2. Your details</legend>

            <div class="form-row">
              <div class="field <?= error_for('name') !== '' ? 'is-invalid' : '' ?>">
                <label for="name">Full name<span class="req">*</span></label>
                <input class="input" type="text" id="name" name="name" required maxlength="120"
                       value="<?= old('name', $donorName) ?>" autocomplete="name">
                <?php if (error_for('name') !== ''): ?><span class="field-error"><?= error_for('name') ?></span><?php endif; ?>
              </div>

              <div class="field <?= error_for('email') !== '' ? 'is-invalid' : '' ?>">
                <label for="email">Email address<span class="req">*</span></label>
                <input class="input" type="email" id="email" name="email" required maxlength="190"
                       value="<?= old('email', $donorEmail) ?>" autocomplete="email">
                <span class="form-help">Your receipt and payment updates are sent here.</span>
                <?php if (error_for('email') !== ''): ?><span class="field-error"><?= error_for('email') ?></span><?php endif; ?>
              </div>
            </div>

            <div class="field <?= error_for('phone') !== '' ? 'is-invalid' : '' ?>">
              <label for="phone">Phone (optional)</label>
              <input class="input" type="tel" id="phone" name="phone" maxlength="30"
                     value="<?= old('phone', $donorPhone) ?>" autocomplete="tel">
              <?php if (error_for('phone') !== ''): ?><span class="field-error"><?= error_for('phone') ?></span><?php endif; ?>
            </div>

            <div class="field">
              <label for="message">Leave a message (optional)</label>
              <textarea class="textarea" id="message" name="message" maxlength="500" rows="3"><?= old('message') ?></textarea>
              <span class="form-help">Your message appears on the fundraiser page unless you give anonymously.</span>
            </div>

            <div class="checkbox-row mt-1">
              <input type="checkbox" id="anonymous" name="anonymous" value="1" <?= old('anonymous') !== '' ? 'checked' : '' ?>>
              <label for="anonymous">Give anonymously — hide my name from the public page</label>
            </div>
          </fieldset>

          <fieldset>
            <legend>3. Payment method</legend>

            <?php if ($gateways === []): ?>
              <p class="text-muted">No payment method is available right now. Please try again later or contact support.</p>
            <?php else: ?>
              <div class="choice-grid">
                <?php foreach ($gateways as $index => $gateway): ?>
                  <label class="choice <?= $index === 0 ? 'active' : '' ?>">
                    <input type="radio" name="gateway" value="<?= e((string) $gateway['code']) ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                    <span>
                      <b><?= e((string) $gateway['label']) ?></b>
                      <em><?= e((string) ($gateway['description'] ?: 'Debit or credit card, verified by your bank')) ?></em>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php if (error_for('gateway') !== ''): ?><span class="field-error"><?= error_for('gateway') ?></span><?php endif; ?>
            <?php endif; ?>
          </fieldset>

          <div class="form-actions">
            <button class="btn btn-brand btn-lg" type="submit" <?= $gateways === [] ? 'disabled' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              Continue to secure payment
            </button>
            <a class="btn btn-light btn-lg" href="<?= e($isGeneral ? base_url('fundraisers') : base_url(($targetType === 'team' ? 'teams/' : ($targetType === 'campaign' ? 'campaigns/' : 'fundraisers/')) . $targetSlug)) ?>">Cancel</a>
          </div>

          <p class="form-help mt-2">
            By continuing you agree to our <a href="<?= e(base_url('terms')) ?>">terms</a> and
            <a href="<?= e(base_url('privacy-policy')) ?>">privacy policy</a>. Payments are processed by the bank;
            your card number is never sent to our servers.
          </p>
        </form>
      </div>

      <aside>
        <div class="donation-summary preview-sticky">
          <h3 style="margin:0 0 14px">Donation summary</h3>
          <dl>
            <div class="row">
              <dt>Giving to</dt>
              <dd><?= e($title) ?></dd>
            </div>
            <div class="row">
              <dt>Type</dt>
              <dd><?= e(ucfirst($isGeneral ? 'general fund' : $targetType)) ?></dd>
            </div>
            <div class="row">
              <dt>Amount</dt>
              <dd data-summary-amount><?= e(money($selectedMinor, $currency)) ?></dd>
            </div>
            <div class="row" style="display:none" data-cover-fee-row>
              <dt>Processing</dt>
              <dd>Paid by Almarah Foundation</dd>
            </div>
            <div class="row total">
              <dt>Total</dt>
              <dd data-summary-amount><?= e(money($selectedMinor, $currency)) ?></dd>
            </div>
          </dl>

          <?php if ($goal > 0): ?>
            <div class="mt-3">
              <div class="progress sm" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
                <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
              </div>
              <p class="text-muted" style="font-size:13.5px;margin:8px 0 0">
                <?= e(money($raised, $currency)) ?> raised of <?= e(money($goal, $currency)) ?> — <?= e((string) (int) round($percent)) ?>% funded.
              </p>
            </div>
          <?php endif; ?>

          <ul class="checkline mt-3" style="font-size:13.5px;display:grid;gap:8px">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg><span class="text-muted">Verified server-to-server before your donation is counted</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg><span class="text-muted">Receipt emailed to you with a public reference</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg><span class="text-muted">Card details never touch our servers</span></li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</section>
