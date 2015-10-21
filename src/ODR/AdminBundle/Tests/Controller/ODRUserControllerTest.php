<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 9/24/15
 * Time: 12:53 PM
 */

namespace ODR\AdminBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ODRUserControllerTest extends WebTestCase
{
    public static $client = "";
    public $token = "";

    public function testUserLogIn()
    {
        self::$client = static::createClient();
        $debug = (getenv("DEBUG") == "ODRUserController" ? true : false);
        ($debug ? fwrite(STDERR, "Testing user login.\n") : '');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        self::$client->followRedirects();
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        ($debug ? fwrite(STDERR, "Should produce a csrf token: " . $this->token . "\n") : '');
        ($debug ? fwrite(STDERR, "Should log in the user\n") : '');
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        $this->assertTrue($crawler->filter('html:contains("admin")')->count() > 0);
        ($debug ? fwrite(STDERR, "Checking Logout Present\n") : '');
        ($debug ? fwrite(STDERR, self::$client->getResponse()->getContent()) . "\n" : '');
        $this->assertTrue($crawler->filter('html:contains("Logout")')->count() > 0);
        $this->assertTrue($crawler->filter('html:contains("Sam Mosher")')->count() > 0);
    }

//    public function testCreateNewUser()
//    {
//        $i = 0;
//        $email = '';
//        $debug = (getenv("DEBUG") == "ODRUserController" ? true : false);
//        self::$client->request('GET', 'http://odr.localhost/admin/new_user/create?_=1443534826412');
//        $jsonObject = json_decode(self::$client->getResponse()->getContent());
//        $htmlString = $jsonObject->{"d"}->{"html"};
//        do {
//            $i++;
//            $email = 'dave' . $i . '@gmail.com';
//            self::$client->request('POST', 'http://odr.localhost/admin/new_user/check', array('email' => $email));
//            //echo("\n" . self::$client->getResponse()->getContent() . "\n");
//        } while (json_decode(self::$client->getResponse()->getContent())->{"d"} != 0);
//        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
//        echo("\n" . self::$client->getResponse()->getContent() . "\n");
//        $valuePos = strpos($htmlString, "value=");
//        $this->token = substr($htmlString, $valuePos + 7, 40);
//        echo($this->token);
//        self::$client->request('POST', 'http://odr.localhost/admin/new_user/save', array(
//            "ODRUserProfileForm" => array(
//                "_token" => $this->token,
//                "email" => $email,
//                "plainPassword" => array(
//                    "first" => "#3Dave77",
//                    "second" => "#3Dave77"
//                ),
//                "firstName" => "dave",
//                "lastName" => "doe",
//                "institution" => "mit",
//                "position" => "teacher",
//                "phoneNumber" => "2076532000"
//            )
//        ));
//        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
//        echo(self::$client->getResponse()->getContent() . "\n");
//        self::$client->request('GET', 'http://odr.localhost/admin/user/list');
//        $htmlString2 = json_decode(self::$client->getResponse()->getContent())->{"d"}->{"html"};
//        $this->assertTrue(strpos($htmlString2, $email) !== false);
//        self::$client->request('GET', 'http://odr.localhost/logout');
//    }

