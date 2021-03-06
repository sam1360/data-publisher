<?php

/**
* Open Data Repository Data Publisher
* Radio Entity
* (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
* (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
* Released under the GPLv2
*
* This file exists for compatability purposes, apparently?
* ODRCustomController::buildForm() requires it, at least...
*
*/


namespace ODR\AdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ODR\AdminBundle\Entity\Radio
 */
class Radio
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var boolean $value
     */
    private $value;

    /**
     * @var ODR\AdminBundle\Entity\DataFields
     */
    private $dataField;

    /**
     * @var ODR\AdminBundle\Entity\FieldType
     */
    private $fieldType;

    /**
     * @var ODR\AdminBundle\Entity\DataRecord
     */
    private $dataRecord;


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
     * Set value
     *
     * @param boolean $value
     * @return Radio
     */
    public function setValue($value)
    {
        $this->value = $value;
    
        return $this;
    }

    /**
     * Get value
     *
     * @return boolean 
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set dataField
     *
     * @param ODR\AdminBundle\Entity\DataFields $dataField
     * @return Radio
     */
    public function setDataField(\ODR\AdminBundle\Entity\DataFields $dataField = null)
    {
        $this->dataField = $dataField;
    
        return $this;
    }

    /**
     * Get dataField
     *
     * @return ODR\AdminBundle\Entity\DataFields 
     */
    public function getDataField()
    {
        return $this->dataField;
    }

    /**
     * Set fieldType
     *
     * @param ODR\AdminBundle\Entity\FieldType $fieldType
     * @return Radio
     */
    public function setFieldType(\ODR\AdminBundle\Entity\FieldType $fieldType = null)
    {
        $this->fieldType = $fieldType;
    
        return $this;
    }

    /**
     * Get fieldType
     *
     * @return ODR\AdminBundle\Entity\FieldType 
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * Set dataRecord
     *
     * @param ODR\AdminBundle\Entity\DataRecord $dataRecord
     * @return Radio
     */
    public function setDataRecord(\ODR\AdminBundle\Entity\DataRecord $dataRecord = null)
    {
        $this->dataRecord = $dataRecord;
    
        return $this;
    }

    /**
     * Get dataRecord
     *
     * @return ODR\AdminBundle\Entity\DataRecord 
     */
    public function getDataRecord()
    {
        return $this->dataRecord;
    }
    /**
     * @var \DateTime
     */
    private $deletedAt;

    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $updated;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $createdBy;

    /**
     * @var \ODR\OpenRepository\UserBundle\Entity\User
     */
    private $updatedBy;


    /**
     * Set deletedAt
     *
     * @param \DateTime $deletedAt
     * @return Radio
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
     * Set created
     *
     * @param \DateTime $created
     * @return Radio
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
     * @return Radio
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
     * Set createdBy
     *
     * @param \ODR\OpenRepository\UserBundle\Entity\User $createdBy
     * @return Radio
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
     * @return Radio
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
     * @var \ODR\AdminBundle\Entity\DataRecordFields
     */
    private $dataRecordFields;


    /**
     * Set dataRecordFields
     *
     * @param \ODR\AdminBundle\Entity\DataRecordFields $dataRecordFields
     * @return Radio
     */
    public function setDataRecordFields(\ODR\AdminBundle\Entity\DataRecordFields $dataRecordFields = null)
    {
        $this->dataRecordFields = $dataRecordFields;
    
        return $this;
    }

    /**
     * Get dataRecordFields
     *
     * @return \ODR\AdminBundle\Entity\DataRecordFields 
     */
    public function getDataRecordFields()
    {
        return $this->dataRecordFields;
    }
}
