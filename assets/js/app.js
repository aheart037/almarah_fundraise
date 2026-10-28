/* ==========================================================================
   Almarah Foundation — fundraising platform UI
   Vanilla JS, no dependencies. Everything degrades gracefully: the site is
   fully usable with JavaScript disabled.
   ========================================================================== */
(function () {
  "use strict";

  /* ---------- Mobile navigation ---------- */
  function initNav() {
    var toggle = document.querySelector("[data-nav-toggle]");
    var panel = document.getElementById("mobilePanel");
    if (!toggle || !panel) return;

    toggle.addEventListener("click", function () {
      var open = panel.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", String(open));
      document.body.style.overflow = open ? "hidden" : "";
    });

    panel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        panel.classList.remove("open");
        toggle.classList.remove("open");
        document.body.style.overflow = "";
      });
    });

    toggle.addEventListener("keydown", function (e) {
      if (e.key === "Escape") toggle.click();
    });
  }

  /* ---------- Sticky header ---------- */
  function initStickyHeader() {
    var header = document.getElementById("site-header");
    if (!header) return;
    var onScroll = function () {
      header.classList.toggle("is-stuck", window.scrollY > 40);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* ---------- Reveal on scroll ---------- */
  function initReveal() {
    var items = document.querySelectorAll(".reveal");
    if (!items.length) return;

    if (!("IntersectionObserver" in window)) {
      items.forEach(function (el) { el.classList.add("in"); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("in");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

    items.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Progress bars: width comes from data-w ---------- */
  function initBars() {
    document.querySelectorAll(".progress > span[data-w]").forEach(function (bar) {
      var target = Math.max(0, Math.min(100, parseFloat(bar.getAttribute("data-w")) || 0));
      bar.style.width = "0%";
      window.requestAnimationFrame(function () {
        window.setTimeout(function () { bar.style.width = target + "%"; }, 60);
      });
    });
  }

  /* ---------- Animated counters ---------- */
  function initCounters() {
    var nodes = document.querySelectorAll("[data-count]");
    if (!nodes.length) return;

    var run = function (el) {
      var target = parseFloat(el.getAttribute("data-count")) || 0;
      var suffix = el.getAttribute("data-suffix") || "";
      var duration = 1200;
      var start = null;

      function step(ts) {
        if (start === null) start = ts;
        var progress = Math.min(1, (ts - start) / duration);
        var value = Math.floor(target * (1 - Math.pow(1 - progress, 3)));
        el.textContent = value.toLocaleString("en-PK") + suffix;
        if (progress < 1) window.requestAnimationFrame(step);
      }

      window.requestAnimationFrame(step);
    };

    if (!("IntersectionObserver" in window)) {
      nodes.forEach(run);
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { run(entry.target); io.unobserve(entry.target); }
      });
    }, { threshold: 0.4 });

    nodes.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Toast ---------- */
  function toast(message) {
    var el = document.getElementById("toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "toast";
      el.className = "toast";
      document.body.appendChild(el);
    }
    el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span></span>';
    el.querySelector("span").textContent = message;
    window.requestAnimationFrame(function () { el.classList.add("show"); });
    window.setTimeout(function () { el.classList.remove("show"); }, 2600);
  }

  /* ---------- Donation amount picker ---------- */
  function initAmounts() {
    var buttons = document.querySelectorAll(".amount-btn[data-amount]");
    var input = document.querySelector("[data-amount-input]");
    var custom = document.querySelector("[data-amount-custom]");
    if (!buttons.length || !input) return;

    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        buttons.forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        var amount = btn.getAttribute("data-amount");
        input.value = amount;
        if (custom) custom.value = amount;
        updateSummary();
      });
    });

    if (custom) {
      custom.addEventListener("input", function () {
        buttons.forEach(function (b) { b.classList.remove("active"); });
        input.value = custom.value;
        updateSummary();
      });
    }

    input.addEventListener("input", updateSummary);
  }

  /* ---------- Live donation summary ---------- */
  function updateSummary() {
    var input = document.querySelector("[data-amount-input]");
    var targets = document.querySelectorAll("[data-summary-amount]");
    var cover = document.querySelector("[data-cover-fees]");
    var coverRow = document.querySelector("[data-cover-fee-row]");
    if (!input || !targets.length) return;

    // Both the "Amount" and the "Total" rows carry data-summary-amount.
    var amount = parseFloat(String(input.value).replace(/[^0-9.]/g, "")) || 0;
    var text = "Rs " + amount.toLocaleString("en-PK", { maximumFractionDigits: 2 });
    targets.forEach(function (target) { target.textContent = text; });

    if (coverRow && cover) {
      coverRow.style.display = cover.checked ? "flex" : "none";
    }
  }

  function initChoices() {
    var radios = document.querySelectorAll(".choice input[type=radio]");
    if (!radios.length) return;
    function sync() {
      radios.forEach(function (r) {
        var box = r.closest(".choice");
        if (box) box.classList.toggle("active", r.checked);
      });
    }
    radios.forEach(function (r) { r.addEventListener("change", sync); });
    sync();
  }

  function initSummary() {
    var cover = document.querySelector("[data-cover-fees]");
    if (cover) cover.addEventListener("change", updateSummary);
    updateSummary();
  }

  /* ---------- Confirm destructive actions ---------- */
  function initConfirms() {
    document.addEventListener("submit", function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var message = form.getAttribute("data-confirm");
      if (message && !window.confirm(message)) {
        e.preventDefault();
        return;
      }

      // Guard against double submission of slow payment/approval requests.
      var submit = form.querySelector("[type=submit]");
      if (submit && form.getAttribute("data-busy") !== "off") {
        window.setTimeout(function () {
          submit.disabled = true;
          submit.dataset.originalText = submit.textContent;
          submit.textContent = "Working…";
        }, 0);
      }
    });
  }

  /* ---------- Copy to clipboard ---------- */
  function initCopy() {
    document.querySelectorAll("[data-copy]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var text = btn.getAttribute("data-copy");
        var done = function () { toast("Copied to clipboard"); };

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done, function () { toast("Copy failed"); });
          return;
        }

        var area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "");
        area.style.position = "fixed";
        area.style.opacity = "0";
        document.body.appendChild(area);
        area.select();
        try { document.execCommand("copy"); done(); } catch (err) { toast("Copy failed"); }
        document.body.removeChild(area);
      });
    });
  }

  /* ---------- Share buttons ---------- */
  function initShare() {
    document.querySelectorAll("[data-share]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var url = btn.getAttribute("data-share");
        var title = btn.getAttribute("data-share-title") || document.title;

        if (navigator.share) {
          navigator.share({ title: title, url: url }).catch(function () {});
          return;
        }

        if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(function () {
            toast("Link copied — share it anywhere");
          }, function () {});
        }
      });
    });
  }

  /* ---------- Modals (progressive enhancement of <details> free dialogs) ---------- */
  function initModals() {
    document.querySelectorAll("[data-modal-open]").forEach(function (trigger) {
      trigger.addEventListener("click", function (e) {
        var modal = document.getElementById(trigger.getAttribute("data-modal-open"));
        if (!modal) return;
        e.preventDefault();
        modal.classList.add("open");
        document.body.style.overflow = "hidden";
        var focusable = modal.querySelector("input, textarea, select, button");
        if (focusable) focusable.focus();
      });
    });

    document.querySelectorAll("[data-modal-close]").forEach(function (closer) {
      closer.addEventListener("click", function () {
        var modal = closer.closest(".modal-back");
        if (!modal) return;
        modal.classList.remove("open");
        document.body.style.overflow = "";
      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      document.querySelectorAll(".modal-back.open").forEach(function (modal) {
        modal.classList.remove("open");
        document.body.style.overflow = "";
      });
    });
  }

  /* ---------- Auto-submit filter selects ---------- */
  function initAutoSubmit() {
    document.querySelectorAll("[data-autosubmit]").forEach(function (el) {
      el.addEventListener("change", function () {
        var form = el.closest("form");
        if (form) form.submit();
      });
    });
  }

  /* ---------- Admin branding image previews ---------- */
  function initBrandingPreviews() {
    document.querySelectorAll("[data-branding-input]").forEach(function (input) {
      var preview = document.getElementById(input.getAttribute("data-branding-preview"));
      var fallback = document.getElementById(input.getAttribute("data-branding-fallback"));
      var remove = document.getElementById(input.getAttribute("data-branding-remove"));
      var currentSrc = input.getAttribute("data-current-src") || "";
      var objectUrl = "";

      if (!preview || !fallback) return;

      function showFallback() {
        preview.hidden = true;
        fallback.hidden = false;
      }

      function releaseObjectUrl() {
        if (objectUrl && window.URL && window.URL.revokeObjectURL) {
          window.URL.revokeObjectURL(objectUrl);
        }
        objectUrl = "";
      }

      function showCurrent() {
        if (currentSrc) {
          preview.src = currentSrc;
          preview.hidden = false;
          fallback.hidden = true;
        } else {
          showFallback();
        }
      }

      input.addEventListener("change", function () {
        var file = input.files && input.files[0];
        if (!file || !window.URL || !window.URL.createObjectURL) {
          releaseObjectUrl();
          showCurrent();
          return;
        }

        if (remove) remove.checked = false;
        releaseObjectUrl();
        objectUrl = window.URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        fallback.hidden = true;
      });

      if (remove) {
        remove.addEventListener("change", function () {
          if (remove.checked) {
            input.value = "";
            releaseObjectUrl();
            showFallback();
          } else {
            showCurrent();
          }
        });
      }
    });
  }

  /* ---------- Add-another repeatable rows (impact numbers, etc.) ---------- */
  function initRepeaters() {
    document.querySelectorAll("[data-repeater-add]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var list = document.querySelector(btn.getAttribute("data-repeater-add"));
        if (!list) return;
        var template = list.querySelector("[data-repeater-row]");
        if (!template) return;
        var clone = template.cloneNode(true);
        clone.querySelectorAll("input, textarea").forEach(function (input) {
          var name = input.getAttribute("name");
          if (name) {
            input.setAttribute("name", name.replace(/\[\d+\]/, "[" + list.children.length + "]"));
          }
          input.value = "";
        });
        list.appendChild(clone);
      });
    });
  }

  /* ---------- Print ---------- */
  function initPrint() {
    document.querySelectorAll("[data-print]").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        window.print();
      });
    });
  }

  /* ---------- Fundraiser form (counters, quick picks, cover preview) ---------- */
  function initFundraiserForm() {
    var form = document.querySelector(".fr-form");
    if (!form) { return; }

    // Live character counters.
    form.querySelectorAll("[data-count-for]").forEach(function (counter) {
      var input = document.getElementById(counter.getAttribute("data-count-for"));
      if (!input) { return; }
      var min = parseInt(counter.getAttribute("data-min") || "0", 10);
      var max = parseInt(counter.getAttribute("data-max") || "0", 10);
      function update() {
        var len = input.value.length;
        if (min > 0 && len < min) {
          counter.textContent = len + " / " + min + " minimum";
          counter.className = "fr-count" + (len > 0 ? " is-warn" : "");
        } else {
          counter.textContent = len + (max ? " / " + max.toLocaleString() : "");
          counter.className = "fr-count" + (len > 0 ? " is-ok" : "");
        }
      }
      input.addEventListener("input", update);
      update();
    });

    // Goal quick picks and the "Rs 50,000" preview.
    var goal = document.getElementById("goal");
    var preview = document.getElementById("goalPreview");
    var goalChips = form.querySelectorAll("[data-goal]");
    function updateGoal() {
      if (!goal) { return; }
      var value = parseFloat(goal.value);
      if (preview) {
        preview.textContent = value > 0 ? "Rs " + Math.round(value).toLocaleString("en-US") : "";
      }
      goalChips.forEach(function (chip) {
        chip.classList.toggle("is-active", parseFloat(chip.getAttribute("data-goal")) === value);
      });
    }
    goalChips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        goal.value = chip.getAttribute("data-goal");
        updateGoal();
        goal.focus();
      });
    });
    if (goal) { goal.addEventListener("input", updateGoal); updateGoal(); }

    // End date quick picks.
    var endAt = document.getElementById("end_at");
    var dayChips = form.querySelectorAll("[data-days]");
    function pad(n) { return n < 10 ? "0" + n : String(n); }
    function isoIn(days) {
      var d = new Date();
      d.setDate(d.getDate() + days);
      return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
    }
    function updateDays() {
      dayChips.forEach(function (chip) {
        var days = parseInt(chip.getAttribute("data-days"), 10);
        var on = days === 0 ? endAt.value === "" : endAt.value === isoIn(days);
        chip.classList.toggle("is-active", on);
      });
    }
    if (endAt) {
      dayChips.forEach(function (chip) {
        chip.addEventListener("click", function () {
          var days = parseInt(chip.getAttribute("data-days"), 10);
          endAt.value = days === 0 ? "" : isoIn(days);
          updateDays();
        });
      });
      endAt.addEventListener("input", updateDays);
      updateDays();
    }

    // Cover photo: preview and a friendly size/type check before upload.
    var fileInput = document.getElementById("cover_image");
    var drop = document.getElementById("coverDrop");
    var img = document.getElementById("coverPreview");
    var err = document.getElementById("coverError");
    var maxBytes = parseInt(form.getAttribute("data-max-upload") || "0", 10);
    if (fileInput && drop && img) {
      fileInput.addEventListener("change", function () {
        var file = fileInput.files && fileInput.files[0];
        if (err) { err.hidden = true; err.textContent = ""; }
        if (!file) { return; }
        var okType = /^image\/(jpeg|png|webp)$/.test(file.type);
        if (!okType || (maxBytes && file.size > maxBytes)) {
          fileInput.value = "";
          if (err) {
            err.textContent = !okType ? "Please choose a JPG, PNG or WebP image."
              : "That photo is too large. Please choose one under " + Math.round(maxBytes / 1048576) + " MB.";
            err.hidden = false;
          }
          return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
          img.src = ev.target.result;
          img.hidden = false;
          drop.classList.add("has-image");
        };
        reader.readAsDataURL(file);
      });
      ["dragenter", "dragover"].forEach(function (name) {
        drop.addEventListener(name, function () { drop.classList.add("is-drag"); });
      });
      ["dragleave", "drop"].forEach(function (name) {
        drop.addEventListener(name, function () { drop.classList.remove("is-drag"); });
      });
    }

    // Stop double submissions.
    var submit = document.getElementById("frSubmit");
    form.addEventListener("submit", function () {
      if (submit) { setTimeout(function () { submit.classList.add("is-busy"); }, 0); }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initNav();
    initStickyHeader();
    initReveal();
    initBars();
    initCounters();
    initAmounts();
    initSummary();
    initChoices();
    initConfirms();
    initCopy();
    initShare();
    initModals();
    initAutoSubmit();
    initBrandingPreviews();
    initRepeaters();
    initPrint();
    initFundraiserForm();
  });
})();
