<?php
// functions.php — уся логіка: анкета, адмін-панель, допоміжні функції.
// Сторінки (survey.php, admin.php) лише викликають ці функції.

// ---------- Допоміжні функції ----------

// Захист від XSS: екранує спецсимволи перед виводом у HTML
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Довжина рядка: працює і без розширення mbstring
function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

// Бере з масиву $_POST значення, лише якщо воно є звичайним рядком
function post_string(array $post, string $key): string
{
    return isset($post[$key]) && is_string($post[$key]) ? trim($post[$key]) : '';
}

// Перетворює відповідь (рядок або список) у текст
function answer_to_text($answer): string
{
    if (is_array($answer)) {
        return implode(', ', array_map('strval', $answer));
    }
    return (string) $answer;
}

// ---------- Анкета ----------

function empty_form_values(): array
{
    return ['name' => '', 'email' => '', 'power' => '', 'place' => '', 'effects' => [], 'exam_text' => ''];
}

// Збирає значення форми з $_POST
function collect_survey_values(array $post): array
{
    $effects = isset($post['effects']) && is_array($post['effects']) ? $post['effects'] : [];
    $effects = array_values(array_filter($effects, 'is_string'));
    $effects = array_values(array_intersect($effects, array_keys(SIDE_EFFECTS)));

    return [
        'name'      => post_string($post, 'name'),
        'email'     => post_string($post, 'email'),
        'power'     => post_string($post, 'power'),
        'place'     => post_string($post, 'place'),
        'effects'   => $effects,
        'exam_text' => post_string($post, 'exam_text'),
    ];
}

// Повертає список помилок (порожній, якщо все гаразд)
function validate_survey(array $v): array
{
    $errors = [];
    if ($v['name'] === '' || str_len($v['name']) > 100) {
        $errors[] = "Вкажіть ім'я (до 100 символів).";
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Вкажіть коректний email.';
    }
    if (!isset(POWERS[$v['power']])) {
        $errors[] = 'Оберіть суперсилу.';
    }
    if (!isset(PLACES[$v['place']])) {
        $errors[] = 'Оберіть, де використаєте суперсилу.';
    }
    if ($v['exam_text'] === '' || str_len($v['exam_text']) > 1000) {
        $errors[] = 'Напишіть відповідь на питання про залік (до 1000 символів).';
    }
    return $errors;
}

// Зберігає відповідь у файл survey/РРРР-ММ-ДД_ГГ-ХХ-СС_мкс.json і в базу даних
// Повертає ['file' => ..., 'saved_at' => ..., 'in_db' => ...] або null, якщо файл не збережено
function save_response(array $v): ?array
{
    if (!is_dir(SURVEY_DIR) && !mkdir(SURVEY_DIR, 0755, true)) {
        return null;
    }

    $now = new DateTime();
    $savedAt = $now->format('d.m.Y H:i:s');

    $data = [
        'submitted_at' => $savedAt,
        'name'         => $v['name'],
        'email'        => $v['email'],
        'answers'      => [
            QUESTIONS['power']     => POWERS[$v['power']],
            QUESTIONS['place']     => PLACES[$v['place']],
            QUESTIONS['effects']   => array_map(fn($k) => SIDE_EFFECTS[$k], $v['effects']),
            QUESTIONS['exam_text'] => $v['exam_text'],
        ],
    ];

    // У назві файлу є дата, час і мікросекунди (щоб файли не перезаписувались)
    $fileName = $now->format('Y-m-d_H-i-s') . '_' . $now->format('u') . '.json';
    $ok = file_put_contents(
        SURVEY_DIR . '/' . $fileName,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    if ($ok === false) {
        return null;
    }

    // Ту саму відповідь записуємо в базу даних. Якщо база недоступна, файл уже
    // збережено, а в базу відповідь потрапить пізніше (див. sync_files_to_db).
    try {
        $inDb = insert_response_row($fileName, $data);
    } catch (Throwable $e) {
        $inDb = false;
    }

    return ['file' => $fileName, 'saved_at' => $savedAt, 'in_db' => $inDb];
}

// Обробляє надіслану форму: збір, перевірка, збереження
function process_survey(array $post): array
{
    $values = collect_survey_values($post);
    $result = [
        'values'    => $values,
        'errors'    => validate_survey($values),
        'submitted' => false,
        'saved_at'  => '',
        'in_db'     => false,
    ];

    if (!$result['errors']) {
        $saved = save_response($values);
        if ($saved === null) {
            $result['errors'][] = 'Не вдалося зберегти відповідь.';
        } else {
            $result['submitted'] = true;
            $result['saved_at'] = $saved['saved_at'];
            $result['in_db'] = $saved['in_db'];
        }
    }
    return $result;
}

// ---------- Виведення частин форми (HTML генерується функціями) ----------

function render_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="errors"><ul>';
    foreach ($errors as $e) {
        echo '<li>' . h($e) . '</li>';
    }
    echo '</ul></div>';
}