    public function testselfEditAction()
    {

        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::assertTrue($crawler->filter('html:contains("dave")')->count() > 0);
        $crawler = self::$client->request('GET', 'http://odr.localhost/profile');
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
        $html = $jsonObject->{"d"}->{"html"};
        $needle = "value=";
        $lastPos = 0;
        $positions = array();
        while (($lastPos = strpos($html, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

// Displays 3 and 10
        $id = substr($html,$positions[0]+ 7, 2);
        $this->token = substr($html, $positions[1] + 7, 40);
//        echo($id);
//        echo($this->token);

        self::$client->request('POST', 'http://odr.localhost/profile/save', array(
            "ODRUserProfileForm" => array(
                "_token" => $this->token,
                "user_id" => $id,
                "firstName" => "davee",
                "lastName" => "doe",
                "institution" => "mit",
                "position" => "teacher",
                "phoneNumber" => "2076532000"
            )
        ));
//        echo(self::$client->getResponse()->getContent());
        $crawler = self::$client->request('GET', 'http://odr.localhost/admin');
        //echo(self::$client->getResponse()->getContent());
        $this->assertTrue($crawler->filter('html:contains("davee")')->count() > 0);
        self::$client->request('GET', 'http://odr.localhost/profile');
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
        $html = $jsonObject->{"d"}->{"html"};
        $needle = "value=";
        $lastPos = 0;
        $positions = array();
        while (($lastPos = strpos($html, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

// Displays 3 and 10
        $id = substr($html,$positions[0]+ 7, 2);
        $this->token = substr($html, $positions[1] + 7, 40);
//        echo("this is the token: " . $this->token);
        self::$client->request('POST', 'http://odr.localhost/profile/save', array(
            "ODRUserProfileForm" => array(
                "_token" => $this->token,
                "user_id" => $id,
                "firstName" => "dave",
                "lastName" => "doe",
                "institution" => "mit",
                "position" => "teacher",
                "phoneNumber" => "2076532000"
            )
        ));
//        echo(self::$client->getResponse()->getContent() . "\n");
        $crawler = self::$client->request('GET', 'http://odr.localhost/admin');
        //echo(self::$client->getResponse()->getContent());
        $this->assertTrue($crawler->filter('html:contains("dave doe")')->count() > 0);
        self::$client->request('GET', 'http://odr.localhost/logout');
    }

    /**
     * User should not be able to change other users information unless User is an admin.
     */
    public function testcannotChangeDifferentUsersInfoWhenNotAdmin()
    {
        /**
         * login and making sure that the login was successful.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::assertTrue($crawler->filter('html:contains("dave")')->count() > 0);

        /**
         * Check to make sure that user is at profile page and also cant change another users informationk.
         */

        $crawler = self::$client->request('GET', 'http://odr.localhost/profile');
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
        $html = $jsonObject->{"d"}->{"html"};
        $needle = "value=";
        $lastPos = 0;
        $positions = array();
        while (($lastPos = strpos($html, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

// Displays 3 and 10
        $id = substr($html,$positions[0]+ 7, 2);
        $this->token = substr($html, $positions[1] + 7, 40);
//        echo($id);
//        echo($this->token);

        self::$client->request('POST', 'http://odr.localhost/profile/save', array(
            "ODRUserProfileForm" => array(
                "_token" => $this->token,
                "user_id" => '70',
                "firstName" => "davee",
                "lastName" => "doe",
                "institution" => "mit",
                "position" => "teacher",
                "phoneNumber" => "2076532000"
            )
        ));
//        echo(self::$client->getResponse()->getContent() . "\n");
        self::assertTrue(strpos(json_decode(self::$client->getResponse()->getContent())->{"d"}, 'Error') !== false);
        self::$client->request('GET', 'http://odr.localhost/logout');
    }
    public function testadminCanChangeUserInfo()
    {
        /**
         * logging in as an admin
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        self::$client->followRedirects();
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        $this->assertTrue($crawler->filter('html:contains("Sam")')->count() > 0);

        /**
         * Admin should be able to change a users profile information
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/edit/56');
//        echo(self::$client->getResponse()->getContent());
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
        $html = $jsonObject->{"d"}->{"html"};
        $needle = "value=";
        $lastPos = 0;
        $positions = array();
        while (($lastPos = strpos($html, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

// Displays 3 and 10
        $id = substr($html,$positions[0]+ 7, 2);
        $this->token = substr($html, $positions[1] + 7, 40);
        //echo($id);
        //echo($this->token . "\n");

        self::$client->request('POST', 'http://odr.localhost/admin/user/save', array(
            "ODRUserProfileForm" => array(
                "_token" => $this->token,
                //"id" => '56',
                "user_id" => '56',
                "firstName" => "davee3",
                "lastName" => "doe",
                "institution" => "mit",
                "position" => "teacher",
                "phoneNumber" => "2076532000"
            )
        ));

        /**
         * Check to see if the change succeeded.Should be true, currently does not.
         * Currently getting an error where it requires both user_id and id, but when both are
         * added it produces an error saying you shouldnt modify the form.
         */
        //echo(self::$client->getResponse()->getContent() . "\n");
        self::$client->request('GET', 'http://odr.localhost/admin/user/list');
        self::assertTrue(strpos(json_decode(self::$client->getResponse()->getContent())->{"d"}->{"html"}, 'davee3') !== false);
        self::$client->request('GET', 'http://odr.localhost/logout');

    }

    public function testchangePasswordAction()
    {
        /**
         * login and making sure that the login was successful.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());

        /**
         * Get the ID and token.
         */

        self::$client->request('GET', 'http://odr.localhost/admin/user/edit/56?_=1444919796300');
        self::$client->request('GET', 'http://odr.localhost/admin/user/change_password/56?_=1444847464934');
        //echo(self::$client->getResponse()->getContent() . "\n");
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
//        echo("blaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaahhhhhhhhhhhhhhhhhhhhhhhhhhhh " . $jsonObject->{"d"}->{"html"} . " This is the decoded json object\n");
        $html = $jsonObject->{"d"}->{"html"};
        $needle = "value=";
        $lastPos = 0;
        $positions = array();
        while (($lastPos = strpos($html, $needle, $lastPos))!== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }

        $id = substr($html,$positions[0]+ 7, 2);
        $this->token = substr($html, $positions[2] + 7, 40);
//        echo($id);
//        echo($this->token . "\n");

        /**
         * Change to the correct page to change a users password.
         */
        self::$client->request('POST', 'http://odr.localhost/admin/user/save',array(
            "ODRUserProfileForm" => array(
                "id" => "42",
                "user_id" => "56"
            )
        ),
        array(
            'ODRAdminChangePasswordForm' => array(
                "_token" => $this->token,
//                "id" => '56',
                "user_id" => '56',
                "plainPassword" => array(
                    "first" => '@2Dave22',
                    "second" => '@2Dave22'
                )
            )
        ));
//        echo(self::$client->getResponse()->getContent());

        //echo(self::$client->getResponse()->getContent());
        /**
         * weird error not sure why its acting the way it is.
         */
        /**
         *logout so the next test starts clean.
         */
        self::$client->request('GET', 'http://odr.localhost/logout');
    }

    /**
     * Test to make sure the list action is correctly working.
     */
    public function testlistAction()
    {
        /**
         * Login to keep the tests consistent throughout.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());

        /**
         * Navigate to the correct page and then check to see if there are users listed.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/list');
        $jsonObject = json_decode(self::$client->getResponse()->getContent());
        $html = $jsonObject->{"d"}->{"html"};
//        echo($html);
        preg_match("/<td>(\d+)/", $html, $matches_out);

        self::assertTrue($matches_out[0] !== null);
    }

    /**
     * Tests the functionality making sure that only admins can change the users information.
     */
    public function testmanagerolesAction()
    {
        /**
         * Login
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());

        /**
         * navigate to correct page.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/manage/roles?_=1444943105302');

        /**
         * change the users role, check it, then change it back.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/setrole/7/sadmin?_=1444943131099');
        self::assertTrue((json_decode(self::$client->getResponse()->getContent())->{"d"})=="");
        self::$client->request('GET', 'http://odr.localhost/admin/user/setrole/7/admin?_=1444943131099');
        self::$client->request('GET', 'http://odr.localhost/logout');

        /**
         * Login on non admin user.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());

        /**
         * try to access the page to manage roles. 403 should be the code to make sure that it is not allowing a user without the privlage to modify the roles.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/manage/roles?_=1444943105302');
        self::$client->request('GET', 'http://odr.localhost/admin/user/setrole/7/sadmin?_=1444943131099');
        self::assertEquals(self::$client->getResponse()->getStatusCode(), 403);

        self::$client->request('GET', 'http://odr.localhost/logout');
    }

    /**
     * View and change the permissions of a user then change them back.
     */
    public function testmanagepermissionsAction()
    {
        /**
         * Login
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());

        /**
         * change to the proper page and modify the content.
         * then proceed to check to make sure that the content is correct.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/togglepermission/56/3/1/view?_=1445028392762');
        echo(self::$client->getResponse()->getContent());
        self::$client->request('GET', 'http://odr.localhost/logout');

        /**
         * login with other user to see if the change is working properly.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/rruff_ref');
        $html = self::$client->getResponse()->getContent();
        echo("BLLLLLLLLLLLLLAAAAAAAAAAAAAAAAAAAAAAHHHHHHHHHH" . self::$client->getResponse()->getContent() . "BLLLLLLLLLLLLLLLLLLLLLLLAAAAAAAAAAAAAAAAAAAHHHHHHHHHH\n");
        self::assertTrue(strpos($html,'RRUFF Reference') !== false);
        self::$client->request('GET', 'http://odr.localhost/logout');

        /**
         * change privileges back and check to see if user can see the same thing.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/admin/user/togglepermission/56/3/0/view?_=1445028392762');
        self::$client->request('GET', 'http://odr.localhost/logout');

        /**
         * login with the user to make sure that the user no longer has the privilege to view rruff.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/rruff');
        $html = self::$client->getResponse()->getContent();
        self::assertTrue(strpos($html,'RRUFF Reference') == false);
        self::$client->request('GET', 'http://odr.localhost/logout');
    }

    public function testQuickPermissionsAction()
    {
        /**
         * Login to an admin user.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));

        /**
         * send request to the site to change the permissions.
         */
        self::$client->request('GET', 'http://odr.localhost/admin/user/togglequickpermission/0179/1/design?_=1445285194219');
        self::$client->request('GET', 'http://odr.locahost/logout');


        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/admin/type/list/records');
//        echo(self::$client->getResponse()->getContent());
        $html = json_decode(self::$client->getResponse()->getContent())->{"d"}->{"html"};
        self::assertTrue(strpos($html, 'TestDataType') !== false);


        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/admin/user/togglequickpermission/0179/0/view?_=1445286768724');
//        echo(self::$client->getResponse()->getContent());
        self::$client->request('GET', 'http://odr.localhost/logout');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/admin/type/list/records');
        $html2 = json_decode(self::$client->getResponse()->getContent())->{"d"}->{"html"};
//        echo($html2);
        self::assertTrue(strpos($html2, 'TestDataType') === false);
    }

    /**
     * Test to make sure that the super admin can delete and undelete a user.
     */
    public function testDeleteAction()
    {
        /**
         * Login to an admin user.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));

        self::$client->request('GET', 'http://odr.localhost/admin/user/delete/56?_=1445362212355');
        self::$client->request('GET', 'http://odr.localhost/logout');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());
        $this->assertTrue($crawler->filter('html:contains("disabled")')->count() > 0);
        self::$client->request('GET', 'http://odr.localhost/logout');

    }
    public function testUndeleteAction()
    {
        /**
         * Login to an admin user.
         */
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        self::$client->request('POST', '/login_check', array('_username' => 'sam@stoneumbrella.com', '_password' => '$4samS7&', '_csrf_token' => $this->token));
        self::$client->request('GET', 'http://odr.localhost/admin/user/undelete/56?_=1445362212355');
        self::$client->request('GET', 'http://odr.localhost/logout');
        $crawler = self::$client->request('GET', 'http://odr.localhost/login');
        $buttonCrawlerNode = $crawler->selectButton('_submit');
        $form = $buttonCrawlerNode->form();
        $this->token = $form->get('_csrf_token')->getValue();
        $crawler = self::$client->request('POST', '/login_check', array('_username' => 'dave1@gmail.com', '_password' => '@2Dave77', '_csrf_token' => $this->token));
//        echo(self::$client->getResponse()->getContent());
        $this->assertTrue($crawler->filter('html:contains("Logout")')->count() > 0);
        self::$client->request('GET', 'http://odr.localhost/logout');


    }

}
