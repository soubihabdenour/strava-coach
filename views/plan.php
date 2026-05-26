<?php
/** @var array $plan */
/** @var ?array $completion */
/** @var bool $aiAvailable */
$phaseColors = [
    'base'  => '#60a5fa',
    'build' => '#a78bfa',
    'peak'  => '#fc4c02',
    'taper' => '#22c55e',
];
$dayColors = [
    'rest'    => '#3a3f4a',
    'easy'    => '#4b5563',
    'long'    => '#1e40af',
    'tempo'   => '#b45309',
    'quality' => '#7c2d12',
    'race'    => '#fc4c02',
    'brick'   => '#7c3aed',
];
$sportIcons = [
    'run' => 'run',
    'bike' => 'bike',
    'swim' => 'swim',
    'multi' => 'tri',
    'strength' => 'strength',
    'rest' => 'rest',
];
?>
<header>
    <h1><?= e(t('plan.title', t('goal.' . $plan['goal']))) ?></h1>
    <div>
        <?php if ($aiAvailable && ($plan['engine'] ?? 'rule') === 'ai'): ?>
            <form method="post" action="plan.php" style="display:inline;" data-loading="<?= e(t('loading.plan_regenerating')) ?>">
                <input type="hidden" name="action" value="regenerate">
                <button type="submit" class="muted" style="background:none; border:none; color: var(--muted); cursor:pointer; font-size: 14px; margin-right: 16px;">
                    <?= e(t('plan.regenerate')) ?>
                </button>
            </form>
        <?php endif; ?>
        <a class="muted" href="plan.php?reset=1" style="margin-right: 16px;"><?= e(t('plan.new')) ?></a>
        <a class="muted" href="dashboard.php"><?= e(t('plan.back_dashboard')) ?></a>
    </div>
</header>

<?php if (($plan['locale'] ?? 'en') !== I18n::locale()): ?>
    <div class="card" style="border-left: 4px solid var(--info);">
        <div style="color: var(--muted);"><?= e(t('plan.locale_hint')) ?></div>
    </div>
<?php endif; ?>

<div class="grid">
    <div class="stat">
        <div class="label"><?= e(t('plan.weeks')) ?></div>
        <div class="value"><?= $plan['weeks_total'] ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('plan.start')) ?></div>
        <div class="value" style="font-size: 18px;"><?= e($plan['start_date']) ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('plan.goal_date')) ?></div>
        <div class="value" style="font-size: 18px;"><?= e($plan['goal_date']) ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('plan.engine')) ?></div>
        <div class="value" style="font-size: 18px;">
            <?= ($plan['engine'] ?? 'rule') === 'ai' ? '🤖 AI' : '📐 Rule' ?>
        </div>
    </div>
</div>

<?php if (!empty($plan['paces']['run']['has_data']) || !empty($plan['paces']['bike']['has_data']) || !empty($plan['paces']['swim']['has_data'])): ?>
    <div class="card">
        <h2><?= e(t('plan.zones_title')) ?></h2>
        <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <?php if (!empty($plan['paces']['run']['has_data'])): ?>
                <div>
                    <div style="font-weight:600; margin-bottom: 6px; display:flex; align-items:center; gap:6px; color: var(--pc-text);"><?= icon('run') ?> <?= e(t('zones.run')) ?></div>
                    <?php foreach ($plan['paces']['run']['zones'] as $z => $range): ?>
                        <div style="display:flex; justify-content:space-between; font-size:13px; padding: 3px 0; color: var(--muted);">
                            <span><?= e($z) ?></span><span style="color: var(--text);"><?= e($range) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($plan['paces']['bike']['has_data'])): ?>
                <div>
                    <div style="font-weight:600; margin-bottom: 6px; display:flex; align-items:center; gap:6px; color: var(--pc-text);">
                        <?= icon('bike') ?> <?= e(t('zones.bike')) ?>
                        <?php if (!empty($plan['paces']['bike']['ftp_estimate_w'])): ?>
                            <span style="color:var(--muted); font-weight:400; font-size:12px;">(FTP ≈ <?= $plan['paces']['bike']['ftp_estimate_w'] ?>W)</span>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($plan['paces']['bike']['zones_w'] as $z => $range): ?>
                        <div style="display:flex; justify-content:space-between; font-size:13px; padding: 3px 0; color: var(--muted);">
                            <span><?= e($z) ?></span><span style="color: var(--text);"><?= e($range) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($plan['paces']['swim']['has_data'])): ?>
                <div>
                    <div style="font-weight:600; margin-bottom: 6px; display:flex; align-items:center; gap:6px; color: var(--pc-text);"><?= icon('swim') ?> <?= e(t('zones.swim')) ?></div>
                    <?php foreach ($plan['paces']['swim']['zones'] as $z => $range): ?>
                        <div style="display:flex; justify-content:space-between; font-size:13px; padding: 3px 0; color: var(--muted);">
                            <span><?= e($z) ?></span><span style="color: var(--text);"><?= e($range) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$completionMap = [];
