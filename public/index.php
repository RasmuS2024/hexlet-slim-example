<?php
//php -S localhost:8080
namespace App;

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;
use App\CarRepository;
use App\Car;


session_start();
$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});



$container->set(\PDO::class, function () {
    //$conn = new \PDO('sqlite:hexlet');
    $conn = new \PDO('sqlite:database.sqlite');
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});
$initFilePath = implode('/', [dirname(__DIR__), 'init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

$container->set(CarRepository::class, function ($container) {
    return new CarRepository($container->get(\PDO::class));
});





$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
$router = $app->getRouteCollector()->getRouteParser();



$app->get('/cars', function ($request, $response) {
    $carRepository = $this->get(CarRepository::class);
    $cars = $carRepository->getEntities();

    $messages = $this->get('flash')->getMessages();

    $params = [
      'cars' => $cars,
      'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/index.phtml', $params);
})->setName('cars.index');


$app->get('/cars/new', function ($request, $response) {
    $params = [
        'car' => new Car(),
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'cars/new.phtml', $params);
})->setName('cars.create');


$app->get('/cars/{id}', function ($request, $response, $args) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];
    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'car' => $car,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/show.phtml', $params);
})->setName('cars.show');


$app->post('/cars', function ($request, $response) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $carData = $request->getParsedBodyParam('car');
    //var_dump($carData);
    //$validator = new CarValidator();
    //$errors = $validator->validate($carData);
    $errors=[];
    if (count($errors) === 0) {
        $car = Car::fromArray([$carData['make'], $carData['model']]);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', 'Car was added successfully');
        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $params = [
        'car' => $carData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/new.phtml', $params);
})->setName('cars.store');













$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'auth.phtml');
})->setName('root');

$app->get('/auth', function ($request, $response) use ($router) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $data = $request->getParsedBodyParam('email');
    foreach($users as $user) {
        if ($user['email'] == $data) {
            $_SESSION['email'] = $data;
        }
    }
    return $response->withRedirect($router->urlFor('users'), 302);
});

$app->get('/exit', function ($request, $response) use ($router) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect($router->urlFor('root'), 302);
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    //var_dump($request->getCookieParams());
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Логируем ошибку или обрабатываем
        //error_log("Ошибка JSON: " . json_last_error_msg());
        $users = []; // Используем пустой массив по умолчанию
    }
    $filteredUsers = array_filter($users, fn($user) => str_contains($user['name'], $term));
    $messages = $this->get('flash')->getMessages();
    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $messages];
    //$encodedUsers = json_encode($users);
    //$response = $response->withHeader('Set-Cookie', "users={$encodedUsers}");
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'name' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('newuser');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        //print_r($messages['success'][0]);
        $readedUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
        $id = random_int(1, 150000);
        $user['id'] = $id;
        array_push($readedUsers, $user);
        $encodedUsers = json_encode($readedUsers);
        //var_dump($encodedUsers);
        $response = $response->withHeader('Set-Cookie', "users={$encodedUsers}");
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
    $readedUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
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
    $readedUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
    $messages = $this->get('flash')->getMessages();
    //print_r($messages['success'][0]);
    $params = ['flash' => $messages];
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
        $readedUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
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
        $encodedUsers = json_encode($tempUsers);
        $url = $router->urlFor('users', ['id' => $user['id']]);
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($url);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $readedUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
    $tempUsers=[];
    foreach ($readedUsers as $userT) {
            if ($userT['id'] != $id) {
                array_push($tempUsers, $userT);
            }
        }
        $encodedUsers = json_encode($tempUsers);
        $response = $response->withHeader('Set-Cookie', "users={$encodedUsers}");
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('users'));
});

$app->run();
