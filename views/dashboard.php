<?php
/** @var array $athlete */
/** @var array $summary */
/** @var array $tips */
/** @var ?array $plan */
/** @var ?array $todayCtx */
/** @var ?array $todayMatch */
$weeks = $summary['weeks'];
$maxKm = max(0.1, max(array_column($weeks, 'distance_km')));
$delta = $summary['volume_change_pct'];
$current = $summary['current_block'];

$phaseColors = ['base' => '#60a5fa', 'build' => '#a78bfa', 'peak' => '#fc4c02', 'taper' => '#22c55e'];
$dayColors = ['rest' => '#3a3f4a', 'easy' => '#4b5563', 'long' => '#1e40af', 'tempo' => '#b45309', 'quality' => '#7c2d12', 'race' => '#fc4c02', 'brick' => '#7c3aed'];
$sportIcons = ['run' => 'run', 'bike' => 'bike', 'swim' => 'swim', 'multi' => 'tri', 'strength' => 'strength', 'rest' => 'rest'];
?>
<header>
    <h1><?= t('dashboard.greeting', e($athlete['firstname'] ?? 'athlete')) ?></h1>
    <div>
        <a class="btn" href="coach.php" style="padding: 8px 14px; font-size: 14px;"><?= e(t('coach.page_title')) ?></a>
        <a class="btn" href="plan.php" style="padding: 8px 14px; font-size: 14px; margin-left: 8px;"><?= e(t('dashboard.training_plan')) ?></a>
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

<?php if ($plan && $todayCtx && $todayCtx['state'] === 'active'):
    $tDay = $todayCtx['day'];
    $tWeek = $todayCtx['week'];
    $tDateLabel = (new DateTimeImmutable($todayCtx['today']))->format('D, M j');
    $isRest = ($tDay['sport'] ?? 'rest') === 'rest';
    $isUnattributed = in_array($tDay['sport'] ?? '', ['strength', 'multi'], true);
?>
<div class="card" style="border-left: 4px solid var(--accent);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 10px;">
        <h2 style="margin: 0;">
            <?= e(t('dashboard.today.title', $tDateLabel)) ?>
        </h2>
        <div style="display:flex; align-items:center; gap: 10px; font-size: 13px; color: var(--muted); flex-wrap: wrap;">
            <span><?= e(t('dashboard.today.week_progress', $todayCtx['week_index'], $todayCtx['weeks_total'])) ?></span>
            <span class="phase-pill" style="background: <?= $phaseColors[$tWeek['phase']] ?? '#3a3f4a' ?>;">
                <?= e(t('phase.' . $tWeek['phase'])) ?>
            </span>
            <span>
                <?= $todayCtx['days_to_goal'] === 0
                    ? e(t('dashboard.today.goal_day'))
                    : e(t('dashboard.today.days_to_goal', $todayCtx['days_to_goal'])) ?>
            </span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap: 12px; margin-bottom: 10px; flex-wrap: wrap;">
        <span class="day-type" style="background: <?= $dayColors[$tDay['type']] ?? '#3a3f4a' ?>;">
            <?= icon($sportIcons[$tDay['sport']] ?? 'run') ?><?= e($tDay['title']) ?>
        </span>
        <?php if (($tDay['distance_km'] ?? 0) > 0): ?>
            <span style="color: var(--muted);"><?= e(t('dashboard.unit.km', number_format($tDay['distance_km'], 1))) ?></span>
        <?php endif; ?>
    </div>
    <p style="margin: 0 0 12px;"><?= e($tDay['desc']) ?></p>
    <?php if ($todayMatch): ?>
        <div style="color: var(--good); font-size: 13px;">
            <?= e(t('dashboard.today.matched',
                $todayMatch['name'] ?? '—',
                number_format(($todayMatch['distance'] ?? 0) / 1000, 1)
            )) ?>
            <a class="muted" href="https://www.strava.com/activities/<?= (int)($todayMatch['id'] ?? 0) ?>" target="_blank" rel="noopener" style="margin-left: 8px;">↗ Strava</a>
        </div>
    <?php elseif ($isRest): ?>
        <div style="color: var(--muted); font-size: 13px;"><?= e(t('dashboard.today.rest_note')) ?></div>
    <?php elseif ($isUnattributed): ?>
        <div style="color: var(--muted); font-size: 13px;"><?= e(t('dashboard.today.manual_note')) ?></div>
    <?php else: ?>
        <div style="color: var(--muted); font-size: 13px;"><?= e(t('dashboard.today.pending')) ?></div>
    <?php endif; ?>
</div>
<?php elseif ($plan && $todayCtx && $todayCtx['state'] === 'pre_start'): ?>
<div class="card" style="border-left: 4px solid var(--info);">
    <h2 style="margin: 0 0 6px;"><?= e(t('dashboard.today.upcoming_title')) ?></h2>
    <p style="color: var(--muted); margin: 0;"><?= e(t('dashboard.today.upcoming_body', $todayCtx['starts'])) ?></p>
</div>
<?php elseif ($plan && $todayCtx && $todayCtx['state'] === 'ended'): ?>
<div class="card" style="border-left: 4px solid var(--warn);">
    <h2 style="margin: 0 0 6px;"><?= e(t('dashboard.today.ended_title')) ?></h2>
    <p style="color: var(--muted); margin: 0 0 12px;"><?= e(t('dashboard.today.ended_body', $todayCtx['ended'])) ?></p>
    <a class="btn" href="plan.php?reset=1" style="padding: 8px 14px; font-size: 14px;"><?= e(t('dashboard.today.no_plan_cta')) ?></a>
</div>
<?php else: ?>
<div class="card">
    <h2 style="margin: 0 0 6px;"><?= e(t('dashboard.today.no_plan_title')) ?></h2>
    <p style="color: var(--muted); margin: 0 0 12px;"><?= e(t('dashboard.today.no_plan_body')) ?></p>
    <a class="btn" href="plan.php" style="padding: 8px 14px; font-size: 14px;"><?= e(t('dashboard.today.no_plan_cta')) ?></a>
</div>
<?php endif; ?>

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