function render_radio_group(string $name, array $options, string $selected): void
{
    foreach ($options as $key => $label) {
        $checked = ((string) $key === $selected) ? ' checked' : '';
        echo '<label><input type="radio" name="' . h($name) . '" value="' . h((string) $key) . '"' . $checked . '> '
            . h($label) . '</label>';
    }
}

function render_select(string $name, array $options, string $selected): void
{
    echo '<select name="' . h($name) . '"><option value="">— оберіть —</option>';
    foreach ($options as $key => $label) {
        $sel = ((string) $key === $selected) ? ' selected' : '';
        echo '<option value="' . h((string) $key) . '"' . $sel . '>' . h($label) . '</option>';
    }
    echo '</select>';
}

function render_checkbox_group(string $name, array $options, array $selected): void
{
    foreach ($options as $key => $label) {
        $checked = in_array((string) $key, $selected, true) ? ' checked' : '';
        echo '<label><input type="checkbox" name="' . h($name) . '[]" value="' . h((string) $key) . '"' . $checked . '> '
            . h($label) . '</label>';
    }
}

// ---------- Адмін-панель: вхід, сесія, захист ----------

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function check_login(string $login, string $password): bool
{
    $loginOk = hash_equals(ADMIN_LOGIN, $login);
    $passOk = password_verify($password, ADMIN_PASSWORD_HASH);
    return $loginOk && $passOk;
}

// CSRF-токен: перевіряє, що кнопку «Видалити» натиснули саме на нашій сторінці
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(string $token): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ---------- База даних (SQLite через PDO) ----------

// Підключення до бази. Файл survey.sqlite і таблиця responses створюються автоматично.
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS responses (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                file         TEXT NOT NULL UNIQUE,
                submitted_at TEXT NOT NULL,
                name         TEXT NOT NULL,
                email        TEXT NOT NULL,
                power        TEXT NOT NULL,
                place        TEXT NOT NULL,
                effects      TEXT NOT NULL,
                exam_text    TEXT NOT NULL
            )'
        );
    }
    return $pdo;
}

