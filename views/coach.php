<?php
/** @var string $sport */
/** @var array $history */
/** @var ?string $error */

$sportIcons = [
    'run'       => 'run',
    'swim'      => 'swim',
    'cycle'     => 'bike',
    'tri'       => 'tri',
    'nutrition' => 'nutrition',
];
?>
<header>
    <h1><?= e(t('coach.page_title')) ?></h1>
    <a class="muted" href="dashboard.php"><?= e(t('coach.back')) ?></a>
</header>

<div class="coach-tabs">
    <?php foreach (CoachAgent::SPORTS as $s): ?>
        <a href="coach.php?sport=<?= e($s) ?>" class="coach-tab <?= $s === $sport ? 'active' : '' ?>">
            <span class="coach-tab-icon"><?= icon($sportIcons[$s]) ?></span>
            <?= e(t('coach.sport.' . $s)) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="coach-chat">
        <?php if (empty($history)): ?>
            <div class="coach-empty"><?= e(t('coach.intro.' . $sport)) ?></div>
        <?php endif; ?>
        <?php foreach ($history as $msg): ?>
            <div class="msg <?= e($msg['role']) ?>"><?php
                $html = e($msg['text']);
                $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
                echo nl2br($html);
            ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <div class="msg error"><?= e($error) ?></div>
        <?php endif; ?>
    </div>

    <form method="post" class="coach-form" data-loading="<?= e(t('loading.coach_thinking')) ?>">
        <textarea name="message" rows="3" maxlength="4000" placeholder="<?= e(t('coach.placeholder')) ?>" required></textarea>
        <div class="coach-form-actions">
            <button type="submit" class="btn"><?= e(t('coach.send')) ?></button>
            <?php if (!empty($history)): ?>
                <a class="muted" href="coach.php?sport=<?= e($sport) ?>&amp;clear=1"><?= e(t('coach.clear')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>
