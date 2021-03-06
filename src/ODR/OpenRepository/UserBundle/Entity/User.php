<?php

/**
* Open Data Repository Data Publisher
* User Entity (override)
* (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
* (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
* Released under the GPLv2
*
* Extends the default FOS User Entity to add some additional data,
* and adds a password validation function.
*/


namespace ODR\OpenRepository\UserBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    public function __construct()
    {
        parent::__construct();
        // your own logic
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set email
     * Also ensure username is identical to email field.
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->username = $email;
        $this->email = $email;

        return parent::setEmail($email);
    }

    /**
     * Set emailCanonical
     * Also ensure username is identical to email field.
     *
     * @param string $emailCanonical
     * @return User
     */
    public function setEmailCanonical($emailCanonical)
    {
        $this->usernameCanonical = $emailCanonical;
        $this->emailCanonical = $emailCanonical;

        return parent::setEmailCanonical($emailCanonical);
    }

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $lastName;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $institution;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $position;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $phoneNumber;


    /**
     * Set firstName
     *
     * @param string $firstName
     * @return User
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * Get firstName
     *
     * @return string 
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Set lastName
     *
     * @param string $lastName
     * @return User
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Get lastName
     *
     * @return string 
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Get userString
     *
     * @return string
     */
    public function getUserString()
    {
        if ($this->firstName == null || $this->firstName == '')
            return $this->email;
        else
            return $this->firstName.' '.$this->lastName;
    }

    /**
     * Set institution
     *
     * @param string $institution
     * @return User
     */
    public function setInstitution($institution)
    {
        $this->institution = $institution;

        return $this;
    }

    /**
     * Get institution
     *
     * @return string 
     */
    public function getInstitution()
    {
        return $this->institution;
    }

    /**
     * Set position
     *
     * @param string $position
     * @return User
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return string 
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set phoneNumber
     *
     * @param string $phoneNumber
     * @return User
     */
    public function setPhoneNumber($phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Get phoneNumber
     *
     * @return string 
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }


    /**
     * Custom callback to validate the plainPassword
     * @see http://symfony.com/doc/2.3/reference/constraints/Callback.html
     *
     * changes made to password rules should also be made in these files:
     *  ODR\OpenRepository\UserBundle\Resources\views\ChangePassword\changePassword_content.html.twig
     *  ODR\OpenRepository\UserBundle\Resources\views\Resetting\reset_content.html.twig
     *  ODR\OpenRepository\UserBundle\Entity\User.php
     *  ODR\AdminBundle\Resources\views\ODRUser\change_password.html.twig
     */
    public function isPasswordValid(ExecutionContextInterface $context)
    {
        // Prevent profile edit form from complaining about bad password when nothing was actually submitted as a password
        if ($this->plainPassword == null)
            return;

        if ( preg_match('/[a-z]/', $this->plainPassword) !== 1 ) {
            $context->addViolationAt(
                'plainPassword',
                'Password must contain at least one lowercase letter'
            );
        }
        if ( preg_match('/[A-Z]/', $this->plainPassword) !== 1 ) {
            $context->addViolationAt(
                'plainPassword',
                'Password must contain at least one uppercase letter'
            );
        }
        if ( preg_match('/[0-9]/', $this->plainPassword) !== 1 ) {
            $context->addViolationAt(
                'plainPassword',
                'Password must contain at least one numerical character'
            );
        }
        if ( preg_match('/[\`\~\!\@\#\$\%\^\&\*\(\)\-\_\=\+\[\{\]\}\\\|\;\:\'\"\,\<\.\>\/\?]/', $this->plainPassword) !== 1 ) {
            $context->addViolationAt(
                'plainPassword',
                'Password must contain at least one symbol'
            );
        }
        if ( strlen($this->plainPassword) < 8 ) {
            $context->addViolationAt(
                'plainPassword',
                'Password must be at least 8 characters long'
            );
        }
    }
}
