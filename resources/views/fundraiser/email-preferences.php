<?php
/**
 * Per-event email preferences.
 *
 * @var array $preferences  event_key => bool
 */

$labels = [
    'account.created'             => ['Account welcome', 'A one-time message when your account is created.', true],
    'account.verify_email'        => ['Email verification', 'The link that proves the address is yours.', true],
    'account.password_reset'      => ['Password resets', 'Sent when you ask for a new password.', true],
    'account.password_changed'    => ['Password change alerts', 'A security notice whenever your password changes.', true],
    'account.suspended'           => ['Account notices', 'Only sent if an administrator takes action on your account.', true],
    'account.reactivated'         => ['Account notices', 'Only sent if your account is restored.', true],
    'fundraiser.submitted'        => ['Fundraiser updates', 'When you submit a page, and when it is approved or needs changes.', false],
    'fundraiser.approved'         => ['Fundraiser approvals', 'The moment your page goes live.', false],
    'fundraiser.rejected'         => ['Fundraiser decisions', 'If our team needs changes before it can go live.', true],
    'fundraiser.paused'           => ['Fundraiser decisions', 'If a fundraiser is paused.', true],
    'fundraiser.published'        => ['Fundraiser approvals', 'When a fundraiser becomes public.', false],
    'fundraiser.update_published' => ['Update confirmations', 'A copy of each update you post.', false],
    'fundraiser.goal_reached'     => ['Goal reached', 'A celebration when you hit your target.', false],
    'fundraiser.ending_soon'      => ['Ending soon reminders', 'A nudge a few days before your fundraiser ends.', false],
    'fundraiser.new_donation'     => ['New donation alerts', 'Tell me every time somebody donates.', false],
    'team.invitation'             => ['Team invitations', 'When someone invites you to a team.', true],
    'team.member_joined'          => ['Team activity', 'When somebody joins a team you captain.', false],
    'donation.receipt'            => ['Donation receipts', 'Your tax-style receipt for every completed donation.', true],
    'donation.pending'            => ['Payment updates', 'While a payment is being confirmed by the bank.', true],
    'donation.failed'             => ['Payment updates', 'If a payment does not go through.', true],
    'donation.cancelled'          => ['Payment updates', 'If a payment is cancelled.', true],
    'donation.refunded'           => ['Payment updates', 'If a donation is refunded.', true],
    'admin.payment_alert'         => ['Administrator alerts', 'Internal alerts about payment problems.', true],
];

$required = ['account.verify_email', 'account.password_reset', 'donation.receipt'];
?>
<div class="dash-head">
  <div>
    <h1>Email preferences</h1>
    <p class="sub">Choose what lands in your inbox. Security and receipt emails always go out.</p>
  </div>
</div>

<form method="post" action="<?= e(base_url('dashboard/email-preferences')) ?>" class="panel">
  <?= csrf_field() ?>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Email type</th><th class="wrap">What it is</th><th>Send it</th></tr>
      </thead>
      <tbody>
        <?php foreach ($labels as $eventKey => [$title, $description, $essential]): ?>
          <?php
            $enabled = array_key_exists($eventKey, $preferences) ? (bool) $preferences[$eventKey] : !$essential;
            $locked = in_array($eventKey, $required, true);
          ?>
          <tr>
            <td><?= e($title) ?></td>
            <td class="wrap muted"><?= e($description) ?></td>
            <td>
              <?php if ($locked): ?>
                <span class="badge badge-green">Required</span>
                <input type="hidden" name="pref_<?= e($eventKey) ?>" value="1">
              <?php else: ?>
                <label class="checkbox-row" style="margin:0">
                  <input type="checkbox" name="pref_<?= e($eventKey) ?>" value="1" <?= $enabled ? 'checked' : '' ?>>
                  <span class="sr-only">Send <?= e($title) ?></span>
                </label>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <button class="btn btn-brand btn-lg mt-3" type="submit">Save preferences</button>
  <p class="form-help">You can also unsubscribe from occasional stories only — security and receipt emails are part of the service and cannot be switched off.</p>
</form>
