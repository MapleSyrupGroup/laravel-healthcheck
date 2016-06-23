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
     * @param UserLedgerGateway $userLedgerGateway
     */
//    public function __construct(UserLedgerGateway $userLedgerGateway)
//    {
//    }

    /**
     * @SWG\Api(
     *  path="/healthcheck",
     *  @SWG\Operation(
     *      method="GET",
     *      summary="Healthcheck",
     *      notes="Healthcheck",
     *      type="void",
     *      authorizations={},
     *   )
     * )
     */
    public function execute()
    {
        $httpResponseContent = '';
        $isProd = (bool) Input::get('prod', true);
        $healthcheck = new HealthCheck($isProd);

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
        $healthcheck->checkRedis();

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

}
