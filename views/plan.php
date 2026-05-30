<?php
/** @var array $plan */
/** @var ?array $completion */
/** @var bool $aiAvailable */
/** @var ?string $feedUrl */
/** @var array $activities */
/** @var array $actionsMap */
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

<?php if (!empty($feedUrl)):
    $webcalUrl = preg_replace('#^https?://#', 'webcal://', $feedUrl);
?>
<div class="card">
    <h2 style="margin: 0 0 6px;"><?= e(t('plan.calendar.title')) ?></h2>
    <p style="color: var(--muted); margin: 0 0 14px; font-size: 14px;"><?= e(t('plan.calendar.body')) ?></p>

    <label class="field" style="font-size: 13px;"><?= e(t('plan.calendar.subscribe_label')) ?></label>
    <div style="display:flex; gap: 8px; align-items: stretch; flex-wrap: wrap;">
        <input id="feedUrlInput" class="input" type="text" readonly value="<?= e($feedUrl) ?>"
               style="flex: 1 1 280px; min-width: 0; font-family: ui-monospace, monospace; font-size: 12px;"
               onclick="this.select()">
        <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('feedUrlInput').value); this.textContent='<?= e(t('plan.calendar.copied')) ?>'; setTimeout(()=>{this.textContent='<?= e(t('plan.calendar.copy')) ?>'}, 1800);"
                style="padding: 8px 14px; font-size: 13px; border: none; cursor: pointer; white-space: nowrap;">
            <?= e(t('plan.calendar.copy')) ?>
        </button>
        <a class="btn" href="<?= e($webcalUrl) ?>"
           style="padding: 8px 14px; font-size: 13px; white-space: nowrap; text-decoration: none;">
            <?= e(t('plan.calendar.open_in_app')) ?>
        </a>
    </div>
    <div style="color: var(--muted); font-size: 12px; margin-top: 6px;">
        <?= e(t('plan.calendar.subscribe_hint')) ?>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; gap: 12px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #222; flex-wrap: wrap;">
        <a href="plan.ics.php" class="muted" style="font-size: 13px;">
            <?= e(t('plan.calendar.download_one_time')) ?>
        </a>
        <form method="post" action="plan.php" style="margin:0;" onsubmit="return confirm('<?= e(t('plan.calendar.rotate_confirm')) ?>');">
            <input type="hidden" name="action" value="rotate_calendar_token">
            <button type="submit" class="muted" style="background:none; border:none; color: var(--muted); cursor:pointer; font-size: 12px; text-decoration: underline;">
                <?= e(t('plan.calendar.rotate')) ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

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

        <?php
            // Index this week's days by code so we can apply swaps and iterate in fixed DOW order.
            $daysByCode = [];
            foreach ($week['days'] as $_d) { $daysByCode[$_d['day'] ?? ''] = $_d; }
            $weekMonday = new DateTimeImmutable($week['start']);
            $dowOrder = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            $weekIdx = (int)($week['index'] ?? 0);
        ?>
        <div style="display: grid; gap: 8px;">
            <?php foreach ($dowOrder as $offset => $dow):
                $action = $actionsMap[$weekIdx . ':' . $dow] ?? null;
                $original = $daysByCode[$dow] ?? null;
                if (!$original) continue;

                $day = $original;
                $swappedFrom = null;
                if ($action && !empty($action['swap_with']) && isset($daysByCode[$action['swap_with']])) {
                    $day = $daysByCode[$action['swap_with']];
                    $day['day'] = $dow;
                    $swappedFrom = $action['swap_with'];
                }

                $dayDate = $weekMonday->modify('+' . $offset . ' days')->format('Y-m-d');
                $matchInfo = PlanProgress::matchActivityStatus($day, $dayDate, $activities, $action);
                $matchStatus = $matchInfo['status'];
                $matchAct = $matchInfo['activity'];

                $hasSteps = !empty($day['structured_steps']);
                $hasMeta = !empty($day['purpose']) || !empty($day['rpe']) || !empty($day['fueling']);
                $statusBadge = match ($matchStatus) {
                    'auto_matched'   => ['✓', 'var(--good)', t('plan.status.auto_matched')],
                    'manual_done'    => ['✓', 'var(--good)', t('plan.status.manual_done')],
                    'manual_skipped' => ['⏭', 'var(--muted)', t('plan.status.manual_skipped')],
                    default          => null,
                };
            ?>
                <div class="plan-day">
                    <div class="day-name"><?= e(t('day.' . strtolower($dow))) ?></div>
                    <div>
                        <span class="day-type" style="background: <?= $dayColors[$day['type']] ?? '#3a3f4a' ?>;">
                            <?= icon($sportIcons[$day['sport'] ?? 'run'] ?? 'run') ?><?= e($day['title']) ?>
                        </span>
                        <?php if ($statusBadge): ?>
                            <span style="margin-left: 6px; font-size: 12px; color: <?= $statusBadge[1] ?>;" title="<?= e($statusBadge[2]) ?>">
                                <?= $statusBadge[0] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($swappedFrom): ?>
                            <span style="margin-left: 6px; font-size: 11px; color: var(--accent);">↔ <?= e(t('plan.day.swapped_from', t('day.' . strtolower($swappedFrom)))) ?></span>
                        <?php endif; ?>
                        <?php if (($day['distance_km'] ?? 0) > 0 || !empty($day['duration_min'])): ?>
                            <span style="color: var(--muted); margin-left: 8px; font-size: 13px;">
                                <?php if (($day['distance_km'] ?? 0) > 0): ?><?= e(t('dashboard.unit.km', number_format($day['distance_km'], 1))) ?><?php endif; ?>
                                <?php if (!empty($day['duration_min'])): ?> · <?= (int)$day['duration_min'] ?> min<?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="day-desc">
                        <?php if ($day['desc'] !== ''): ?>
                            <div style="margin-bottom: <?= ($hasSteps || $hasMeta || !empty($day['fueling'])) ? '8px' : '0' ?>;"><?= e($day['desc']) ?></div>
                        <?php endif; ?>

                        <?php if ($hasMeta): ?>
                            <div style="display:flex; gap: 10px; flex-wrap: wrap; font-size: 12px; color: var(--muted); margin-bottom: 8px;">
                                <?php if (!empty($day['purpose'])): ?>
                                    <span><strong style="color: var(--text);"><?= e(t('plan.day.purpose')) ?>:</strong> <?= e($day['purpose']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($day['rpe'])): ?>
                                    <span><strong style="color: var(--text);"><?= e(t('plan.day.rpe')) ?>:</strong> <?= (int)$day['rpe'] ?>/10</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasSteps): ?>
                            <ol style="margin: 0 0 8px; padding-left: 18px; color: var(--text); font-size: 13px;">
                                <?php foreach ($day['structured_steps'] as $step): ?>
                                    <li style="margin-bottom: 4px;">
                                        <strong style="color: var(--accent);"><?= e(t('plan.step.' . ($step['kind'] ?? 'main'))) ?></strong>
                                        <?php
                                            $bits = [];
                                            if (!empty($step['reps']) && $step['reps'] > 1) {
                                                $bits[] = (int)$step['reps'] . '×';
                                            }
                                            $vol = [];
                                            if (!empty($step['distance_km'])) {
                                                $vol[] = $step['distance_km'] >= 1
                                                    ? number_format($step['distance_km'], 2) . ' km'
                                                    : (int)round($step['distance_km'] * 1000) . ' m';
                                            }
                                            if (!empty($step['duration_min'])) {
                                                $vol[] = (float)$step['duration_min'] >= 1
                                                    ? rtrim(rtrim(number_format((float)$step['duration_min'], 1), '0'), '.') . ' min'
                                                    : (int)round((float)$step['duration_min'] * 60) . ' s';
                                            }
                                            if ($vol) $bits[] = implode(' / ', $vol);
                                            if (!empty($step['target'])) $bits[] = '@ ' . $step['target'];
                                        ?>
                                        <span style="color: var(--muted);"><?= e(implode(' ', $bits)) ?></span>
                                        <?php if (!empty($step['recovery'])): ?>
                                            <span style="color: var(--muted);"> · <?= e(t('plan.step.recovery_inline', $step['recovery'])) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($step['notes'])): ?>
                                            <div style="color: var(--muted); font-size: 12px; padding-left: 4px;"><?= e($step['notes']) ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if (!empty($day['fueling'])): ?>
                            <div style="display:flex; align-items:center; gap: 6px; padding: 6px 10px; background: rgba(252,76,2,0.08); border-radius: 6px; font-size: 12px; color: var(--text); margin-top: 4px;">
                                <?= icon('nutrition', 'icon icon-sm') ?>
                                <span><strong><?= e(t('plan.day.fueling')) ?>:</strong> <?= e($day['fueling']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($matchStatus === 'auto_matched' && $matchAct): ?>
                            <div style="color: var(--good); font-size: 12px; margin-top: 6px;">
                                <?= e(t('plan.status.matched_inline',
                                    $matchAct['name'] ?? '—',
                                    number_format(($matchAct['distance'] ?? 0) / 1000, 1)
                                )) ?>
                                <a class="muted" href="https://www.strava.com/activities/<?= (int)($matchAct['id'] ?? 0) ?>" target="_blank" rel="noopener" style="margin-left: 6px;">↗ Strava</a>
                            </div>
                        <?php endif; ?>

                        <?php if (($day['sport'] ?? 'rest') !== 'rest'): ?>
                            <div style="display:flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 10px; padding-top: 8px; border-top: 1px solid #1f1f1f;">
                                <?php if ($matchStatus === 'manual_done' || $matchStatus === 'manual_skipped'): ?>
                                    <form method="post" action="plan.php" style="margin:0;">
                                        <input type="hidden" name="day_action" value="clear_status">
                                        <input type="hidden" name="week_index" value="<?= $weekIdx ?>">
                                        <input type="hidden" name="day" value="<?= e($dow) ?>">
                                        <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 11px; padding: 3px 9px; border-radius: 4px;">
                                            <?= e(t('plan.action.undo')) ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="plan.php" style="margin:0;">
                                        <input type="hidden" name="day_action" value="mark_done">
                                        <input type="hidden" name="week_index" value="<?= $weekIdx ?>">
                                        <input type="hidden" name="day" value="<?= e($dow) ?>">
                                        <button type="submit" style="background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.4); color: var(--good); cursor:pointer; font-size: 11px; padding: 3px 9px; border-radius: 4px;">
                                            <?= e(t('plan.action.mark_done')) ?>
                                        </button>
                                    </form>
                                    <form method="post" action="plan.php" style="margin:0;">
                                        <input type="hidden" name="day_action" value="mark_skipped">
                                        <input type="hidden" name="week_index" value="<?= $weekIdx ?>">
                                        <input type="hidden" name="day" value="<?= e($dow) ?>">
                                        <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 11px; padding: 3px 9px; border-radius: 4px;">
                                            <?= e(t('plan.action.skip')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($swappedFrom): ?>
                                    <form method="post" action="plan.php" style="margin:0;">
                                        <input type="hidden" name="day_action" value="clear_swap">
                                        <input type="hidden" name="week_index" value="<?= $weekIdx ?>">
                                        <input type="hidden" name="day" value="<?= e($dow) ?>">
                                        <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 11px; padding: 3px 9px; border-radius: 4px;">
                                            <?= e(t('plan.action.unswap')) ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="plan.php" style="margin:0; display:flex; gap:4px; align-items:center;">
                                        <input type="hidden" name="day_action" value="swap">
                                        <input type="hidden" name="week_index" value="<?= $weekIdx ?>">
                                        <input type="hidden" name="day" value="<?= e($dow) ?>">
                                        <select name="swap_with" class="input" style="font-size: 11px; padding: 3px 6px; height: auto; min-width: 0; width: auto;">
                                            <?php foreach ($dowOrder as $otherDow):
                                                if ($otherDow === $dow) continue;
                                                if (!isset($daysByCode[$otherDow])) continue;
                                            ?>
                                                <option value="<?= e($otherDow) ?>"><?= e(t('day.' . strtolower($otherDow))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="muted" style="background:none; border:1px solid #333; color: var(--muted); cursor:pointer; font-size: 11px; padding: 3px 9px; border-radius: 4px;">
                                            <?= e(t('plan.action.swap')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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