// Повертає текст помилки, якщо база недоступна, або null, якщо все гаразд
function db_error(): ?string
{
    try {
        db();
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

// Додає відповідь у таблицю responses. Повертає true, якщо рядок додано
// (INSERT OR IGNORE: якщо файл уже є в базі, повторно не додається)
function insert_response_row(string $file, array $data): bool
{
    $answers = $data['answers'] ?? [];
    $effects = $answers[QUESTIONS['effects']] ?? [];

    $stmt = db()->prepare(
        'INSERT OR IGNORE INTO responses (file, submitted_at, name, email, power, place, effects, exam_text)
         VALUES (:file, :submitted_at, :name, :email, :power, :place, :effects, :exam_text)'
    );
    $stmt->execute([
        ':file'         => $file,
        ':submitted_at' => (string) ($data['submitted_at'] ?? ''),
        ':name'         => (string) ($data['name'] ?? ''),
        ':email'        => (string) ($data['email'] ?? ''),
        ':power'        => (string) ($answers[QUESTIONS['power']] ?? ''),
        ':place'        => (string) ($answers[QUESTIONS['place']] ?? ''),
        ':effects'      => json_encode(is_array($effects) ? $effects : [], JSON_UNESCAPED_UNICODE),
        ':exam_text'    => (string) ($answers[QUESTIONS['exam_text']] ?? ''),
    ]);
    return $stmt->rowCount() > 0;
}

// Переносить у базу JSON-файли з папки survey, яких там ще немає
// (наприклад, відповіді, згенеровані ШІ). Повертає кількість доданих.
function sync_files_to_db(): int
{
    if (!is_dir(SURVEY_DIR)) {
        return 0;
    }
    $added = 0;
    foreach (scandir(SURVEY_DIR) as $file) {
        if (!is_valid_response_file($file)) {
            continue;
        }
        $data = json_decode((string) file_get_contents(SURVEY_DIR . '/' . $file), true);
        if (is_array($data) && insert_response_row($file, $data)) {
            $added++;
        }
    }
    return $added;
}

// ---------- Адмін-панель: відповіді, видалення, експорт ----------

// Читає всі відповіді з бази (найновіші першими)
function load_responses(): array
{
    sync_files_to_db();

    $rows = db()->query('SELECT * FROM responses ORDER BY file DESC')->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $r) {
        $effects = json_decode($r['effects'], true);
        $list[] = [
            'id'   => (int) $r['id'],
            'file' => $r['file'],
            'data' => [
                'submitted_at' => $r['submitted_at'],
                'name'         => $r['name'],
                'email'        => $r['email'],
                'answers'      => [
                    QUESTIONS['power']     => $r['power'],
                    QUESTIONS['place']     => $r['place'],
                    QUESTIONS['effects']   => is_array($effects) ? $effects : [],
                    QUESTIONS['exam_text'] => $r['exam_text'],
                ],
            ],
        ];
    }
    return $list;
}

// Дозволяє лише імена виду 2026-11-11_17-42-08_318204.json (захист від ../)
function is_valid_response_file(string $file): bool
{
    return (bool) preg_match('/^[0-9_\-]+\.json$/', $file);
}

// Видаляє відповідь з бази і її JSON-файл
function delete_response(int $id): bool
{
    $stmt = db()->prepare('SELECT file FROM responses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $file = $stmt->fetchColumn();
    if ($file === false) {
        return false;
    }

    $del = db()->prepare('DELETE FROM responses WHERE id = :id');
    $del->execute([':id' => $id]);

    // Файл теж видаляємо, інакше sync_files_to_db() знову додав би його в базу
    $path = SURVEY_DIR . '/' . $file;
    if (is_valid_response_file($file) && is_file($path)) {
        unlink($path);
    }
    return true;
}

// Експорт усіх відповідей в один JSON-файл (завантажується браузером)
function export_json(array $responses): void
{
    $all = array_map(fn($r) => $r['data'], $responses);
    $name = 'survey_export_' . date('Y-m-d_H-i-s') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// Експорт у CSV (відкривається в Excel)
function export_csv(array $responses): void
{
    $name = 'survey_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');

    echo "\xEF\xBB\xBF"; // BOM, щоб Excel правильно показав українські літери
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Час', "Ім'я", 'Email'], array_values(QUESTIONS)), ';', '"', '');
    foreach ($responses as $r) {
        $d = $r['data'];
        $row = [
            (string) ($d['submitted_at'] ?? ''),
            (string) ($d['name'] ?? ''),
            (string) ($d['email'] ?? ''),
        ];
        foreach (QUESTIONS as $question) {
            $row[] = answer_to_text($d['answers'][$question] ?? '');
        }
        fputcsv($out, $row, ';', '"', '');
    }
    fclose($out);
}
