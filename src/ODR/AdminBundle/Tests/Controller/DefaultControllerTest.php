<?php

namespace ODR\AdminBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public static $client = "";
    public $token = "";

    /**
     * testIndex
     *
     * DEBUG=DefaultController ./vendor/phpunit/phpunit/phpunit -c app/
     *
     *
     */
    public function testIndex()
    {
        $debug = (getenv("DEBUG") == "DefaultController" ? true: false);
        self::$client = static::createClient();

        // Test that the outer frame loaded
        ($debug ? fwrite(STDERR, "Test the outer frame loaded.\n"):'');
        $crawler = self::$client->request('GET', 'http://odr.localhost/admin');

        // Show the actual content if debug enabled.
        ($debug ? fwrite(STDERR, self::$client->getResponse()->getContent()) . "\n":'');

        // Should redirect to login
        ($debug ? fwrite(STDERR, "Should redirect to login.\n"):'');
        $this->assertTrue($crawler->filter('html:contains("Redirecting to")')->count() > 0);
    }

    /**
     *
     */
    public function testIndex2()
    {
        $debug = (getenv("DEBUG") == "DefaultController" ? true: false);

        // Test that the outer frame loaded
        ($debug ? fwrite(STDERR, "Test the outer frame loaded.\n"):'');
        $crawler = self::$client->request('GET', 'http://odr.localhost/admin');
        ($debug ? fwrite(STDERR, self::$client->getResponse()->getContent()) . "\n":'');

        // Should redirect to login
        ($debug ? fwrite(STDERR, "222Should redirect to login.\n"):'');
        $this->assertTrue($crawler->filter('html:contains("Redirecting to")')->count() > 0);
    }
    /*
     * Check to make sure that the login page contains _username, _password, and
     * and _submit fields.
     */
    public function testLoginPage()
    {
        $debug = (getenv("DEBUG") == "DefaultController" ? true: false);
        ($debug ? fwrite(STDERR, "Test the outer frame loaded.\n"):'');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $this->assertTrue($crawler->filter('html:contains("username")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Password")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Login")')->count() > 0);
        
    }
    public function testUserLogin()
    {
        $debug = (getenv("DEBUG") == "DefaultController" ? true : false);
        ($debug ? fwrite(STDERR, "Testing user login.\n") : '');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        self::$client->followRedirects();
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        ($debug ? fwrite(STDERR, "Should produce a csrf token: " . $this->token . "\n") : '');
        $this->assertTrue($crawler->filter('html:contains("username")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Password")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Login")')->count() > 0);
        ($debug ? fwrite(STDERR, "Should log in the user\n") : '');
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        $this->assertTrue($crawler->filter('html:contains("admin")')->count() > 0);
        ($debug ? fwrite(STDERR, "Checking Logout Present\n") : '');
        ($debug ? fwrite(STDERR, self::$client->getResponse()->getContent()) . "\n" : '');
        $this->assertTrue($crawler->filter('html:contains("Logout")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Sam Mosher")')->count() > 0);
    }
    public function testStillLoggedIn()
    {
        $crawler = self::$client->request('GET', 'http://odr.localhost/admin');
        $this->assertTrue($crawler->filter('html:contains("Sam Mosher")')->count()>0);
    }

}
