<?php
/** @var array $goals */
/** @var float $baselineKm */
/** @var string $defaultGoalDate */
?>
<header>
    <h1><?= e(t('plan_form.title')) ?></h1>
    <a class="muted" href="dashboard.php"><?= e(t('plan_form.back')) ?></a>
</header>

<div class="card">
    <p style="color: var(--muted); margin-top: 0;">
        <?= t('plan_form.baseline', e(number_format($baselineKm, 1))) ?>
    </p>

    <form method="post" action="plan.php">
        <input type="hidden" name="action" value="generate">

        <label class="field"><?= e(t('plan_form.goal')) ?></label>
        <select class="input" name="goal" required>
            <?php foreach ($goals as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= $key === '10k' ? 'selected' : '' ?>>
                    <?= e(t('plan_form.goal_option', t('goal.' . $key), $cfg['default_weeks'])) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="field"><?= e(t('plan_form.goal_date')) ?></label>
        <input class="input" type="date" name="goal_date" value="<?= e($defaultGoalDate) ?>" required>
        <div style="color: var(--muted); font-size: 13px; margin-top: 4px;">
            <?= e(t('plan_form.date_hint')) ?>
        </div>

        <button type="submit" class="btn" style="margin-top: 24px; border: none; cursor: pointer; font-size: 15px;">
            <?= e(t('plan_form.submit')) ?>
        </button>
    </form>
</div>
