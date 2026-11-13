<?php
require __DIR__ . '/../includes/init.php';
$u = require_role('admin');
$rows = all('SELECT * FROM email_log ORDER BY id DESC LIMIT 50');
page_start('Email log', 'admin', 'admin/emails.php');
?>
<div class="page-head"><div><h1>Email log</h1><div class="text-muted">Every email the system sends is saved here, even without SMTP. Set up SMTP in config.php to deliver them.</div></div>
  <?php if ($rows): ?><button type="button" class="btn btn-primary" id="logPdf"><i class="bi bi-download"></i> Download PDF</button><?php endif; ?></div>
<div class="vstack gap-2" id="emailLog">
<?php if (!$rows): ?><div class="bh-card empty"><i class="bi bi-envelope"></i>No emails yet.</div><?php endif; ?>
<?php foreach ($rows as $m): $when = date('M j, Y g:i A', strtotime($m['created_at'])); ?>
  <details class="bh-card py-2" data-subject="<?= e($m['subject']) ?>" data-to="<?= e($m['to_email']) ?>" data-when="<?= e($when) ?>" data-status="<?= e($m['status'] === 'sent' ? 'Sent' : ($m['status'] === 'failed' ? 'Failed' : '')) ?>">
    <summary class="d-flex flex-wrap justify-content-between gap-2" style="cursor:pointer"><span><b><?= e($m['subject']) ?></b> <span class="text-muted small">to <?= e($m['to_email']) ?></span></span><span class="small"><?php if ($m['status'] === 'sent'): ?><span class="bh-badge bh-ok">Sent</span> <?php elseif ($m['status'] === 'failed'): ?><span class="bh-badge bh-bad">Failed</span> <?php endif; ?><?= $when ?></span></summary>
    <iframe sandbox class="w-100 border rounded mt-3 bg-white" style="height:340px" srcdoc="<?= e($m['body']) ?>" title="Email preview"></iframe>
    <div class="mt-2 text-end"><button type="button" class="btn btn-soft btn-sm js-email-pdf"><i class="bi bi-download"></i> Download PDF</button></div>
    <template><?= e($m['body']) ?></template>
  </details>
<?php endforeach; ?>
</div>
<?php page_end('<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
  const opt = name => ({ margin: 10, filename: name, image: { type: "jpeg", quality: 0.95 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: "mm", format: "a4" } });
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[c]));
  // Render HTML in a throw-away, off-screen sheet so scripts never run and the page layout is untouched.
  function render(html, name) {
    if (typeof html2pdf === "undefined") { alert("The PDF tool could not load. Check your internet connection and try again."); return; }
    const host = document.createElement("div");
    host.style.cssText = "position:fixed;left:-10000px;top:0;width:700px;background:#fff;color:#1D2B47;font-family:Arial,sans-serif";
    host.innerHTML = html;
    host.querySelectorAll("script,iframe,object,embed,link,meta").forEach(n => n.remove());
    host.querySelectorAll("*").forEach(n => [...n.attributes].forEach(a => { if (/^on/i.test(a.name)) n.removeAttribute(a.name); }));
    document.body.appendChild(host);
    html2pdf().set(opt(name)).from(host).save().finally(() => host.remove());
  }
  document.querySelectorAll(".js-email-pdf").forEach(btn => btn.addEventListener("click", () => {
    const d = btn.closest("details");
    const body = d.querySelector("template").content.textContent || d.querySelector("template").innerHTML;
    const head = "<div style=\\"border-bottom:3px solid #88AADD;padding-bottom:10px;margin-bottom:14px\\"><div style=\\"font-size:12px;color:#5D6B86\\">BoardingHub email log</div><h2 style=\\"margin:4px 0\\">" + esc(d.dataset.subject) + "</h2><div style=\\"font-size:13px;color:#5D6B86\\">To: " + esc(d.dataset.to) + " &middot; " + esc(d.dataset.when) + (d.dataset.status ? " &middot; " + esc(d.dataset.status) : "") + "</div></div>";
    render(head + body, "email-" + d.dataset.when.replace(/[^0-9A-Za-z]+/g, "-") + ".pdf");
  }));
  const all = document.getElementById("logPdf");
  if (all) all.addEventListener("click", () => {
    const rows = [...document.querySelectorAll("#emailLog details")].map(d => "<tr><td style=\\"padding:6px;border-bottom:1px solid #D3DEEF;font-size:12px\\">" + esc(d.dataset.when) + "</td><td style=\\"padding:6px;border-bottom:1px solid #D3DEEF;font-size:12px\\">" + esc(d.dataset.to) + "</td><td style=\\"padding:6px;border-bottom:1px solid #D3DEEF;font-size:12px\\">" + esc(d.dataset.subject) + "</td><td style=\\"padding:6px;border-bottom:1px solid #D3DEEF;font-size:12px\\">" + esc(d.dataset.status) + "</td></tr>").join("");
    render("<h2 style=\\"margin:0 0 4px\\">BoardingHub email log</h2><div style=\\"font-size:12px;color:#5D6B86;margin-bottom:12px\\">Printed " + new Date().toLocaleString() + "</div><table style=\\"width:100%;border-collapse:collapse;text-align:left\\"><tr><th style=\\"padding:6px;font-size:12px\\">Date</th><th style=\\"padding:6px;font-size:12px\\">To</th><th style=\\"padding:6px;font-size:12px\\">Subject</th><th style=\\"padding:6px;font-size:12px\\">Status</th></tr>" + rows + "</table>", "boardinghub-email-log.pdf");
  });
})();
</script>'); ?>
