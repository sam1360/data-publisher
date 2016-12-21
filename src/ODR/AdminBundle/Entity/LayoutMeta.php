<?php

/**
 * Open Data Repository Data Publisher
 * LayoutMeta Entity
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * The LayoutMeta Entity is responsible for storing the properties
 * of the Layout Entity that are subject to change, and is
 * automatically generated from ./Resources/config/doctrine/LayoutMeta.orm.yml
 *
 */

namespace ODR\AdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LayoutMeta
 */
class LayoutMeta
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $layoutName;

    /**
     * @var string
     */
    private $layoutDescription;

    /**
     * @var boolean
     */
    private $isOfficial;

    /**
     * @var boolean
     */
    private $isSearchDefault;

    /**
     * @var boolean
     */
    private $isViewDefault;

    /**
     * @var boolean
     */
    private $isEditDefault;

    /**
     * @var boolean
     */
    private $hasSearchIntent;

    /**
     * @var \DateTime
     */
    private $publicDate;

    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $updated;

    /**
     * @var \DateTime
     */
    private $deletedAt;

    /**
     * @var \ODR\AdminBundle\Entity\Layout
     */
    private $layout;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $createdBy;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $updatedBy;


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
     * Set layoutName
     *
     * @param string $layoutName
     * @return LayoutMeta
     */
    public function setLayoutName($layoutName)
    {
        $this->layoutName = $layoutName;

        return $this;
    }

    /**
     * Get layoutName
     *
     * @return string 
     */
    public function getLayoutName()
    {
        return $this->layoutName;
    }

    /**
     * Set layoutDescription
     *
     * @param string $layoutDescription
     * @return LayoutMeta
     */
    public function setLayoutDescription($layoutDescription)
    {
        $this->layoutDescription = $layoutDescription;

        return $this;
    }

    /**
     * Get layoutDescription
     *
     * @return string 
     */
    public function getLayoutDescription()
    {
        return $this->layoutDescription;
    }

    /**
     * Set isOfficial
     *
     * @param boolean $isOfficial
     * @return LayoutMeta
     */
    public function setIsOfficial($isOfficial)
    {
        $this->isOfficial = $isOfficial;

        return $this;
    }

    /**
     * Get isOfficial
     *
     * @return boolean
     */
    public function getIsOfficial()
    {
        return $this->isOfficial;
    }

    /**
     * Set isSearchDefault
     *
     * @param boolean $isSearchDefault
     * @return LayoutMeta
     */
    public function setIsSearchDefault($isSearchDefault)
    {
        $this->isSearchDefault = $isSearchDefault;

        return $this;
    }

    /**
     * Get isSearchDefault
     *
     * @return boolean
     */
    public function getIsSearchDefault()
    {
        return $this->isSearchDefault;
    }

    /**
     * Set isViewDefault
     *
     * @param boolean $isViewDefault
     * @return LayoutMeta
     */
    public function setIsViewDefault($isViewDefault)
    {
        $this->isViewDefault = $isViewDefault;

        return $this;
    }

    /**
     * Get isViewDefault
     *
     * @return boolean
     */
    public function getIsViewDefault()
    {
        return $this->isViewDefault;
    }

    /**
     * Set isEditDefault
     *
     * @param boolean $isEditDefault
     * @return LayoutMeta
     */
    public function setIsEditDefault($isEditDefault)
    {
        $this->isEditDefault = $isEditDefault;

        return $this;
    }

    /**
     * Get isEditDefault
     *
     * @return boolean
     */
    public function getIsEditDefault()
    {
        return $this->isEditDefault;
    }

    /**
     * Set hasSearchIntent
     *
     * @param boolean $hasSearchIntent
     * @return LayoutMeta
     */
    public function setHasSearchIntent($hasSearchIntent)
    {
        $this->hasSearchIntent = $hasSearchIntent;

        return $this;
    }

    /**
     * Get hasSearchIntent
     *
     * @return boolean
     */
    public function getHasSearchIntent()
    {
        return $this->hasSearchIntent;
    }

    /**
     * Set publicDate
     *
     * @param \DateTime $publicDate
     * @return LayoutMeta
     */
    public function setPublicDate($publicDate)
    {
        $this->publicDate = $publicDate;

        return $this;
    }

    /**
     * Get publicDate
     *
     * @return \DateTime
     */
    public function getPublicDate()
    {
        return $this->publicDate;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return LayoutMeta
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set updated
     *
     * @param \DateTime $updated
     * @return LayoutMeta
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get updated
     *
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Set deletedAt
     *
     * @param \DateTime $deletedAt
     * @return LayoutMeta
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Get deletedAt
     *
     * @return \DateTime 
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Set layout
     *
     * @param \ODR\AdminBundle\Entity\Layout $layout
     * @return LayoutMeta
     */
    public function setLayout(\ODR\AdminBundle\Entity\Layout $layout = null)
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * Get layout
     *
     * @return \ODR\AdminBundle\Entity\Layout 
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * Set createdBy
     *
     * @param \ODR\OpenRepository\UserBundle\Entity\User $createdBy
     * @return LayoutMeta
     */
    public function setCreatedBy(\ODR\OpenRepository\UserBundle\Entity\User $createdBy = null)
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Get createdBy
     *
     * @return \ODR\OpenRepository\UserBundle\Entity\User 
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Set updatedBy
     *
     * @param \ODR\OpenRepository\UserBundle\Entity\User $updatedBy
     * @return LayoutMeta
     */
    public function setUpdatedBy(\ODR\OpenRepository\UserBundle\Entity\User $updatedBy = null)
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * Get updatedBy
     *
     * @return \ODR\OpenRepository\UserBundle\Entity\User 
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }
}
