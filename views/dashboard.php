<?php
/** @var array $athlete */
/** @var array $summary */
/** @var array $tips */
/** @var ?array $plan */
/** @var ?array $todayCtx */
/** @var ?array $todayStatus */
/** @var ?array $weekCtx */
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
    $tStatus = $todayStatus['status'] ?? 'pending';
    $tAct = $todayStatus['activity'] ?? null;
    $isRest = ($tDay['sport'] ?? 'rest') === 'rest';
    $tSwappedFrom = $todayCtx['swapped_from'] ?? null;
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
    <?php if ($tSwappedFrom): ?>
        <div style="color: var(--muted); font-size: 12px; margin-bottom: 6px;">
            <?= e(t('dashboard.today.swapped_from', t('day.' . strtolower($tSwappedFrom)))) ?>
        </div>
    <?php endif; ?>
    <div style="display:flex; align-items:center; gap: 12px; margin-bottom: 10px; flex-wrap: wrap;">
        <span class="day-type" style="background: <?= $dayColors[$tDay['type']] ?? '#3a3f4a' ?>;">
            <?= icon($sportIcons[$tDay['sport']] ?? 'run') ?><?= e($tDay['title']) ?>
        </span>
        <?php if (($tDay['distance_km'] ?? 0) > 0): ?>
            <span style="color: var(--muted);"><?= e(t('dashboard.unit.km', number_format($tDay['distance_km'], 1))) ?></span>
        <?php endif; ?>
    </div>
    <p style="margin: 0 0 12px;"><?= e($tDay['desc']) ?></p>

    <div style="display:flex; justify-content:space-between; align-items:center; gap: 12px; flex-wrap: wrap;">
        <div style="font-size: 13px; flex: 1; min-width: 200px;">
            <?php if ($tStatus === 'auto_matched' && $tAct): ?>
                <span style="color: var(--good);">
                    <?= e(t('dashboard.today.matched',
                        $tAct['name'] ?? '—',
                        number_format(($tAct['distance'] ?? 0) / 1000, 1)
                    )) ?>
                    <a class="muted" href="https://www.strava.com/activities/<?= (int)($tAct['id'] ?? 0) ?>" target="_blank" rel="noopener" style="margin-left: 8px;">↗ Strava</a>
                </span>
            <?php elseif ($tStatus === 'manual_done'): ?>
                <span style="color: var(--good);"><?= e(t('dashboard.today.manual_done')) ?></span>
            <?php elseif ($tStatus === 'manual_skipped'): ?>
                <span style="color: var(--muted);"><?= e(t('dashboard.today.manual_skipped')) ?></span>
            <?php elseif ($isRest): ?>
                <span style="color: var(--muted);"><?= e(t('dashboard.today.rest_note')) ?></span>
            <?php else: ?>
                <span style="color: var(--muted);"><?= e(t('dashboard.today.pending')) ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap: 6px;">
            <?php if ($tStatus === 'manual_done' || $tStatus === 'manual_skipped'): ?>
                <form method="post" action="plan.php" style="margin:0;">
                    <input type="hidden" name="day_action" value="clear_status">
                    <input type="hidden" name="week_index" value="<?= (int)$todayCtx['week_index'] ?>">
                    <input type="hidden" name="day" value="<?= e($todayCtx['today_dow']) ?>">
                    <input type="hidden" name="return_to" value="dashboard.php">
                    <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 12px; padding: 4px 10px; border-radius: 4px;">
                        <?= e(t('plan.action.undo')) ?>
                    </button>
                </form>
            <?php elseif (!$isRest && $tStatus !== 'auto_matched'): ?>
                <form method="post" action="plan.php" style="margin:0;">
                    <input type="hidden" name="day_action" value="mark_done">
                    <input type="hidden" name="week_index" value="<?= (int)$todayCtx['week_index'] ?>">
                    <input type="hidden" name="day" value="<?= e($todayCtx['today_dow']) ?>">
                    <input type="hidden" name="return_to" value="dashboard.php">
                    <button type="submit" class="btn" style="font-size: 12px; padding: 6px 12px; border: none; cursor: pointer;">
                        <?= e(t('plan.action.mark_done')) ?>
                    </button>
                </form>
                <form method="post" action="plan.php" style="margin:0;">
                    <input type="hidden" name="day_action" value="mark_skipped">
                    <input type="hidden" name="week_index" value="<?= (int)$todayCtx['week_index'] ?>">
                    <input type="hidden" name="day" value="<?= e($todayCtx['today_dow']) ?>">
                    <input type="hidden" name="return_to" value="dashboard.php">
                    <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 12px; padding: 4px 10px; border-radius: 4px;">
                        <?= e(t('plan.action.skip')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($weekCtx): ?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <h2 style="margin:0;"><?= e(t('dashboard.thisweek.title')) ?></h2>
        <div style="color: var(--muted); font-size: 13px;">
            <?= e(t('dashboard.today.week_progress', $weekCtx['week_index'], $weekCtx['weeks_total'])) ?>
            <?php if (!empty($weekCtx['theme'])): ?> · <?= e($weekCtx['theme']) ?><?php endif; ?>
        </div>
    </div>
    <div style="display:grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px;">
        <?php foreach ($weekCtx['days'] as $wd):
            $wSport = $wd['plan']['sport'] ?? 'rest';
            $wStatus = $wd['match']['status'];
            $statusIcon = match (true) {
                $wStatus === 'auto_matched' || $wStatus === 'manual_done' => '<span style="color: var(--good);">✓</span>',
                $wStatus === 'manual_skipped' => '<span style="color: var(--muted);">⏭</span>',
                $wd['is_past'] && $wSport !== 'rest' => '<span style="color: #ef4444;">✗</span>',
                $wd['is_future'] => '<span style="color: #444;">◯</span>',
                default => '<span style="color: var(--muted);">◯</span>',
            };
            $cellBg = $wd['is_today'] ? '#1a2236' : '#0f1115';
            $cellBorder = $wd['is_today'] ? '1px solid var(--accent)' : '1px solid #1f1f1f';
        ?>
            <div style="padding: 8px; border-radius: 6px; background: <?= $cellBg ?>; border: <?= $cellBorder ?>; min-height: 86px; display:flex; flex-direction:column; gap:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; font-size: 11px; color: var(--muted);">
                    <span><?= e(t('day.' . strtolower($wd['day']))) ?></span>
                    <span><?= $statusIcon ?></span>
                </div>
                <?php if ($wd['plan']): ?>
                    <div style="font-size: 11px; color: var(--text); line-height: 1.25; overflow:hidden; display:-webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                        <?= icon($sportIcons[$wSport] ?? 'rest', 'icon icon-sm') ?>
                        <?= e($wd['plan']['title'] ?? '') ?>
                    </div>
                    <?php if (!empty($wd['swapped_from'])): ?>
                        <div style="font-size: 10px; color: var(--accent);">↔ <?= e(t('day.' . strtolower($wd['swapped_from']))) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 10px; font-size: 12px; color: var(--muted); text-align: right;">
        <a class="muted" href="plan.php"><?= e(t('dashboard.thisweek.open_full')) ?></a>
    </div>
</div>
<?php endif; ?>
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
