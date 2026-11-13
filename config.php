<?php
// config.php — налаштування сайту (змінні та константи в одному місці)

date_default_timezone_set('Europe/Kyiv');

// Папка, куди зберігаються відповіді
const SURVEY_DIR = __DIR__ . '/survey';

// Файл бази даних SQLite (створюється автоматично при першому зверненні)
const DB_FILE = __DIR__ . '/survey.sqlite';

// Дані адміністратора: логін admin, пароль lab6pass.
// Пароль зберігається не відкритим текстом, а у вигляді хешу.
// Щоб зробити свій пароль, виконай у терміналі:
//   php -r "echo password_hash('твій_пароль', PASSWORD_DEFAULT);"
// і встав результат нижче.
const ADMIN_LOGIN = 'admin';
const ADMIN_PASSWORD_HASH = '$2y$10$9KFH92VbNkI3D9sjnHlfNertoDy0Y7WA68CNYl8JR43mg5Mqvd2Gy';

// Питання анкети (ці підписи зберігаються у файлах відповідей)
const QUESTIONS = [
    'power'     => 'Яку суперсилу обираєш?',
    'place'     => 'Де використаєш її в першу чергу?',
    'effects'   => 'Яка побічна дія тебе не лякає?',
    'exam_text' => 'Як би використав суперсилу на заліку?',
];

// Варіанти відповідей
const POWERS = [
    'teleport' => 'Телепортація',
    'mind'     => 'Читання думок',
    'time'     => 'Зупинка часу',
    'invis'    => 'Невидимість',
    'fly'      => 'Політ',
];

const PLACES = [
    'exam'     => 'На заліку',
    'deadline' => 'Перед дедлайном',
    'canteen'  => 'У черзі в їдальні',
    'travel'   => 'У подорожах',
];

const SIDE_EFFECTS = [
    'sleep'  => 'Постійно хочеться спати',
    'hungry' => 'Завжди голодний',
    'wifi'   => 'Зникає Wi-Fi там, де я є',
    'luck'   => 'Не щастить у лотереях',
];
