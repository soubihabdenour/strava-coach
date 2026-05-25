<div class="hero">
    <h1><?= t('landing.hero.title') ?></h1>
    <p><?= e(t('landing.hero.body')) ?></p>
    <a class="btn" href="login.php"><?= e(t('landing.cta')) ?></a>
</div>

<div class="card">
    <h2><?= e(t('landing.features.title')) ?></h2>
    <ul style="color: var(--muted); line-height: 1.8;">
        <li><strong style="color: var(--text);"><?= e(t('landing.features.tracking.title')) ?></strong> <?= e(t('landing.features.tracking.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('landing.features.alerts.title')) ?></strong> <?= e(t('landing.features.alerts.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('landing.features.split.title')) ?></strong> <?= e(t('landing.features.split.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('landing.features.rest.title')) ?></strong> <?= e(t('landing.features.rest.body')) ?></li>
        <li><strong style="color: var(--text);"><?= e(t('landing.features.workout.title')) ?></strong> <?= e(t('landing.features.workout.body')) ?></li>
    </ul>
</div>