if ($completion) {
    foreach ($completion as $c) $completionMap[$c['start']] = $c;
}
?>

<?php foreach ($plan['weeks'] as $week): ?>
    <?php $c = $completionMap[$week['start']] ?? null; ?>
    <div class="card">
        <div class="week-head">
            <h2 style="margin: 0;">
                <?= e(t('plan.week_n', $week['index'])) ?>
                <span style="color: var(--muted); font-weight: 400; font-size: 14px; margin-left: 8px;">
                    <?= e(t('plan.starts_on', $week['start'])) ?>
                </span>
            </h2>
            <div>
                <span class="phase-pill" style="background: <?= $phaseColors[$week['phase']] ?? '#3a3f4a' ?>;">
                    <?= e(t('phase.' . $week['phase'])) ?>
                </span>
                <span style="color: var(--muted); margin-left: 12px;"><?= e(t('plan.km_target', $week['target_km'])) ?></span>
            </div>
        </div>
        <p style="color: var(--muted); margin: 0 0 12px;"><?= e($week['theme']) ?></p>

        <?php if ($c && $c['past'] && $c['prescribed_km'] > 0): ?>
            <?php
                $ratio = $c['ratio'];
                $color = $ratio >= 0.85 ? 'var(--good)' : ($ratio >= 0.6 ? 'var(--warn)' : '#ef4444');
                $width = min(100, $ratio * 100);
            ?>
            <div style="margin: 0 0 14px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; color: var(--muted); margin-bottom: 4px;">
                    <span><?= e(t('plan.completion')) ?></span>
                    <span><?= $c['actual_km'] ?> / <?= $c['prescribed_km'] ?> km · <?= (int)round($ratio * 100) ?>%</span>
                </div>
                <div style="height: 4px; background:#0f1115; border-radius: 2px; overflow:hidden;">
                    <div style="height:100%; width: <?= $width ?>%; background: <?= $color ?>;"></div>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: grid; gap: 8px;">
            <?php foreach ($week['days'] as $day): ?>
                <div class="plan-day">
                    <div class="day-name"><?= e(t('day.' . strtolower($day['day']))) ?></div>
                    <div>
                        <span class="day-type" style="background: <?= $dayColors[$day['type']] ?? '#3a3f4a' ?>;">
                            <?= icon($sportIcons[$day['sport'] ?? 'run'] ?? 'run') ?><?= e($day['title']) ?>
                        </span>
                    </div>
                    <div class="day-desc"><?= e($day['desc']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2><?= e(t('plan.howto.title')) ?></h2>
    <ul style="color: var(--muted); line-height: 1.8;">
        <li><strong style="color: var(--text);"><?= e(t('plan.howto.easy.title')) ?></strong> <?= e(t('plan.howto.easy.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('plan.howto.move.title')) ?></strong> <?= e(t('plan.howto.move.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('plan.howto.listen.title')) ?></strong> <?= e(t('plan.howto.listen.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('plan.howto.refresh.title')) ?></strong> <?= e(t('plan.howto.refresh.body')) ?></li>
    </ul>
</div>
