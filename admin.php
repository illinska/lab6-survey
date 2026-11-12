<?php
// admin.php — адмін-панель: перегляд відповідей, експорт і видалення.
// Доступ лише після входу (логін і пароль), факт входу зберігається в $_SESSION.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_start();

$loginError = '';

// ----- Обробка POST: вхід, вихід, видалення -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string($_POST, 'action');

    if ($action === 'login') {
        if (check_login(post_string($_POST, 'login'), (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);   // новий ідентифікатор сесії після входу
            $_SESSION['admin'] = true;     // фіксуємо вхід адміністратора
            $_SESSION['admin_login'] = ADMIN_LOGIN;
            header('Location: admin.php');
            exit;
        }
        $loginError = 'Невірний логін або пароль.';
    } elseif (is_admin() && check_csrf(post_string($_POST, 'csrf'))) {
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: admin.php');
            exit;
        }
        if ($action === 'delete') {
            $ok = delete_response(post_string($_POST, 'file'));
            $_SESSION['flash'] = $ok ? 'Відповідь видалено.' : 'Не вдалося видалити відповідь.';
            header('Location: admin.php');
            exit;
        }
    }
}

// ----- Експорт (лише для адміністратора) -----
if (is_admin() && isset($_GET['export'])) {
    $format = $_GET['export'];
    if ($format === 'json') {
        export_json(load_responses());
        exit;
    }
    if ($format === 'csv') {
        export_csv(load_responses());
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$pageTitle = 'Адмін-панель';
$wide = true;
include __DIR__ . '/header.php';
?>
    <h1>🔐 Адмін-панель</h1>

<?php if (!is_admin()): ?>

    <?php if ($loginError !== ''): ?>
        <div class="errors"><?= h($loginError) ?></div>
    <?php endif; ?>

    <form method="post" action="admin.php" style="max-width: 360px;">
        <input type="hidden" name="action" value="login">
        <label>Логін:
            <input type="text" name="login" required autofocus>
        </label>
        <label>Пароль:
            <input type="password" name="password" required>
        </label>
        <button type="submit">Увійти</button>
    </form>

<?php else: ?>
    <?php $responses = load_responses(); ?>

    <?php if ($flash !== ''): ?>
        <div class="ok"><?= h($flash) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <strong>Відповідей: <?= count($responses) ?></strong>
        <a class="btn" href="admin.php?export=json">Експорт JSON</a>
        <a class="btn" href="admin.php?export=csv">Експорт CSV</a>
        <form method="post" action="admin.php">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <button type="submit" class="link">Вийти</button>
        </form>
    </div>

    <?php if (!$responses): ?>
        <p>Поки що відповідей немає.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Час</th>
                <th>Респондент</th>
                <th>Відповіді</th>
                <th></th>
            </tr>
            <?php foreach ($responses as $r): ?>
                <?php $d = $r['data']; ?>
                <tr>
                    <td><?= h((string) ($d['submitted_at'] ?? '—')) ?></td>
                    <td>
                        <?= h((string) ($d['name'] ?? '—')) ?><br>
                        <?= h((string) ($d['email'] ?? '')) ?>
                    </td>
                    <td>
                        <?php foreach (($d['answers'] ?? []) as $question => $answer): ?>
                            <?php $text = answer_to_text($answer); ?>
                            <div><span class="q"><?= h((string) $question) ?>:</span>
                                <?= $text === '' ? '—' : h($text) ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <form method="post" action="admin.php" onsubmit="return confirm('Видалити цю відповідь?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="file" value="<?= h($r['file']) ?>">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <button type="submit" class="danger">Видалити</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
