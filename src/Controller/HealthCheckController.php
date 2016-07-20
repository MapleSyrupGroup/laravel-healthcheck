<?php
namespace MapleSyrupGroup\HealthCheck\Controller;

use MapleSyrupGroup\Annotations\Swagger\Annotations as SWG;
use MapleSyrupGroup\HealthCheck\HealthCheck;
use MapleSyrupGroup\QCommon\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Input;
use Illuminate\Database\Migrations\Migrator;

class HealthCheckController extends BaseController
{
    /**
     * @var HealthCheck
     */
    private $healthCheck;

    public function __construct(HealthCheck $healthCheck)
    {
        parent::__construct();
        $healthCheck->setIsProduction($this->isProduction());
        $this->healthCheck = $healthCheck;
    }

    /**
     * Used for kubernetes periodic readiness probe: http://kubernetes.io/docs/user-guide/pod-states/#container-probes
     *
     * @return Response
     */
    public function executeReadiness()
    {
        $this->healthCheck->checkExtensions();
        $this->healthCheck->checkExtensionsConfig();
        $this->healthCheck->checkPermissions();
        $this->healthCheck->checkSession();
        $this->healthCheck->checkDatabase();
        $this->healthCheck->checkRabbit();
        $this->healthCheck->checkCache();
        $this->healthCheck->checkMigrations();

        return $this->generateResponse($this->healthCheck);
    }

    /**
     * Used for kubernetes constant liveness probe: http://kubernetes.io/docs/user-guide/pod-states/#container-probes
     *
     * @return Response
     */
    public function executeLiveness()
    {
        $this->healthCheck->checkExtensions();
        $this->healthCheck->checkExtensionsConfig();
        $this->healthCheck->checkPermissions();
        $this->healthCheck->checkSession();

        return $this->generateResponse($this->healthCheck);
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
        foreach ($healthcheck->getMessages() as $message) {
            list($messageType, $text) = $message;
            if ($messageType === HealthCheck::MESSAGE_TYPE_FAILURE) {
                $errorsOccurred = true;
            }
            $httpResponseContent .= "$text\n";
        }

        $httpResponseContent = sprintf("<pre>%s</pre>", $httpResponseContent);

        $response = new Response($httpResponseContent);

        if ($errorsOccurred) {
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
        if (is_string($prod)) {
            if ($prod === "false") {
                $prod = false;
            } else {
                $prod = true;
            }
        }
        return $prod;
    }
}
