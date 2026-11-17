
<?php
require __DIR__ . '/../includes/api.php';

require_admin();

$method = $_SERVER['REQUEST_METHOD'];

function read_setting(string $key, array $fallback = []): array {
    global $mysqli;

    $stmt = $mysqli->prepare(
        "SELECT setting_value FROM settings WHERE setting_key = ?"
    );
    $stmt->bind_param('s', $key);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !$row['setting_value']) {
        return $fallback;
    }

    $value = json_decode($row['setting_value'], true);

    return is_array($value) ? $value : $fallback;
}

function save_setting(string $key, array $value): void {
    global $mysqli;

    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        json_error('Could not encode settings.', 422);
    }

    $stmt = $mysqli->prepare(
        "INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    $stmt->bind_param('ss', $key, $json);
    $stmt->execute();
}

$companyDefaults = COMPANY;

if ($method === 'GET') {
    $company = array_merge(
        $companyDefaults,
        read_setting('company_profile')
    );

    $settings = read_setting('app_settings');

    json_out([
        'ok' => true,
        'company' => $company,
        'settings' => $settings
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    require_csrf();

    $input = read_json();

    $mysqli->begin_transaction();

    try {
        // Save general settings-page values.
        if (isset($input['settings'])) {
            if (!is_array($input['settings'])) {
                json_error('Invalid settings data.', 422);
            }

            if (count($input['settings']) > 100) {
                json_error('Too many settings.', 422);
            }

            save_setting('app_settings', $input['settings']);
        }

        // Save company profile and bank details.
        if (isset($input['company'])) {
            if (!is_array($input['company'])) {
                json_error('Invalid company profile.', 422);
            }

            $allowed = [
                'name', 'legal', 'slogan', 'tagline',
                'phone', 'email', 'web', 'address',
                'ntn', 'strn', 'bank', 'acTitle',
                'acNo', 'iban', 'methods', 'terms'
            ];

            $company = array_merge(
                $companyDefaults,
                read_setting('company_profile')
            );

            foreach ($allowed as $field) {
                if (array_key_exists($field, $input['company'])) {
                    $value = trim((string)$input['company'][$field]);

                    if (mb_strlen($value) > 1000) {
                        json_error('Company field is too long: ' . $field, 422);
                    }

                    $company[$field] = $value;
                }
            }

            if ($company['name'] === '') {
                json_error('Company name is required.', 422);
            }

            if (!is_numeric($company['terms']) ||
                (int)$company['terms'] < 1 ||
                (int)$company['terms'] > 365) {
                json_error('Payment terms must be between 1 and 365 days.', 422);
            }

            $company['terms'] = (int)$company['terms'];

            save_setting('company_profile', $company);
        }

        $mysqli->commit();

        audit(
            'update',
            'settings',
            null,
            'Company profile or application settings updated'
        );

        json_out([
            'ok' => true,
            'message' => 'Settings saved successfully.',
            'company' => array_merge(
                $companyDefaults,
                read_setting('company_profile')
            ),
            'settings' => read_setting('app_settings')
        ]);

    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}

json_error('Method not allowed.', 405);
