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

// Зберігає відповідь у файл survey/РРРР-ММ-ДД_ГГ-ХХ-СС_мкс.json
// Повертає ['file' => ..., 'saved_at' => ...] або null, якщо не вдалося
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

    return $ok === false ? null : ['file' => $fileName, 'saved_at' => $savedAt];
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
    ];

    if (!$result['errors']) {
        $saved = save_response($values);
        if ($saved === null) {
            $result['errors'][] = 'Не вдалося зберегти відповідь.';
        } else {
            $result['submitted'] = true;
            $result['saved_at'] = $saved['saved_at'];
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

// ---------- Адмін-панель: відповіді, видалення, експорт ----------

// Читає всі JSON-файли з папки survey (найновіші першими)
function load_responses(): array
{
    $list = [];
    if (!is_dir(SURVEY_DIR)) {
        return $list;
    }
    foreach (scandir(SURVEY_DIR) as $file) {
        if (!is_valid_response_file($file)) {
            continue;
        }
        $data = json_decode((string) file_get_contents(SURVEY_DIR . '/' . $file), true);
        if (is_array($data)) {
            $list[] = ['file' => $file, 'data' => $data];
        }
    }
    usort($list, fn($a, $b) => strcmp($b['file'], $a['file']));
    return $list;
}

// Дозволяє лише імена виду 2026-11-11_17-42-08_318204.json (захист від ../)
function is_valid_response_file(string $file): bool
{
    return (bool) preg_match('/^[0-9_\-]+\.json$/', $file);
}

function delete_response(string $file): bool
{
    if (!is_valid_response_file($file)) {
        return false;
    }
    $path = SURVEY_DIR . '/' . $file;
    return is_file($path) && unlink($path);
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
