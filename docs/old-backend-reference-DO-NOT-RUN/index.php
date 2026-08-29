<?php

require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/AdminController.php';
require_once __DIR__ . '/../src/LearnerController.php';
require_once __DIR__ . '/../src/ClassController.php';
require_once __DIR__ . '/../src/ActivityController.php';
require_once __DIR__ . '/../src/SessionController.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];

parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);

// Simple route table: "METHOD path" => callable
$routes = [
    'POST /api/auth/register-teacher' => fn() => AuthController::registerTeacher($body),
    'POST /api/auth/register-parent'  => fn() => AuthController::registerParent($body),
    'POST /api/auth/login'            => fn() => AuthController::login($body),
    'POST /api/auth/logout'           => fn() => AuthController::logout(),
    'GET /api/auth/me'                => fn() => AuthController::me(),
    'POST /api/learner/login-pin'     => fn() => AuthController::learnerLoginPin($body),
    'GET /api/learner/me'             => fn() => AuthController::learnerMe(),
    'POST /api/learner/logout'        => fn() => AuthController::learnerLogout(),

    'GET /api/admin/pending-teachers'    => fn() => AdminController::pendingTeachers(),
    'POST /api/admin/teacher-status'     => fn() => AdminController::teacherStatus($body),
    'GET /api/admin/all-users'           => fn() => AdminController::allUsers(),
    'POST /api/admin/toggle-user-status' => fn() => AdminController::toggleUserStatus($body),

    'POST /api/learner/create'      => fn() => LearnerController::create($body),
    'GET /api/learner/my-learners'  => fn() => LearnerController::myLearners(),
    'POST /api/learner/link-parent' => fn() => LearnerController::linkParent($body),

    'POST /api/class/create'      => fn() => ClassController::create($body),
    'GET /api/class/my-classes'   => fn() => ClassController::myClasses($query),
    'POST /api/learner/join-class' => fn() => ClassController::joinClass($body),
    'GET /api/class/roster'       => fn() => ClassController::roster($query),

    'POST /api/activity/generate'      => fn() => ActivityController::generate($body),
    'GET /api/activity/my-activities'  => fn() => ActivityController::myActivities(),
    'POST /api/activity/decision'      => fn() => ActivityController::decision($body),
    'POST /api/activity/assign'        => fn() => ActivityController::assign($body),
    'POST /api/activity/share'         => fn() => ActivityController::share($body),

    'GET /api/session/available-activities'    => fn() => SessionController::availableActivities(),
    'GET /api/session/next-diagnostic-passage' => fn() => SessionController::nextDiagnosticPassage(),
    'POST /api/session/submit'                 => fn() => SessionController::submit($body),
];

$key = "$method $path";

if (isset($routes[$key])) {
    $routes[$key]();
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => "No route for $key"]);
}
