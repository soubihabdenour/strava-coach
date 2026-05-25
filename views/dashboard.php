<?php
/** @var array $athlete */
/** @var array $summary */
/** @var array $tips */
$weeks = $summary['weeks'];
$maxKm = max(0.1, max(array_column($weeks, 'distance_km')));
$delta = $summary['volume_change_pct'];
$current = $summary['current_block'];
?>
<header>
    <h1><?= t('dashboard.greeting', e($athlete['firstname'] ?? 'athlete')) ?></h1>
    <div>
        <a class="btn" href="plan.php" style="padding: 8px 14px; font-size: 14px;"><?= e(t('dashboard.training_plan')) ?></a>
        <a class="muted" href="logout.php" style="margin-left: 16px;"><?= e(t('dashboard.disconnect')) ?></a>
    </div>
</header>

<div class="grid">
    <div class="stat">
        <div class="label"><?= e(t('dashboard.distance_4w')) ?></div>
        <div class="value"><?= e(t('dashboard.unit.km', number_format($current['distance_km'], 1))) ?></div>
        <?php if ($delta !== null): ?>
            <div class="delta" style="color: <?= $delta >= 0 ? 'var(--good)' : 'var(--warn)' ?>">
                <?= e(t('dashboard.delta_vs_prior', (int)round($delta))) ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('dashboard.time_4w')) ?></div>
        <div class="value"><?= e(t('dashboard.unit.h', number_format($current['moving_hours'], 1))) ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('dashboard.elevation_4w')) ?></div>
        <div class="value"><?= e(t('dashboard.unit.m', number_format($current['elevation_m']))) ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(t('dashboard.rest_14d')) ?></div>
        <div class="value"><?= $summary['rest_days_last_14'] ?></div>
    </div>
</div>

<div class="card">
    <h2><?= e(t('dashboard.weekly_chart')) ?></h2>
    <div class="bars">
        <?php foreach ($weeks as $w): $h = $maxKm > 0 ? ($w['distance_km'] / $maxKm) * 100 : 0; ?>
            <div class="bar" style="height: <?= max(2, $h) ?>%" title="<?= e($w['start']) ?>: <?= number_format($w['distance_km'], 1) ?> km"></div>
        <?php endforeach; ?>
    </div>
    <div class="bar-labels">
        <?php foreach ($weeks as $w): ?>
            <span><?= (new DateTime($w['start']))->format('M j') ?></span>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h2><?= e(t('dashboard.feedback')) ?></h2>
    <?php foreach ($tips as $tip): ?>
        <div class="tip <?= e($tip['level']) ?>">
            <div class="title"><?= e($tip['title']) ?></div>
            <div class="body"><?= e($tip['body']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($summary['sport_breakdown'])): ?>
<div class="card">
    <h2><?= e(t('dashboard.activity_mix')) ?></h2>
    <?php $total = array_sum($summary['sport_breakdown']); ?>
    <?php foreach ($summary['sport_breakdown'] as $sport => $count): ?>
        <div style="display:flex; justify-content:space-between; padding: 6px 0; border-bottom: 1px solid #222;">
            <span><?= e($sport) ?></span>
            <span style="color: var(--muted);"><?= $count ?> · <?= round(100 * $count / $total) ?>%</span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
