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
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\JsonResponse;

class RegisterController implements ControllerInterface

{
    /**
     * @var Client
     */
    protected $api;
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
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
    public function __construct(Client $api, SettingsRepositoryInterface $settings, SessionAuthenticator $authenticator, Rememberer $rememberer)
    {
        $this->api = $api;
        $this->settings = $settings;
        $this->authenticator = $authenticator;
        $this->rememberer = $rememberer;
    }
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request)
    {        
        $body = ['data' => ['attributes' => $request->getParsedBody()]];
        $username = array_get($body, 'attributes.username');
        $words = explode(', ', $this->settings->get('Words'));
        foreach ($words as $word)
        {
           if (stripos($username, $word) !== false || preg_match($word, $username)) {
           $response = new TextResponse("I'm a response!");
           return $response;
          
        } else {
        $controller = 'issyrocks12\filter\Api\CreateUserController';
        $actor = $request->getAttribute('actor');
        
        $response = $this->api->send($controller, $actor, [], $body);
      
        $type = $response->getHeader('Content-Type');
      
      
        $body = json_decode($response->getBody());
        
      
        if (isset($body->data)) {
            $userId = $body->data->id;
            $session = $request->getAttribute('session');
            $this->authenticator->logIn($session, $userId);
            $response = $this->rememberer->rememberUser($response, $userId);
            return $response;
        }
        }
      }
   }
}

