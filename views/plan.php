<?php
/** @var array $plan */
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
];
?>
<header>
    <h1><?= e(t('plan.title', t('goal.' . $plan['goal']))) ?></h1>
    <div>
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
        <div class="label"><?= e(t('plan.baseline_peak')) ?></div>
        <div class="value" style="font-size: 18px;"><?= e(t('plan.baseline_peak_value', $plan['baseline_km'], $plan['peak_km'])) ?></div>
    </div>
</div>

<?php foreach ($plan['weeks'] as $week): ?>
    <div class="card">
        <div class="week-head">
            <h2 style="margin: 0;">
                <?= e(t('plan.week_n', $week['index'])) ?>
                <span style="color: var(--muted); font-weight: 400; font-size: 14px; margin-left: 8px;">
                    <?= e(t('plan.starts_on', $week['start'])) ?>
                </span>
            </h2>
            <div>
                <span class="phase-pill" style="background: <?= $phaseColors[$week['phase']] ?>;">
                    <?= e(t('phase.' . $week['phase'])) ?>
                </span>
                <span style="color: var(--muted); margin-left: 12px;"><?= e(t('plan.km_target', $week['target_km'])) ?></span>
            </div>
        </div>
        <p style="color: var(--muted); margin: 0 0 16px;"><?= e($week['theme']) ?></p>

        <div style="display: grid; gap: 8px;">
            <?php foreach ($week['days'] as $day): ?>
                <div class="plan-day">
                    <div class="day-name"><?= e(t('day.' . strtolower($day['day']))) ?></div>
                    <div>
                        <span class="day-type" style="background: <?= $dayColors[$day['type']] ?? '#3a3f4a' ?>;">
                            <?= e($day['title']) ?>
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
