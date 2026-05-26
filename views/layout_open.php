<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('app.title')) ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        :root { --bg:#0f1115; --card:#1a1d24; --text:#e6e8ec; --muted:#8a93a6; --accent:#fc4c02; --good:#22c55e; --warn:#f59e0b; --info:#60a5fa; }
        * { box-sizing: border-box; }
        body { margin:0; font:16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg); color:var(--text); -webkit-text-size-adjust: 100%; }
        .container { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        header { display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 12px; margin-bottom: 32px; }
        h1 { font-size: 22px; margin: 0; }
        h2 { font-size: 18px; margin: 0 0 16px; }
        a.btn, button.btn { display:inline-block; background: var(--accent); color:#fff; padding: 12px 20px; border-radius: 8px; text-decoration:none; font-weight: 600; }
        a.btn:hover, button.btn:hover { opacity: 0.9; }
        a.muted { color: var(--muted); text-decoration: none; font-size: 14px; }
        .card { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .grid { display:grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px; }
        .stat { background: var(--card); border-radius: 12px; padding: 18px; }
        .stat .label { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
        .stat .value { font-size: 28px; font-weight: 700; margin-top: 6px; word-break: break-word; }
        .stat .delta { font-size: 13px; margin-top: 4px; }
        .tip { border-left: 4px solid var(--info); padding-left: 16px; margin-bottom: 12px; }
        .tip.good { border-color: var(--good); }
        .tip.warn { border-color: var(--warn); }
        .tip.info { border-color: var(--info); }
        .tip .title { font-weight: 600; margin-bottom: 4px; }
        .tip .body { color: var(--muted); font-size: 15px; }
        .bars { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding-top: 8px; }
        .bar { flex: 1; background: var(--accent); border-radius: 4px 4px 0 0; opacity: 0.85; position: relative; min-height: 2px; }
        .bar:hover { opacity: 1; }
        .bar-labels { display:flex; gap: 6px; font-size: 11px; color: var(--muted); margin-top: 6px; }
        .bar-labels span { flex:1; text-align:center; }
        .hero { text-align: center; padding: 60px 20px; }
        .hero h1 { font-size: 38px; margin-bottom: 16px; }
        .hero p { color: var(--muted); max-width: 560px; margin: 0 auto 32px; }
        .plan-day { display: grid; grid-template-columns: 50px 140px 1fr; gap: 12px; padding: 10px 12px; background: #0f1115; border-radius: 8px; align-items: start; }
        .plan-day .day-name { color: var(--muted); font-weight: 600; font-size: 13px; }
        .plan-day .day-type { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #fff; }
        .plan-day .day-desc { color: var(--muted); font-size: 14px; }
        .week-head { display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap: 8px; margin-bottom: 12px; }
        .phase-pill { color: #000; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .field { display:block; margin: 16px 0 6px; font-weight: 600; }
        .input { width:100%; padding:10px; border-radius:6px; background:#0f1115; color:var(--text); border:1px solid #2a2f3a; font-size:16px; }
        .coach-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .coach-tab { display: flex; align-items: center; gap: 6px; padding: 10px 14px; background: var(--card); color: var(--muted); border-radius: 8px; text-decoration: none; font-size: 14px; }
        .coach-tab:hover { color: var(--text); }
        .coach-tab.active { color: var(--text); background: #2a2f3a; }
        .coach-tab-icon { font-size: 16px; }
        .coach-chat { display: flex; flex-direction: column; gap: 10px; min-height: 120px; max-height: 540px; overflow-y: auto; margin-bottom: 16px; padding-right: 4px; }
        .coach-empty { color: var(--muted); font-size: 14px; padding: 20px 8px; text-align: center; }
        .msg { padding: 10px 14px; border-radius: 12px; max-width: 85%; line-height: 1.5; font-size: 15px; word-wrap: break-word; }
        .msg.user { align-self: flex-end; background: var(--accent); color: #fff; }
        .msg.model { align-self: flex-start; background: #0f1115; color: var(--text); }
        .msg.error { align-self: stretch; max-width: 100%; background: rgba(245, 158, 11, 0.12); color: var(--warn); border: 1px solid var(--warn); font-size: 14px; }
        .coach-form textarea { width: 100%; padding: 12px; background: #0f1115; color: var(--text); border: 1px solid #2a2f3a; border-radius: 8px; font-size: 16px; font-family: inherit; resize: vertical; }
        .coach-form-actions { display: flex; align-items: center; gap: 16px; margin-top: 10px; }
        .coach-form-actions button { border: none; cursor: pointer; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #1f2330; }
        .brand { display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none; }
        .brand-logo { width: 28px; height: 28px; flex-shrink: 0; }
        .brand-name { font-weight: 600; font-size: 15px; letter-spacing: -0.01em; }
        .lang-bar { display:flex; gap: 4px; font-size: 12px; }
        .lang-bar a { color: var(--muted); text-decoration: none; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: .05em; }
        .lang-bar a:hover { color: var(--text); }
        .lang-bar a.active { color: var(--text); background: #0f1115; }
        .loading-overlay { display: none; position: fixed; inset: 0; background: rgba(15,17,21,0.85); backdrop-filter: blur(2px); z-index: 1000; align-items: center; justify-content: center; flex-direction: column; gap: 20px; }
        .loading-overlay.visible { display: flex; }
        .loading-spinner { width: 56px; height: 56px; border: 4px solid rgba(255,255,255,0.08); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.9s linear infinite; }
        .loading-text { color: var(--text); font-size: 16px; max-width: 320px; text-align: center; padding: 0 20px; }
        .loading-hint { color: var(--muted); font-size: 13px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 600px) {
            .container { padding: 20px 14px; }
            .brand-name { display: none; }
            header { margin-bottom: 20px; }
            header h1 { font-size: 20px; }
            h2 { font-size: 16px; }
            .card { padding: 16px; }
            .stat { padding: 14px; }
            .stat .value { font-size: 22px; }
            .hero { padding: 32px 8px; }
            .hero h1 { font-size: 26px; }
            .bars { gap: 4px; height: 100px; }
            .bar-labels { gap: 4px; font-size: 10px; }
            .bar-labels span:nth-child(even) { display: none; }
            .plan-day { grid-template-columns: 1fr; gap: 4px; padding: 12px; }
            .plan-day .day-name { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        }
    </style>
</head>
<body>
<div id="loading-overlay" class="loading-overlay" role="status" aria-live="polite">
    <div class="loading-spinner" aria-hidden="true"></div>
    <div class="loading-text"></div>
    <div class="loading-hint"><?= e(t('loading.hint')) ?></div>
</div>
<div class="container">
<div class="top-bar">
    <a class="brand" href="<?= current_access_token() ? 'dashboard.php' : 'index.php' ?>">
        <img class="brand-logo" src="/favicon.svg" alt="">
        <span class="brand-name"><?= e(t('app.title')) ?></span>
    </a>
    <div class="lang-bar">
        <?php foreach (I18n::supported() as $loc): ?>
            <a href="<?= e(lang_url($loc)) ?>" class="<?= I18n::locale() === $loc ? 'active' : '' ?>"><?= e($loc) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('[data-loading]')) return;
    var overlay = document.getElementById('loading-overlay');
    overlay.querySelector('.loading-text').textContent = form.dataset.loading || '';
    overlay.classList.add('visible');
});
window.addEventListener('pageshow', function () {
    var overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.classList.remove('visible');
});
</script>
