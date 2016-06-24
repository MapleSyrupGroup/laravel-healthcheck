<?php
namespace MapleSyrupGroup\HealthCheck\Controller;

use MapleSyrupGroup\Annotations\Swagger\Annotations as SWG;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use MapleSyrupGroup\QCommon\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;

class HealthCheckController extends BaseController
{
    public function execute()
    {
        $httpResponseContent = '';
        $healthcheck = new HealthCheck($this->isProduction());
        $healthcheck->checkExtensions();
        $healthcheck->checkExtensionsConfig();
        $healthcheck->checkDatabase();
        $healthcheck->checkRabbit(
            getenv('RABBITMQ_HOST'),
            getenv('RABBITMQ_PORT'),
            getenv('RABBITMQ_LOGIN'),
            getenv('RABBITMQ_PASSWORD'),
            getenv('RABBITMQ_VHOST')
        );
        $healthcheck->checkCache();

        // Begin processing console messages
        $errorsOccurred = false;
        foreach($healthcheck->getMessages() as $message) {
            list($messageType, $text) = $message;
            if($messageType === HealthCheck::MESSAGE_TYPE_FAILURE) {
                $errorsOccurred = true;
            }
            $httpResponseContent .= "$text\n";
        }

        $response = new Response($httpResponseContent);

        if($errorsOccurred) {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }


        return $response;
    }

    private function isProduction()
    {
        $prod = Input::get('prod', true);
        if(is_string($prod)) {
            if($prod === "false") {
                $prod = false;
            } else {
                $prod = true;
            }
        }
        return $prod;
    }

}
