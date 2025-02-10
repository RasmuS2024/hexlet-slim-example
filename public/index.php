<?php

//php -S localhost:8080

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
$courses = ['ЗРЗ', 'PHP', 'Java', 'Python', 'JavaScript'];

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
/*
AppFactory::setContainer($container);
$app = AppFactory::create();
*/
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
})->setName('root');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');

$app->get('/courses', function ($request, $response) use ($courses) {
    $params = [
        'courses' => $courses
    ];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
})->setName('courses');

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    $filteredUsers = array_filter($users, fn($user) => str_contains($user, $term));
    $params = ['users' => $filteredUsers, 'term' => $term];

    $messages = $this->get('flash')->getMessages();
    print_r($messages['success'][0]);
    $params = ['flash' => $messages];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');



$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => '', 'city' => ''],
        'errors' => []
    ];
    
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('newuser');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $id = random_int(1, 150000);
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $readedUsers = json_decode(file_get_contents('tt.dat'), true) ?? [];
    array_push($readedUsers, array($id => $user));
    $addedUsers['usr'] = json_encode($readedUsers);
    file_put_contents('tt.dat', $addedUsers);

    $this->get('flash')->addMessage('success', 'User успешно добавлен!');

    return $response->withRedirect($router->urlFor('users'), 302);
});


$app->get('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $readedUsers = json_decode(file_get_contents('tt.dat'), true)[0] ?? [];
    if (array_key_exists($id, $readedUsers)) {
        $user = $readedUsers[$id];
        $params = ['id' => $id, 'name' => $user['name'], 'email' => $user['email']];
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
    return $response->withRedirect($router->urlFor('users'), 404);
});




$app->run();
