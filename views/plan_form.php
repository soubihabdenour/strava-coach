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

    <form method="post" action="plan.php">
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
