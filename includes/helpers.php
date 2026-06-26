<?php
// ============================================================
//  H.A.P.A.G. — General Helper Functions
//  includes/helpers.php
// ============================================================

/**
 * Return a clean JSON response and exit.
 *
 * @param  mixed  $data     Response payload
 * @param  int    $status   HTTP status code
 * @param  string $message  Optional message
 */
function json_response(mixed $data = null, int $status = 200, string $message = ''): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['status' => $status < 400 ? 'success' : 'error'];
    if ($message)       $payload['message'] = $message;
    if ($data !== null) $payload['data']    = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sanitise a plain string input.
 */
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Return POST value or default.
 */
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

/**
 * Return GET value or default.
 */
function get_param(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

/**
 * Validate email format.
 */
function valid_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Calculate recommended daily calories (Mifflin-St Jeor approximation).
 *
 * @param string $goal   muscle|weightloss|maintenance|performance|family
 * @param string $sex    male|female
 * @param int    $age
 * @param float  $weight_kg
 * @param float  $height_cm
 * @param string $activity sedentary|light|moderate|active|very_active
 */
function calc_calories(
    string $goal       = 'maintenance',
    string $sex        = 'male',
    int    $age        = 25,
    float  $weight_kg  = 65,
    float  $height_cm  = 165,
    string $activity   = 'moderate',
    ?int   $custom_kcal = null
): array {
    // If user set a custom calorie target, use it directly
    if ($custom_kcal && $custom_kcal >= 1000 && $custom_kcal <= 5000) {
        $target_kcal = $custom_kcal;
    } else {
        $bmr = ($sex === 'female')
            ? 10 * $weight_kg + 6.25 * $height_cm - 5 * $age - 161
            : 10 * $weight_kg + 6.25 * $height_cm - 5 * $age + 5;

        $activity_multipliers = [
            'sedentary'  => 1.2,
            'light'      => 1.375,
            'moderate'   => 1.55,
            'active'     => 1.725,
            'very_active'=> 1.9,
        ];
        $tdee = $bmr * ($activity_multipliers[$activity] ?? 1.55);

        $goal_adjust = match ($goal) {
            'muscle'      => +300,
            'weightloss'  => -500,
            'performance' => +400,
            default       => 0,
        };

        $target_kcal = round($tdee + $goal_adjust);
    }

    $protein = match ($goal) {
        'muscle', 'performance' => round($weight_kg * 2.0),
        'weightloss'             => round($weight_kg * 1.8),
        default                  => round($weight_kg * 1.4),
    };
    $fat   = round($target_kcal * 0.25 / 9);
    $carbs = round(($target_kcal - $protein * 4 - $fat * 9) / 4);

    return [
        'kcal'    => $target_kcal,
        'protein' => $protein,
        'carbs'   => $carbs,
        'fat'     => $fat,
    ];
}

/**
 * Get the Monday of the current (or given) week.
 */
function week_monday(?string $date = null): string {
    $ts = $date ? strtotime($date) : time();
    $dow = (int) date('N', $ts);  // 1=Mon … 7=Sun
    return date('Y-m-d', $ts - ($dow - 1) * 86400);
}
