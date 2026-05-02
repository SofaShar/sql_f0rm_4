<?php
// Настройки подключения к БД
$host = 'localhost';
$dbname = 'form_db';
$username = 'user1';
$password = '123';

// Подключение PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

// Получаем список языков для select
$languages = [];
$stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $languages[] = $row;
}

// Функция валидации
function validateFormData($data, &$errors) {
    // 1. ФИО: только буквы, пробелы, дефис, длина ≤ 150
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $data['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефис.';
    } elseif (mb_strlen($data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не длиннее 150 символов.';
    }

    // 2. Телефон: цифры, +, -, пробелы, длина 5-20
    if (empty($data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[+\d\s\-]{5,20}$/', $data['phone'])) {
        $errors['phone'] = 'Телефон: цифры, +, -, пробелы, 5-20 символов.';
    }

    // 3. Email
    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email.';
    }

    // 4. Дата рождения
    if (empty($data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $data['birth_date'] || $date > new DateTime()) {
            $errors['birth_date'] = 'Некорректная или будущая дата.';
        }
    }

    // 5. Пол
    if (!in_array($data['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    // 6. Биография (макс 1000)
    if (mb_strlen($data['biography']) > 1000) {
        $errors['biography'] = 'Биография до 1000 символов.';
    }

    // 7. Контракт
    if (empty($data['contract_agreed'])) {
        $errors['contract_agreed'] = 'Подтвердите ознакомление с контрактом.';
    }

    // 8. Языки
    $validLangIds = array_column($GLOBALS['languages'], 'id');
    if (empty($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        foreach ($data['languages'] as $langId) {
            if (!in_array((int)$langId, $validLangIds)) {
                $errors['languages'] = 'Недопустимый язык.';
                break;
            }
        }
    }
}

// Обработка GET-запроса (отправка формы)
$successMessage = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['submit'])) {
    $formData = [
        'full_name' => trim($_GET['full_name'] ?? ''),
        'phone' => trim($_GET['phone'] ?? ''),
        'email' => trim($_GET['email'] ?? ''),
        'birth_date' => trim($_GET['birth_date'] ?? ''),
        'gender' => $_GET['gender'] ?? '',
        'biography' => trim($_GET['biography'] ?? ''),
        'contract_agreed' => isset($_GET['contract_agreed']),
        'languages' => $_GET['languages'] ?? []
    ];

    $errors = [];
    validateFormData($formData, $errors);

    if (empty($errors)) {
        // Сохраняем в БД
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_agreed)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_agreed)
            ");
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':birth_date' => $formData['birth_date'],
                ':gender' => $formData['gender'],
                ':biography' => $formData['biography'],
                ':contract_agreed' => $formData['contract_agreed'] ? 1 : 0
            ]);
            $appId = $pdo->lastInsertId();

            $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
            foreach ($formData['languages'] as $langId) {
                $stmtLang->execute([':app_id' => $appId, ':lang_id' => $langId]);
            }
            $pdo->commit();

            // Успех: сохраняем в Cookies на 1 год
            setcookie('saved_full_name', $formData['full_name'], time() + 365*24*3600, '/');
            setcookie('saved_phone', $formData['phone'], time() + 365*24*3600, '/');
            setcookie('saved_email', $formData['email'], time() + 365*24*3600, '/');
            setcookie('saved_birth_date', $formData['birth_date'], time() + 365*24*3600, '/');
            setcookie('saved_gender', $formData['gender'], time() + 365*24*3600, '/');
            setcookie('saved_biography', $formData['biography'], time() + 365*24*3600, '/');
            setcookie('saved_languages', implode(',', $formData['languages']), time() + 365*24*3600, '/');
            setcookie('saved_contract', $formData['contract_agreed'] ? '1' : '0', time() + 365*24*3600, '/');

            // Очищаем Cookies ошибок
            setcookie('form_errors', '', time() - 3600, '/');
            setcookie('old_input', '', time() - 3600, '/');

            $successMessage = 'Данные успешно сохранены!';
            $formData = []; // очищаем для отображения формы
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка сохранения: ' . $e->getMessage();
            // Сохраняем ошибки в Cookies
            setcookie('form_errors', serialize($errors), time() + 3600, '/');
            setcookie('old_input', serialize($formData), time() + 3600, '/');
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    } else {
        // Сохраняем ошибки и введённые данные в Cookies
        setcookie('form_errors', serialize($errors), time() + 3600, '/');
        setcookie('old_input', serialize($formData), time() + 3600, '/');
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// Чтение Cookies при загрузке страницы
$errors = [];
$oldInput = [];
$hasErrors = false;

if (isset($_COOKIE['form_errors'])) {
    $errors = unserialize($_COOKIE['form_errors']);
    $hasErrors = true;
}
if (isset($_COOKIE['old_input'])) {
    $oldInput = unserialize($_COOKIE['old_input']);
}

// Чтение сохранённых (успешных) Cookies для подстановки по умолчанию
$savedCookies = [
    'full_name' => $_COOKIE['saved_full_name'] ?? '',
    'phone' => $_COOKIE['saved_phone'] ?? '',
    'email' => $_COOKIE['saved_email'] ?? '',
    'birth_date' => $_COOKIE['saved_birth_date'] ?? '',
    'gender' => $_COOKIE['saved_gender'] ?? '',
    'biography' => $_COOKIE['saved_biography'] ?? '',
    'languages' => isset($_COOKIE['saved_languages']) ? explode(',', $_COOKIE['saved_languages']) : [],
    'contract_agreed' => ($_COOKIE['saved_contract'] ?? '') === '1'
];
if (isset($_COOKIE['form_errors'])) {
    $errors = unserialize($_COOKIE['form_errors']);
    setcookie('form_errors', '', time() - 3600, '/'); // удаляем
}
if (isset($_COOKIE['old_input'])) {
    $oldInput = unserialize($_COOKIE['old_input']);
    setcookie('old_input', '', time() - 3600, '/');
}
// Приоритет: старые введённые (при ошибке) > сохранённые cookies > пусто
function getFieldValue($fieldName, $oldInput, $savedCookies) {
    if (!empty($oldInput[$fieldName])) {
        return htmlspecialchars($oldInput[$fieldName]);
    }
    return htmlspecialchars($savedCookies[$fieldName] ?? '');
}

function isFieldError($fieldName, $errors) {
    return isset($errors[$fieldName]);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика (с Cookies)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Анкета разработчика</h1>

    <?php if ($successMessage): ?>
        <div class="success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if ($hasErrors && !empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="get" action="">
        <div class="form-group <?= isFieldError('full_name', $errors) ? 'has-error' : '' ?>">
            <label>ФИО *</label>
            <input type="text" name="full_name" value="<?= getFieldValue('full_name', $oldInput, $savedCookies) ?>">
            <?php if (isFieldError('full_name', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['full_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('phone', $errors) ? 'has-error' : '' ?>">
            <label>Телефон *</label>
            <input type="tel" name="phone" value="<?= getFieldValue('phone', $oldInput, $savedCookies) ?>">
            <?php if (isFieldError('phone', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['phone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('email', $errors) ? 'has-error' : '' ?>">
            <label>E-mail *</label>
            <input type="email" name="email" value="<?= getFieldValue('email', $oldInput, $savedCookies) ?>">
            <?php if (isFieldError('email', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('birth_date', $errors) ? 'has-error' : '' ?>">
            <label>Дата рождения *</label>
            <input type="date" name="birth_date" value="<?= getFieldValue('birth_date', $oldInput, $savedCookies) ?>">
            <?php if (isFieldError('birth_date', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['birth_date']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('gender', $errors) ? 'has-error' : '' ?>">
            <label>Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= (getFieldValue('gender', $oldInput, $savedCookies) === 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (getFieldValue('gender', $oldInput, $savedCookies) === 'female') ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isFieldError('gender', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['gender']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('languages', $errors) ? 'has-error' : '' ?>">
            <label>Любимый язык * (можно несколько)</label>
            <select name="languages[]" multiple size="6">
                <?php foreach ($languages as $lang): ?>
                    <?php
                    $selected = false;
                    if (!empty($oldInput['languages']) && in_array($lang['id'], $oldInput['languages'])) {
                        $selected = true;
                    } elseif (empty($oldInput) && in_array($lang['id'], $savedCookies['languages'])) {
                        $selected = true;
                    }
                    ?>
                    <option value="<?= $lang['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isFieldError('languages', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['languages']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('biography', $errors) ? 'has-error' : '' ?>">
            <label>Биография</label>
            <textarea name="biography" rows="4"><?= getFieldValue('biography', $oldInput, $savedCookies) ?></textarea>
            <?php if (isFieldError('biography', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['biography']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group checkbox <?= isFieldError('contract_agreed', $errors) ? 'has-error' : '' ?>">
            <label>
                <input type="checkbox" name="contract_agreed" value="1" <?= (getFieldValue('contract_agreed', $oldInput, $savedCookies) == 1) ? 'checked' : '' ?>>
                С контрактом ознакомлен(а) *
            </label>
            <?php if (isFieldError('contract_agreed', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['contract_agreed']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" name="submit">Сохранить</button>
    </form>
</div>
</body>
</html>
