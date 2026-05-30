<?php
/** @var array $goals */
/** @var float $baselineKm */
/** @var string $defaultGoalDate */
/** @var bool $aiAvailable */
/** @var array $paces */
?>
<header>
    <h1><?= e(t('plan_form.title')) ?></h1>
    <a class="muted" href="dashboard.php"><?= e(t('plan_form.back')) ?></a>
</header>

<div class="card">
    <p style="color: var(--muted); margin-top: 0;">
        <?= t('plan_form.baseline', e(number_format($baselineKm, 1))) ?>
    </p>

    <?php if ($paces['run']['has_data'] ?? false): ?>
        <div style="background:#0f1115; padding: 10px 14px; border-radius:8px; margin: 12px 0; font-size: 13px; color: var(--muted);">
            <strong style="color: var(--text);"><?= e(t('plan_form.paces_detected')) ?>:</strong>
            <?php foreach ($paces['run']['zones'] as $name => $range): ?>
                <span style="margin-right: 12px;"><?= e($name) ?>: <?= e($range) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="plan.php" data-loading="<?= e(t('loading.plan_generating')) ?>">
        <input type="hidden" name="action" value="generate">

        <label class="field"><?= e(t('plan_form.goal')) ?></label>
        <select class="input" name="goal" required>
            <?php foreach ($goals as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= $key === '10k' ? 'selected' : '' ?> data-ai-only="<?= $cfg['ai_only'] ? '1' : '0' ?>">
                    <?= e(t('plan_form.goal_option', t('goal.' . $key), $cfg['default_weeks'])) ?>
                    <?= $cfg['ai_only'] ? ' — ' . e(t('plan_form.ai_only_marker')) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="field"><?= e(t('plan_form.goal_date')) ?></label>
        <input class="input" type="date" name="goal_date" value="<?= e($defaultGoalDate) ?>" required>
        <div style="color: var(--muted); font-size: 13px; margin-top: 4px;">
            <?= e(t('plan_form.date_hint')) ?>
        </div>

        <label class="field"><?= e(t('plan_form.weekly_hours')) ?></label>
        <select class="input" name="weekly_hours">
            <?php foreach ([4, 6, 8, 10, 12, 15, 20] as $h): ?>
                <option value="<?= $h ?>" <?= $h === 8 ? 'selected' : '' ?>><?= e(t('plan_form.hours_n', $h)) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="field"><?= e(t('plan_form.long_day')) ?></label>
        <select class="input" name="long_run_day">
            <?php foreach (['Sun', 'Sat', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $d): ?>
                <option value="<?= $d ?>" <?= $d === 'Sun' ? 'selected' : '' ?>><?= e(t('day.' . strtolower($d))) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="field"><?= e(t('plan_form.injuries')) ?></label>
        <textarea class="input" name="injuries" rows="2" maxlength="500" placeholder="<?= e(t('plan_form.injuries_placeholder')) ?>"></textarea>

        <details style="margin: 24px 0 0;">
            <summary style="cursor: pointer; color: var(--accent); font-size: 14px; padding: 6px 0;">
                <?= e(t('plan_form.adv.title')) ?>
            </summary>
            <div style="margin-top: 12px; padding: 16px; background: #0f1115; border-radius: 8px;">
                <div style="color: var(--muted); font-size: 13px; margin-bottom: 16px;">
                    <?= e(t('plan_form.adv.hint')) ?>
                </div>

                <label class="field"><?= e(t('plan_form.cant_train_days')) ?></label>
                <div style="display:flex; gap: 6px; flex-wrap: wrap;">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                        <label style="display:flex; align-items:center; gap:6px; padding: 6px 10px; background:#1a1d24; border-radius:6px; cursor:pointer; font-size:13px;">
                            <input type="checkbox" name="cant_train_days[]" value="<?= $d ?>" style="accent-color: var(--accent);">
                            <span><?= e(t('day.' . strtolower($d))) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="color: var(--muted); font-size: 12px; margin-top: 6px;"><?= e(t('plan_form.cant_train_hint')) ?></div>

                <label class="field"><?= e(t('plan_form.sessions_override')) ?></label>
                <select class="input" name="sessions_override">
                    <option value=""><?= e(t('plan_form.sessions_default')) ?></option>
                    <?php foreach ([3,4,5,6,7,8,9] as $n): ?>
                        <option value="<?= $n ?>"><?= $n ?> / week</option>
                    <?php endforeach; ?>
                </select>

                <label class="field"><?= e(t('plan_form.target_time')) ?></label>
                <input class="input" type="text" name="target_time" maxlength="60" placeholder="<?= e(t('plan_form.target_time_placeholder')) ?>">

                <label class="field"><?= e(t('plan_form.intensity')) ?></label>
                <select class="input" name="intensity_preference">
                    <option value="polarized" selected><?= e(t('plan_form.intensity.polarized')) ?></option>
                    <option value="pyramidal"><?= e(t('plan_form.intensity.pyramidal')) ?></option>
                    <option value="threshold"><?= e(t('plan_form.intensity.threshold')) ?></option>
                </select>

                <label class="field"><?= e(t('plan_form.surface')) ?></label>
                <select class="input" name="surface">
                    <option value="mixed" selected><?= e(t('plan_form.surface.mixed')) ?></option>
                    <option value="road"><?= e(t('plan_form.surface.road')) ?></option>
                    <option value="trail"><?= e(t('plan_form.surface.trail')) ?></option>
                    <option value="track"><?= e(t('plan_form.surface.track')) ?></option>
                    <option value="treadmill"><?= e(t('plan_form.surface.treadmill')) ?></option>
                </select>

                <label class="field"><?= e(t('plan_form.pool_length')) ?></label>
                <select class="input" name="pool_length">
                    <option value="25m" selected><?= e(t('plan_form.pool_25m')) ?></option>
                    <option value="50m"><?= e(t('plan_form.pool_50m')) ?></option>
                    <option value="25y"><?= e(t('plan_form.pool_25y')) ?></option>
                    <option value="ow"><?= e(t('plan_form.pool_ow')) ?></option>
                </select>

                <label class="field"><?= e(t('plan_form.bike_location')) ?></label>
                <select class="input" name="bike_location">
                    <option value="mixed" selected><?= e(t('plan_form.bike.mixed')) ?></option>
                    <option value="outdoor"><?= e(t('plan_form.bike.outdoor')) ?></option>
                    <option value="indoor"><?= e(t('plan_form.bike.indoor')) ?></option>
                </select>
            </div>
        </details>

        <?php if ($aiAvailable): ?>
            <label style="display:flex; align-items:center; gap: 10px; margin: 20px 0 6px; cursor:pointer;">
                <input type="checkbox" name="use_ai" value="1" checked style="width:18px; height:18px; accent-color: var(--accent);">
                <span><?= e(t('plan_form.use_ai')) ?></span>
            </label>
            <div style="color: var(--muted); font-size: 12px; margin-bottom: 8px;">
                <?= e(t('plan_form.use_ai_hint')) ?>
            </div>
        <?php else: ?>
            <div style="color: var(--warn); font-size: 13px; margin: 16px 0; padding: 10px; background: rgba(245,158,11,0.08); border-radius: 8px;">
                <?= e(t('plan_form.no_ai_warning')) ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn" style="margin-top: 24px; border: none; cursor: pointer; font-size: 15px;">
            <?= e(t('plan_form.submit')) ?>
        </button>
    </form>
</div>
