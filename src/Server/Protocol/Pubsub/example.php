<?php
declare(strict_types=1);
include_once __DIR__ . '/../../../../vendor/autoload.php';

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\Stats;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Definition;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Publish;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Subscribe;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Unsubscribe;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\StatusResponse;

$pubsub = new Definition(
    [
        'publish' => 'publish',
        'subscribe' => 'subscribe',
        'unsubscribe' => 'unsubscribe'
    ]
);
$sep = str_repeat('-', 100) . PHP_EOL;
$pubsub->addActionResolver($pubsub->publish, Publish::class);
echo PHP_EOL.$sep;
echo "📨 Publish request format: " . json_encode(Publish::getModel()) . PHP_EOL;
echo $sep;
$pubsub->addActionResolver($pubsub->subscribe, Subscribe::class);
echo "📬 Subscribe request format: " . json_encode(Subscribe::getModel()) . PHP_EOL;
echo $sep;
$pubsub->addActionResolver($pubsub->unsubscribe, Unsubscribe::class);
echo "📭 Unsubscribe request format: " . json_encode(Unsubscribe::getModel()) . PHP_EOL;
echo $sep;

$pubsub->addResponseType($pubsub->publish, StatusResponse::class);
echo "📩 Publish response format: " . json_encode(StatusResponse::getModel()) . PHP_EOL;
echo $sep;
$pubsub->addResponseType($pubsub->subscribe, StatusResponse::class);
echo "📩 Subscribe response format: " . json_encode(StatusResponse::getModel()) . PHP_EOL;
echo $sep;
$pubsub->addResponseType($pubsub->unsubscribe, StatusResponse::class);
echo "📩 Unsubscribe response format: " . json_encode(StatusResponse::getModel()) . PHP_EOL;
echo $sep;
function resolvePublish(...$args): string
{
    $title = ' 🏗️ Exec resolvePublish ';
    $size = 100 - strlen($title);
    $title = str_repeat('*', (int)ceil($size / 2)) . $title . str_repeat('*', (int)floor($size / 2)) . PHP_EOL;
    $passedArguments = var_export($args, true);
    $end = PHP_EOL. str_repeat('*', 100) . PHP_EOL;
    echo <<<DESC
    $title
    This is the resolvePublish function, it will be called when the server receives a publish request.
    Here we can mix the request (first argument passed) with extra arguments, 
    for example we can pass the protocol instance to access its properties or methods, 
    or we can pass any other context information we need to process the request.
    In this example we will just return 'ok' to acknowledge the request.
    
    Arguments passed: 
    $passedArguments
    $end
    DESC;
    return 'ok';
}

//Ej: payload sent by client
$payload = "{\"action\":\"publish\",\"payload\":{\"topic\":\"topic.channel\",\"message\":\"hello world\"}}";

if ($pubsub->validateMessage($payload)) {
    echo "Message is valid." . PHP_EOL;
    // Ask protocol to resolve the request
    $result = $pubsub->resolve('publish', 'topic.channel', 'hello world', 'resolvePublish', $pubsub);

    // process response (with 'resolvePublish' in this case) and get the response to send it back to client
    // calling class as function execute ->handle method (__invoke)
    $response = $result('arg1', 'arg2')->getResponse([
        'action' => $pubsub->publish,
        '_metadata' => new Stats(
            worker_id: 0
        )
    ]);
    var_dump($response->isValid(), $response);
} else {
    echo "Message is invalid.\n";
}
