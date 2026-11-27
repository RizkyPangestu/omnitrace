<?php
declare(strict_types=1);

function submitted_password(): string
{
    $password = input($_POST, 'password');
    if ($password !== input($_POST, 'confirm')) {
        reject(422, 'The passwords must match.');
    }
    return hash_password($password);
}

function replace_password(int $userId, string $hash): void
{
    sql('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
    sql('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
    sql('DELETE FROM auth_sessions WHERE user_id = ?', [$userId]);
}

function login(?array $user, bool $post): void
{
    if ($user !== null) {
        redirect('/workspace');
    }
    $plan = input($post ? $_POST : $_GET, 'plan');
    $plan = isset(PLANS[$plan]) ? $plan : '';
    $error = null;
    if ($post) {
        throttle('login-ip', 20, 900);
        $email = email_address(input($_POST, 'email'));
        throttle('login-email', 8, 900, $email);
        $account = row('SELECT * FROM users WHERE email = ?', [$email]);
        if (password_matches($account, input($_POST, 'password')) && $account !== null) {
            start_authentication($account);
            redirect($plan !== '' && $account['role'] === 'client' ? '/cases/new?plan=' . $plan : '/workspace');
        }
        http_response_code(401);
        $error = 'Invalid email or password.';
    }
    render('auth', ['mode' => 'login', 'plan' => $plan, 'error' => $error]);
}

function register(?array $user, bool $post): void
{
    if ($user !== null) {
        redirect('/workspace');
    }
    $plan = input($post ? $_POST : $_GET, 'plan');
    $plan = isset(PLANS[$plan]) ? $plan : '';
    if ($post) {
        throttle('register', 8, 3600);
        $email = email_address(input($_POST, 'email'));
        $name = field('name', 'Full name', 100);
        $hash = submitted_password();
        sql(
            "INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, 'client')
             ON CONFLICT(email) DO NOTHING",
            [$email, $name, $hash],
        );
        flash('If the address is available, your account is ready. Sign in with your password.');
        redirect('/login' . ($plan === '' ? '' : '?plan=' . $plan));
    }
    render('auth', ['mode' => 'register', 'plan' => $plan, 'error' => null]);
}

function reset_password(bool $post): void
{
    if ($post) {
        throttle('reset', 8, 900);
        $digest = token_hash(field('code', 'Reset code', 100));
        $lookup = 'SELECT user_id FROM password_resets WHERE token_hash = ? AND expires > ?';
        if (row($lookup, [$digest, time()]) === null) {
            reject(422, 'The reset code is invalid or expired.');
        }
        $hash = submitted_password();
        transaction(function () use ($lookup, $digest, $hash): void {
            $reset = row($lookup, [$digest, time()]);
            if ($reset === null) {
                reject(422, 'The reset code is invalid or expired.');
            }
            replace_password((int) $reset['user_id'], $hash);
            end_authentication();
        });
        rotate_session();
        flash('Password updated. Sign in again.');
        redirect('/login');
    }
    render('auth', ['mode' => 'reset', 'plan' => '', 'error' => null]);
}

function logout(?array $user): never
{
    require_user($user);
    end_authentication();
    rotate_session();
    redirect('/login');
}

function profile(?array $user, bool $post): void
{
    $user = require_user($user);
    if ($post) {
        sql(
            'UPDATE users SET name = ?, organization = ? WHERE id = ?',
            [field('name', 'Full name', 100), field('organization', 'Organization', 160, 0), $user['id']],
        );
        flash('Profile saved.');
        redirect('/profile');
    }
    render('profile', ['user' => $user]);
}

function change_password(?array $user): never
{
    $user = require_user($user);
    throttle('change-password', 8, 900, (string) $user['id']);
    if (!password_matches($user, input($_POST, 'current_password'))) {
        reject(422, 'Current password is incorrect.');
    }
    $hash = submitted_password();
    transaction(function () use ($user, $hash): void {
        $current = row('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if ($current === null || $current['password_hash'] !== $user['password_hash']) {
            reject(409, 'The account changed. Sign in again.');
        }
        replace_password((int) $user['id'], $hash);
    });
    rotate_session();
    flash('Password updated. Sign in again.');
    redirect('/login');
}
