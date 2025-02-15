<?php

//php -S localhost:8080
namespace App;

require __DIR__ . '/../vendor/autoload.php';


use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();

//$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
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
$app->add(MethodOverrideMiddleware::class);

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

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $users = json_decode(file_get_contents('tt.dat'), true) ?? [];
    $filteredUsers = array_filter($users, fn($user) => str_contains($user['name'], $term));

    $messages = $this->get('flash')->getMessages();
    print_r($messages['success'][0]);
    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $messages];
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
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $id = random_int(1, 150000);
        $readedUsers = json_decode(file_get_contents('tt.dat'), true) ?? [];
        $user['id'] = $id;
        array_push($readedUsers, $user);
        $addedUsers['usr'] = json_encode($readedUsers);
        file_put_contents('tt.dat', $addedUsers);
        $this->get('flash')->addMessage('success', 'User успешно добавлен!');
        return $response->withRedirect($router->urlFor('users'), 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});


$app->get('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $readedUsers = json_decode(file_get_contents('tt.dat'), true) ?? [];
    foreach ($readedUsers as $user) {
        if ($user['id'] == $id) {
            $params = ['user' => $user];
            return $this->get('renderer')->render($response, 'users/show.phtml', $params);
        }
    }
    return $response->withRedirect($router->urlFor('users'), 404);
});

//редактирование
$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $id = $args['id'];
    $readedUsers = json_decode(file_get_contents('tt.dat'), true) ?? [];

    $messages = $this->get('flash')->getMessages();
    print_r($messages['success'][0]);
    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $messages];



    foreach ($readedUsers as $user) {
        if ($user['id'] == $id) {
            $params = ['user' => $user];
            return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
        }
    }
})->setName('editUser');


//внесение изменений
$app->patch('/users/{id}', function ($request, $response, array $args) use ($router)  {
    $id = $args['id'];
    $data = $request->getParsedBodyParam('user');
    //$validator = new Validator();
    //$errors = $validator->validate($data);
    $errors = [];
    if (count($errors) === 0) {
        // Ручное копирование данных из формы в нашу сущность
        $this->get('flash')->addMessage('success', 'User has been updated');
        $readedUsers = json_decode(file_get_contents('tt.dat'), true) ?? [];
        $tempUsers=[];
        $user['name'] = $data['name'];
        foreach ($readedUsers as $userT) {
            if ($userT['id'] == $id) {
                $user['id'] = $id;
                $user['email'] = $userT['email'];
                array_push($tempUsers, $user);
            } else {
                array_push($tempUsers, $userT);
            }
        }
        $addedUsers['usr'] = json_encode($tempUsers);
        file_put_contents('tt.dat', $addedUsers);
        $url = $router->urlFor('editUser', ['id' => $user['id']]);
        return $response->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});


$app->run();
