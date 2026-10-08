<?php
/** 404 page. */
?>
<section class="section">
  <div class="wrap">
    <div class="empty-state" style="padding:70px 20px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="width:64px;height:64px"><circle cx="12" cy="12" r="9"/><path d="M9 9.5h.01M15 9.5h.01M8.5 15.5s1.3-1.5 3.5-1.5 3.5 1.5 3.5 1.5"/></svg>
      <h3>We could not find that page</h3>
      <p>The link may be broken, or the fundraiser may have been removed or archived by its owner.</p>
      <div class="cta-actions">
        <a class="btn btn-brand" href="<?= e(base_url('/')) ?>">Back to home</a>
        <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a>
      </div>
    </div>
  </div>
</section>
