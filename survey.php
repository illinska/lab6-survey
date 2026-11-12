<?php
// survey.php — сторінка анкети.
// Логіки тут мало: вона у functions.php, а налаштування у config.php.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$result = [
    'values'    => empty_form_values(),
    'errors'    => [],
    'submitted' => false,
    'saved_at'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = process_survey($_POST);
}
$v = $result['values'];

$pageTitle = 'Яку суперсилу ти б обрав?';
include __DIR__ . '/header.php';
?>
    <h1>🦸 Яку суперсилу ти б обрав?</h1>

<?php if ($result['submitted']): ?>
    <div class="ok">
        <p><strong>Дякуємо, <?= h($v['name']) ?>!</strong> Відповідь збережено.</p>
        <p>Форму заповнено: <strong><?= h($result['saved_at']) ?></strong></p>
        <p><a href="survey.php">Заповнити ще раз</a></p>
    </div>
<?php else: ?>
    <?php render_errors($result['errors']); ?>

    <form method="post" action="survey.php">
        <label>Ім'я:
            <input type="text" name="name" value="<?= h($v['name']) ?>" required>
        </label>
        <label>Email:
            <input type="email" name="email" value="<?= h($v['email']) ?>" required>
        </label>

        <fieldset>
            <legend>1. <?= h(QUESTIONS['power']) ?></legend>
            <?php render_radio_group('power', POWERS, $v['power']); ?>
        </fieldset>

        <fieldset>
            <legend>2. <?= h(QUESTIONS['place']) ?></legend>
            <?php render_select('place', PLACES, $v['place']); ?>
        </fieldset>

        <fieldset>
            <legend>3. <?= h(QUESTIONS['effects']) ?> (можна кілька)</legend>
            <?php render_checkbox_group('effects', SIDE_EFFECTS, $v['effects']); ?>
        </fieldset>

        <fieldset>
            <legend>4. <?= h(QUESTIONS['exam_text']) ?></legend>
            <textarea name="exam_text" rows="4" maxlength="1000"><?= h($v['exam_text']) ?></textarea>
        </fieldset>

        <button type="submit">Надіслати</button>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
