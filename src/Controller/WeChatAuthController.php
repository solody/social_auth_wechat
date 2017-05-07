<?php

namespace Drupal\social_auth_wechat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_auth_wechat\WeChatAuthManager;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Manages requests to WeChat API.
 *
 * Most of the code here is specific to implement a WeChat login process. Social
 * Networking services might require different approaches.
 */
class WeChatAuthController extends ControllerBase {

	/**
	 * The network plugin manager.
	 *
	 * @var \Drupal\social_api\Plugin\NetworkManager
	 */
	private $networkManager;

	/**
	 * The WeChat authentication manager.
	 *
	 * @var \Drupal\social_auth_wechat\WeChatAuthManager
	 */
	private $wechatManager;

	/**
	 * The user manager.
	 *
	 * @var \Drupal\social_auth\SocialAuthUserManager
	 */
	private $userManager;

	/**
	 * GoogleLoginController constructor.
	 *
	 * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
	 *   Used to get an instance of social_auth_google network plugin.
	 * @param \Drupal\social_auth_wechat\WeChatAuthManager $wechat_manager
	 *   Used to manage authentication methods.
	 * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
	 *   Manages user login/registration.
	 */
	public function __construct(NetworkManager $network_manager, WeChatAuthManager $wechat_manager, SocialAuthUserManager $user_manager) {
		$this->networkManager = $network_manager;
		$this->wechatManager = $wechat_manager;
		$this->userManager = $user_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
				$container->get('plugin.network.manager'),
				$container->get('wechat_auth.manager'),
				$container->get('social_auth.user_manager')
				);
	}

	/**
	 * Redirects to Google Services Authentication page.
	 *
	 * Most of the Social Networks' API require you to redirect users to a
	 * authentication page. This method is not a mandatory one, instead you must
	 * adapt to the requirements of the module you are implementing.
	 *
	 * This method is called in 'social_auth_wechat.redirect_to_wechat' route.
	 * @see social_auth_wechat.routing.yml.
	 *
	 * This method is triggered when the user loads user/login/google. It creates
	 * an instance of the Network Plugin 'social auth google' and returns an
	 * instance of the \Google_Client object.
	 *
	 * It later sets the permissions that should be asked for, and redirects the
	 * user to Google Accounts to allow him to grant those permissions.
	 *
	 * After the user grants permission, Google redirects him to a url specified
	 * in the Google project settings. In this case, it should redirects to
	 * 'user/login/google/callback', which calls the callback method.
	 *
	 * @return \Zend\Diactoros\Response\RedirectResponse
	 *   Redirection to Google Accounts.
	 */
	public function redirectToWeChat() {
		/* @var \Google_Client $client */
		// Creates an instance of the Network Plugin and gets the SDK.
		$client = $this->networkManager->createInstance('social_auth_wechat')->getSdk();
		// Sets the scopes (permissions to ask for).
		$client->setScopes(array('email', 'profile'));

		// Redirects to WeChat Accounts to allow the user grant the permissions.
		return new RedirectResponse($client->createAuthUrl());
	}

	/**
	 * Callback function to login user.
	 *
	 * Most of the Social Networks' API redirects to callback url. This method is
	 * not a mandatory one, instead you must adapt to the requirements of the
	 * module you are implementing.
	 *
	 * This method is called in 'social_auth_wechat.callback' route.
	 * @see social_auth_wechat.routing.yml.
	 *
	 * This method is triggered when the path user/login/wechat/callback is
	 * loaded. It creates an instance of the Network Plugin 'social auth wechat'.
	 *
	 * It later authenticates the user and creates the service to obtain data
	 * about the user.
	 *
	 * After the user is authenticated, it checks if a user with the same email
	 * has already registered. If so, it logins that user; if not, it creates
	 * a new user with the information provided by the social network and logins
	 * the new user.
	 */
	public function callback() {
		/* @var \Google_Client $client */
		// Creates the Network Plugin instance and get the SDK.
		$client = $this->networkManager->createInstance('social_auth_wechat')->getSdk();

		// Authenticate the user and obtains his data.
		$this->wechatManager->setClient($client)
		->authenticate()
		->createService();

		// Gets user information.
		$user = $this->wechatManager->getUserInfo();

		// If user information could be retrieved.
		if ($user) {
      // Uses authenticateUser method to create and/or login an user.
			$this->userManager->authenticateUser($user->getEmail(), $user->getName(), $user->getId(), $user->getPicture());
		}

		drupal_set_message($this->t('You could not be authenticated, please contact the administrator'), 'error');
		return $this->redirect('user.login');
	}

}
