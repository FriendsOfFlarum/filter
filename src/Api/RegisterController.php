<?php
/*
 * This file was a part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace issyrocks12\filter\Api;

use Flarum\Api\Client;
use Flarum\Http\Controller\ControllerInterface;
use Flarum\Http\Rememberer;
use Flarum\Http\SessionAuthenticator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\JsonResponse;

class RegisterController implements ControllerInterface

{
    /**
     * @var Client
     */
    protected $api;
    /**
     * @var SessionAuthenticator
     */
    protected $authenticator;
    /**
     * @var Rememberer
     */
    protected $rememberer;
    /**
     * @param Client $api
     * @param SessionAuthenticator $authenticator
     * @param Rememberer $rememberer
     */
    public function __construct(Client $api, SessionAuthenticator $authenticator, Rememberer $rememberer)
    {
        $this->api = $api;
        $this->authenticator = $authenticator;
        $this->rememberer = $rememberer;
    }
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request)
    {
        $controller = 'issyrocks12\filter\Api\CreateUserController';
        $actor = $request->getAttribute('actor');
        $body = ['data' => ['attributes' => $request->getParsedBody()]];
        
        $response = $this->api->send($controller, $actor, [], $body);
    
        $body = json_decode($response->getBody());
      
        if (isset($body->data)) {
          if ($body->data == "Filtered") 
          {
            $response = new JsonResponse($body->data, 580);
            return $response;
          } else {
            $userId = $body->data->id;
            $session = $request->getAttribute('session');
            $this->authenticator->logIn($session, $userId);
            $response = $this->rememberer->rememberUser($response, $userId);
            return $response;
           } 
        }
    }
}