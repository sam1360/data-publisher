<?php

/**
 * Open Data Repository Data Publisher
 * Layout Entity
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * The Layout Entity is automatically generated from
 * ./Resources/config/doctrine/Layout.orm.yml
 *
 */

namespace ODR\AdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Layout
 */
class Layout
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var boolean
     */
    private $isBaseLayout;

    /**
     * @var boolean
     */
    private $isTableLayout;

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
     * @var \Doctrine\Common\Collections\Collection
     */
    private $layoutMeta;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $layoutData;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $userLayoutPreferences;

    /**
     * @var \ODR\AdminBundle\Entity\DataType
     */
    private $dataType;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $createdBy;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $updatedBy;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $deletedBy;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->layoutMeta = new \Doctrine\Common\Collections\ArrayCollection();
        $this->layoutData = new \Doctrine\Common\Collections\ArrayCollection();
        $this->userLayoutPreferences = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set isBaseLayout
     *
     * @param boolean $isBaseLayout
     * @return Layout
     */
    public function setIsBaseLayout($isBaseLayout)
    {
        $this->isBaseLayout = $isBaseLayout;

        return $this;
    }

    /**
     * Get isBaseLayout
     *
     * @return boolean
     */
    public function getIsBaseLayout()
    {
        return $this->isBaseLayout;
    }

    /**
     * Set isTableLayout
     *
     * @param boolean $isTableLayout
     * @return Layout
     */
    public function setIsTableLayout($isTableLayout)
    {
        $this->isTableLayout = $isTableLayout;

        return $this;
    }

    /**
     * Get isTableLayout
     *
     * @return boolean
     */
    public function getIsTableLayout()
    {
        return $this->isTableLayout;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Layout
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
     * @return Layout
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
     * @return Layout
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
     * Add layoutMeta
     *
     * @param \ODR\AdminBundle\Entity\LayoutMeta $layoutMeta
     * @return Layout
     */
    public function addLayoutMetum(\ODR\AdminBundle\Entity\LayoutMeta $layoutMeta)
    {
        $this->layoutMeta[] = $layoutMeta;

        return $this;
    }

    /**
     * Remove layoutMeta
     *
     * @param \ODR\AdminBundle\Entity\LayoutMeta $layoutMeta
     */
    public function removeLayoutMetum(\ODR\AdminBundle\Entity\LayoutMeta $layoutMeta)
    {
        $this->layoutMeta->removeElement($layoutMeta);
    }

    /**
     * Get layoutMeta
     *
     * @return \ODR\AdminBundle\Entity\LayoutMeta
     */
    public function getLayoutMeta()
    {
        return $this->layoutMeta->first();
    }

    /**
     * Add layoutData
     *
     * @param \ODR\AdminBundle\Entity\LayoutData $layoutData
     * @return Layout
     */
    public function addLayoutDatum(\ODR\AdminBundle\Entity\LayoutData $layoutData)
    {
        $this->layoutData[] = $layoutData;

        return $this;
    }

    /**
     * Remove layoutData
     *
     * @param \ODR\AdminBundle\Entity\LayoutData $layoutData
     */
    public function removeLayoutDatum(\ODR\AdminBundle\Entity\LayoutData $layoutData)
    {
        $this->layoutData->removeElement($layoutData);
    }

    /**
     * Get layoutData
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getLayoutData()
    {
        return $this->layoutData;
    }

    /**
     * Add userLayoutPreference
     *
     * @param \ODR\AdminBundle\Entity\UserLayoutPreferences $userLayoutPreference
     *
     * @return Layout
     */
    public function addUserLayoutPreference(\ODR\AdminBundle\Entity\UserLayoutPreferences $userLayoutPreference)
    {
        $this->userLayoutPreferences[] = $userLayoutPreference;

        return $this;
    }

    /**
     * Remove userLayoutPreference
     *
     * @param \ODR\AdminBundle\Entity\UserLayoutPreferences $userLayoutPreference
     */
    public function removeUserLayoutPreference(\ODR\AdminBundle\Entity\UserLayoutPreferences $userLayoutPreference)
    {
        $this->userLayoutPreferences->removeElement($userLayoutPreference);
    }

    /**
     * Get userLayoutPreferences
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUserLayoutPreferences()
    {
        return $this->userLayoutPreferences;
    }

    /**
     * Set dataType
     *
     * @param \ODR\AdminBundle\Entity\DataType $dataType
     * @return Layout
     */
    public function setDataType(\ODR\AdminBundle\Entity\DataType $dataType = null)
    {
        $this->dataType = $dataType;

        return $this;
    }

    /**
     * Get dataType
     *
     * @return \ODR\AdminBundle\Entity\DataType 
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * Set createdBy
     *
     * @param \ODR\OpenRepository\UserBundle\Entity\User $createdBy
     * @return Layout
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
     * @return Layout
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

    /**
     * Set deletedBy
     *
     * @param \ODR\OpenRepository\UserBundle\Entity\User $deletedBy
     * @return Layout
     */
    public function setDeletedBy(\ODR\OpenRepository\UserBundle\Entity\User $deletedBy = null)
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    /**
     * Get deletedBy
     *
     * @return \ODR\OpenRepository\UserBundle\Entity\User 
     */
    public function getDeletedBy()
    {
        return $this->deletedBy;
    }

    /**
     * Is public
     *
     * @return boolean
     */
    public function isPublic()
    {
        if ($this->getPublicDate()->format('Y-m-d H:i:s') == '2200-01-01 00:00:00')
            return false;
        else
            return true;
    }


    /**
     * Get LayoutName
     *
     * @return string
     */
    public function getLayoutName()
    {
        return $this->getLayoutMeta()->getLayoutName();
    }

    /**
     * Get LayoutDescription
     *
     * @return string
     */
    public function getLayoutDescription()
    {
        return $this->getLayoutMeta()->getLayoutDescription();
    }

    /**
     * Get publicDate
     *
     * @return \DateTime
     */
    public function getPublicDate()
    {
        return $this->getLayoutMeta()->getPublicDate();
    }

    /**
     * Get isOfficial
     * 
     * @return bool
     */
    public function getIsOfficial()
    {
        return $this->getLayoutMeta()->getIsOfficial();
    }

    /**
     * Get isSearchDefault
     *
     * @return bool
     */
    public function getIsSearchDefault()
    {
        return $this->getLayoutMeta()->getIsSearchDefault();
    }

    /**
     * Get isViewDefault
     *
     * @return bool
     */
    public function getIsViewDefault()
    {
        return $this->getLayoutMeta()->getIsViewDefault();
    }

    /**
     * Get isEditDefault
     *
     * @return bool
     */
    public function getIsEditDefault()
    {
        return $this->getLayoutMeta()->getIsEditDefault();
    }
}
