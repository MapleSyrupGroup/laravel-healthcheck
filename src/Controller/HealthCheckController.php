<?php
namespace MapleSyrupGroup\HealthCheck\Controller;

use MapleSyrupGroup\Annotations\Swagger\Annotations as SWG;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use MapleSyrupGroup\QCommon\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;

class HealthCheckController extends BaseController
{

    /**
     * Used for kubernetes constant liveness probe: http://kubernetes.io/docs/user-guide/pod-states/#container-probes
     *
     * @return Response
     */
    public function executeReadiness()
    {
        $healthcheck = new HealthCheck($this->isProduction());
        $healthcheck->checkExtensions();
        $healthcheck->checkExtensionsConfig();
        $healthcheck->checkPermissions();
        $healthcheck->checkSession();
        $healthcheck->checkDatabase();
        $healthcheck->checkRabbit(
            getenv('RABBITMQ_HOST'),
            getenv('RABBITMQ_PORT'),
            getenv('RABBITMQ_LOGIN'),
            getenv('RABBITMQ_PASSWORD'),
            getenv('RABBITMQ_VHOST')
        );
        $healthcheck->checkCache();

        return $this->generateResponse($healthcheck);
    }

    /**
     * Used for kubernetes constant liveness probe: http://kubernetes.io/docs/user-guide/pod-states/#container-probes
     *
     * @return Response
     */
    public function executeLiveness()
    {
        $healthcheck = new HealthCheck($this->isProduction());
        $healthcheck->checkExtensions();
        $healthcheck->checkExtensionsConfig();
        $healthcheck->checkPermissions();
        $healthcheck->checkSession();

        return $this->generateResponse($healthcheck);
    }

    /**
     * Grab the messages out of the $healthcheck and also set HTTP status codes if any errors occurred
     *
     * @param HealthCheck $healthcheck
     * @return Response
     */
    private function generateResponse(HealthCheck $healthcheck)
    {
        $httpResponseContent = '';
        $errorsOccurred = false;
        // Begin processing output messages
        foreach($healthcheck->getMessages() as $message) {
            list($messageType, $text) = $message;
            if($messageType === HealthCheck::MESSAGE_TYPE_FAILURE) {
                $errorsOccurred = true;
            }
            $httpResponseContent .= "$text\n";
        }

        $httpResponseContent = sprintf("<pre>%s</pre>", $httpResponseContent);

        $response = new Response($httpResponseContent);

        if($errorsOccurred) {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    /**
     * @return bool
     */
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
