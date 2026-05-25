<?php
/** @var array $goals */
/** @var float $baselineKm */
/** @var string $defaultGoalDate */
?>
<header>
    <h1>Build a training plan</h1>
    <a class="muted" href="dashboard.php">← Back to dashboard</a>
</header>

<div class="card">
    <p style="color: var(--muted); margin-top: 0;">
        Your current baseline from Strava: <strong style="color: var(--text);"><?= number_format($baselineKm, 1) ?> km/week</strong> avg over the last 4 weeks.
        The plan starts from there and ramps with a 10%-per-week rule, deloading every 4th week.
    </p>

    <form method="post" action="plan.php">
        <input type="hidden" name="action" value="generate">

        <label style="display:block; margin: 16px 0 6px; font-weight: 600;">Goal</label>
        <select name="goal" required style="width:100%; padding:10px; border-radius:6px; background:#0f1115; color:var(--text); border:1px solid #2a2f3a; font-size:15px;">
            <?php foreach ($goals as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= $key === '10k' ? 'selected' : '' ?>>
                    <?= e($cfg['label']) ?> (<?= $cfg['default_weeks'] ?> weeks default)
                </option>
            <?php endforeach; ?>
        </select>

        <label style="display:block; margin: 16px 0 6px; font-weight: 600;">Goal date</label>
        <input type="date" name="goal_date" value="<?= e($defaultGoalDate) ?>" required
               style="width:100%; padding:10px; border-radius:6px; background:#0f1115; color:var(--text); border:1px solid #2a2f3a; font-size:15px;">
        <div style="color: var(--muted); font-size: 13px; margin-top: 4px;">
            Plan length is clamped to the goal's min/max — if your date doesn't fit, we'll use the closest valid duration.
        </div>

        <button type="submit" class="btn" style="margin-top: 24px; border: none; cursor: pointer; font-size: 15px;">
            Generate plan
        </button>
    </form>
</div>
