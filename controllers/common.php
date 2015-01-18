<?php

use PicoFarad\Router;
use PicoFarad\Response;
use PicoFarad\Request;
use PicoFarad\Session;
use PicoFarad\Template;

// Called before each action
Router\before(function($action) {

    Session\open(BASE_URL_DIRECTORY, SESSION_SAVE_PATH, 0);

    // Select the requested database. If it fails, logout to destroy session and
    // 'remember me' cookie
    if (! empty($_SESSION['database'])) {
        if (! Model\Database\select($_SESSION['database'])) {
            Model\User\logout();
            Response\redirect('?action=login');
        }
    }

    // These actions are considered to be safe even for unauthenticated users
    $safe_actions = array('login', 'bookmark-feed', 'select-db', 'logout', 'notfound');

    if (! Model\User\is_loggedin() && ! in_array($action, $safe_actions)) {
        if (! Model\RememberMe\authenticate()) {
            Model\User\logout();
            Response\redirect('?action=login');
        }
    }
    else if (Model\RememberMe\has_cookie()) {
        Model\RememberMe\refresh();
    }

    // Load translations
    $language = Model\Config\get('language') ?: 'en_US';
    
    if ($language !== 'en_US') {
        Translator\load($language);
    }

    // Set timezone
    date_default_timezone_set(Model\Config\get('timezone') ?: 'UTC');

    // HTTP secure headers
    Response\csp(array(
        'media-src' => '*',
        'img-src' => '*',
        'frame-src' => Model\Config\get_iframe_whitelist(),
    ));

    Response\xframe();
    Response\xss();
    Response\nosniff();
});

// Show help
Router\get_action('show-help', function() {

    Response\html(Template\load('show_help'));
});

// Show the menu for the mobile view
Router\get_action('more', function() {

    Response\html(Template\layout('show_more', array('menu' => 'more')));
});

// Select another database
Router\get_action('select-db', function() {

    if (ENABLE_MULTIPLE_DB) {
        $_SESSION['database'] = \Model\Database\select(Request\param('database'));
    }

    Response\redirect('?action=login');
});

// Image proxy (avoid SSL mixed content warnings)
Router\get_action('proxy', function() {
    list($content, $type) = Model\Proxy\download(rawurldecode(Request\param('url')));

    if (empty($content)) {
        Response\text('Not Found', 404);
    }

    Response\content_type($type);
    Response\raw($content);
});
