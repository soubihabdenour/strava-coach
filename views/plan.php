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
    <h1><?= e($plan['goal_label']) ?> plan</h1>
    <div>
        <a class="muted" href="plan.php?reset=1" style="margin-right: 16px;">New plan</a>
        <a class="muted" href="dashboard.php">← Dashboard</a>
    </div>
</header>

<div class="grid">
    <div class="stat">
        <div class="label">Weeks</div>
        <div class="value"><?= $plan['weeks_total'] ?></div>
    </div>
    <div class="stat">
        <div class="label">Start</div>
        <div class="value" style="font-size: 18px;"><?= e($plan['start_date']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Goal date</div>
        <div class="value" style="font-size: 18px;"><?= e($plan['goal_date']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Baseline → peak</div>
        <div class="value" style="font-size: 18px;"><?= $plan['baseline_km'] ?> → <?= $plan['peak_km'] ?> km</div>
    </div>
</div>

<?php foreach ($plan['weeks'] as $week): ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap: 8px; margin-bottom: 12px;">
            <h2 style="margin: 0;">
                Week <?= $week['index'] ?>
                <span style="color: var(--muted); font-weight: 400; font-size: 14px; margin-left: 8px;">
                    starts <?= e($week['start']) ?>
                </span>
            </h2>
            <div>
                <span style="background: <?= $phaseColors[$week['phase']] ?>; color: #000; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                    <?= e($week['phase']) ?>
                </span>
                <span style="color: var(--muted); margin-left: 12px;"><?= $week['target_km'] ?> km target</span>
            </div>
        </div>
        <p style="color: var(--muted); margin: 0 0 16px;"><?= e($week['theme']) ?></p>

        <div style="display: grid; gap: 8px;">
            <?php foreach ($week['days'] as $day): ?>
                <div style="display: grid; grid-template-columns: 50px 140px 1fr; gap: 12px; padding: 10px 12px; background: #0f1115; border-radius: 8px; align-items: start;">
                    <div style="color: var(--muted); font-weight: 600; font-size: 13px;"><?= e($day['day']) ?></div>
                    <div>
                        <div style="display: inline-block; background: <?= $dayColors[$day['type']] ?? '#3a3f4a' ?>; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                            <?= e($day['title']) ?>
                        </div>
                    </div>
                    <div style="color: var(--muted); font-size: 14px;"><?= e($day['desc']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2>How to use this plan</h2>
    <ul style="color: var(--muted); line-height: 1.8;">
        <li><strong style="color: var(--text);">Easy means easy.</strong> Conversational pace — if you can't speak in full sentences, slow down.</li>
        <li><strong style="color: var(--text);">Move workouts</strong> within a week as life requires, but keep quality + long run at least 48h apart.</li>
        <li><strong style="color: var(--text);">Listen to your body.</strong> Skip a session if sleep, RPE, or HR signal you're cooked. Adaptation > completion.</li>
        <li><strong style="color: var(--text);">Refresh from Strava</strong> after a few weeks so the plan can re-scale to your new baseline.</li>
    </ul>
</div>
