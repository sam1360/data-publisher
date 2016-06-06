<?php

/**
* Open Data Repository Data Publisher
* Reports Controller
* (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
* (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
* Released under the GPLv2
*
* The reports controller is meant to be used as a storage spot
* for handling various metadata requests, such as determining which
* datarecords have duplicated values in a specific datafield, or which
* datarecords have multiple files/images uploaded to a specific datafield.
*
*/

namespace ODR\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

// Entities
use ODR\AdminBundle\Entity\DataFields;
use ODR\AdminBundle\Entity\DataTree;
use ODR\AdminBundle\Entity\DataType;
use ODR\AdminBundle\Entity\File;
use ODR\OpenRepository\UserBundle\Entity\User;
// Forms
// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ReportsController extends ODRCustomController
{

    /**
     * Recursively locates and loads all Datatype entities that have the Datatype pointed to by $parent_datatype_id as an ancestor
     *
     * @param \Doctrine\ORM\Entitymanager $em
     * @param array $datatree_array            @see ODRCustomController::getDatatreeArray()
     * @param integer $parent_datatype_id
     *
     * @return array
     */
    private function getAllDatatypes($em, $datatree_array, $parent_datatype_id)
    {
        $repo_datatype = $em->getRepository('ODRAdminBundle:DataType');
        $datatypes = array();

        $tmp = array_keys($datatree_array['descendant_of'], $parent_datatype_id);
        foreach ($tmp as $num => $child_datatype_id) {
            $datatypes[$child_datatype_id] = array('datatype' => $repo_datatype->find($child_datatype_id), 'children' => array() );

            $datatypes[$child_datatype_id]['children'] = self::getAllDatatypes($em, $datatree_array, $child_datatype_id);
        }

        return $datatypes;
    }


    /**
     * Generates a list of all permissions for all users for the specified datatype
     *
     * @param string $datatype_id
     * @param Request $request
     *
     * @return Response
     */
    public function datatypepermissionslistAction($datatype_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $repo_user = $em->getRepository('ODROpenRepositoryUserBundle:User');

            $datatype = $em->getRepository('ODRAdminBundle:DataType')->find($datatype_id);
            if ($datatype == null)
                return parent::deletedEntityError('Datatype');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[$datatype_id]) && isset($user_permissions[$datatype_id]['admin'])) )
                return parent::permissionDeniedError();
            // --------------------


            // ----------------------------------------
            // Ensure this isn't called on a child datatype
            $datatree_array = parent::getDatatreeArray($em);
            if ( isset($datatree_array['descendant_of'][$datatype_id]) && $datatree_array['descendant_of'][$datatype_id] !== '' )
                throw new \Exception('This action is only permitted on top-level datatypes.');


            // Recursively locate all childtypes of this datatype
            $all_datatypes = array($datatype_id => array('datatype' => $datatype, 'children' => array()));
            $all_datatypes[$datatype_id]['children'] = self::getAllDatatypes($em, $datatree_array, $datatype_id);

            // Easier to look through $datatree_array directly to find all relevant datatype ids...
            $all_datatype_ids = array($datatype_id);
            $parent_datatype_ids = array($datatype_id);
            while (count($parent_datatype_ids) > 0) {

                $tmp_datatype_ids = array();
                foreach ($parent_datatype_ids as $num => $dt_id) {
                    $tmp = array_keys($datatree_array['descendant_of'], $dt_id);

                    foreach ($tmp as $child_datatype_id) {
                        $tmp_datatype_ids[] = $child_datatype_id;
                        $all_datatype_ids[] = $child_datatype_id;
                    }
                }

                $parent_datatype_ids = $tmp_datatype_ids;
            }

            // Locate all users that can access this datatype
            $query = $em->createQuery(
               'SELECT DISTINCT(u.id)
                FROM ODRAdminBundle:UserPermissions AS up
                JOIN ODROpenRepositoryUserBundle:User AS u WITH up.user = u
                WHERE up.dataType IN (:datatypes)
                AND (up.can_view_type = 1 OR up.can_edit_record = 1 OR up.can_add_record = 1 OR up.can_delete_record = 1 OR up.can_design_type = 1 OR up.is_type_admin = 1)'
            )->setParameters(array('datatypes' => $all_datatype_ids));
            $results = $query->getArrayResult();

            // Store all relevant permissions in an array for twig...
            $all_permissions = array();
            foreach ($results as $num => $data) {
                $user_id = $data[1];

                if (!isset($all_permissions[$user_id])) {
                    /** @var User $site_user */
                    $site_user = $repo_user->find($user_id);
                    if ($site_user->isEnabled() == 1 && !$site_user->hasRole('ROLE_SUPER_ADMIN'))   // only display this user's permissions for this datatype if they're not deleted and aren't super admin
                        $all_permissions[$user_id] = array('user' => $site_user, 'permissions' => parent::getPermissionsArray($user_id, $request));
                }
            }
            ksort($all_permissions);


            // Render and return the report
            $templating = $this->get('templating');
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:datatype_permissions_list.html.twig',
                    array(
                        'all_datatypes' => $all_datatypes,
                        'all_permissions' => $all_permissions,
                    )
                )
            );

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x23267635 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Given a Datafield, build a list of Datarecords (if any) that have identical values in that Datafield.
     *
     * @param integer $datafield_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzedatafielduniqueAction($datafield_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var DataFields $datafield */
            $datafield = $em->getRepository('ODRAdminBundle:DataFields')->find($datafield_id);
            if ( $datafield == null )
                return parent::deletedEntityError('DataField');

            $datatype = $datafield->getDataType();
            if ( $datatype == null )
                return parent::deletedEntityError('DataType');
            $datatype_id = $datatype->getId();


            // Only run queries if field can be set to unique
            $fieldtype = $datafield->getFieldType();
            if ($fieldtype->getCanBeUnique() == '0')
                throw new \Exception("This DataField can't be unique becase of its FieldType");

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype_id ]) && isset($user_permissions[ $datatype_id ][ 'design' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------

            // Datafields in child datatypes have different rules for duplicated values...
            $is_child_datatype = false;
            $datatree_array = parent::getDatatreeArray($em);
            if ( isset($datatree_array['descendant_of'][$datatype_id]) && $datatree_array['descendant_of'][$datatype_id] !== '' )
                $is_child_datatype = true;

            // Procedure for locating duplicate values in top-level datatypes differs just enough from the one for locating duplicate values in child datatypes...
            if (!$is_child_datatype) {
                // Build the report
                $return['d'] = array(
                    'html' => self::buildDatafieldUniquenessReport($em, $datafield)
                );
            }
            else {
                // Locate the top-level datatype
                $top_level_datatype_id = parent::getGrandparentDatatypeId($datatree_array, $datatype_id);

                /** @var DataType $top_level_datatype */
                $top_level_datatype = $em->getRepository('ODRAdminBundle:DataType')->find($top_level_datatype_id);

                // Build the report
                $return['d'] = array(
                    'html' => self::buildChildDatafieldUniquenessReport($em, $datafield, $top_level_datatype)
                );
            }

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x892357656 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Builds an array of duplicated values for a datafield belonging to a top-level datatype.
     * In this version, duplicate values are not allowed in this datafield.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param Datafields $datafield
     *
     * @return string
     */
    private function buildDatafieldUniquenessReport($em, $datafield)
    {
        // Get necessary objects
        $templating = $this->get('templating');
        $fieldtype = $datafield->getFieldType();
        $datafield_id = $datafield->getId();

        // Build a query to determine which top-level datarecords have duplicate values
        // TODO - modify to use datatype's namefield instead of datarecord id...
        $typeclass = $fieldtype->getTypeClass();
        $query = $em->createQuery(
           'SELECT dr.id AS dr_id, e.value AS value
            FROM ODRAdminBundle:'.$typeclass.' AS e
            JOIN ODRAdminBundle:DataRecordFields AS drf WITH e.dataRecordFields = drf
            JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
            WHERE e.dataField = :datafield
            AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL'
        )->setParameters( array('datafield' => $datafield_id) );
        $results = $query->getArrayResult();

        // Convert the query results into an array grouped by value
        $values = array();
        foreach ($results as $num => $result) {
            $dr_id = $result['dr_id'];
            $value = $result['value'];

            if ( !isset($values[$value]) ) {
                $values[$value] = array('count' => 1, 'dr_list' => array($dr_id));
            }
            else {
                $values[$value]['count'] += 1;
                $values[$value]['dr_list'][] = $dr_id;
            }
        }

        // Don't care about values which aren't duplicated
        foreach ($values as $value => $data) {
            if ( $data['count'] == 1 )
                unset($values[$value]);
        }

/*
print_r($values);
print_r($grandparent_list);
*/

        // Render and return a page detailing which datarecords have duplicate values...
        return $templating->render(
            'ODRAdminBundle:Reports:datafield_uniqueness_report.html.twig',
            array(
                'datafield' => $datafield,
//                'user_permissions' => $user_permissions,
                'duplicate_values' => $values,
            )
        );
    }


    /**
     * Builds an array of duplicated values for a datafield belonging to a child datatype.
     * In this version, duplicate values are allowed in this datafield...provided they don't occur within the same parent datarecord.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @param Datafields $datafield
     * @param Datatype $top_level_datatype
     *
     * @return string
     */
    private function buildChildDatafieldUniquenessReport($em, $datafield, $top_level_datatype)
    {
        // Get necessary objects
        $templating = $this->get('templating');
        $fieldtype = $datafield->getFieldType();
        $datafield_id = $datafield->getId();

        // Build a query to determine which child datarecords have duplicate values
        // TODO - modify to use datatype's namefield instead of datarecord id...
        $typeclass = $fieldtype->getTypeClass();
        $query = $em->createQuery(
           'SELECT dr.id AS dr_id, parent.id AS parent_id, grandparent.id AS grandparent_id, e.value AS value
            FROM ODRAdminBundle:'.$typeclass.' AS e
            JOIN ODRAdminBundle:DataRecordFields AS drf WITH e.dataRecordFields = drf
            JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
            JOIN ODRAdminBundle:DataRecord AS parent WITH dr.parent = parent
            JOIN ODRAdminBundle:DataRecord AS grandparent WITH dr.grandparent = grandparent
            WHERE e.dataField = :datafield
            AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL AND parent.deletedAt IS NULL AND grandparent.deletedAt IS NULL'
        )->setParameters( array('datafield' => $datafield_id) );
        $results = $query->getArrayResult();

        // Convert the query results into an array grouped by value
        $grandparent_list = array();
        $values = array();
        foreach ($results as $num => $result) {
            $dr_id = $result['dr_id'];
            $parent_id = $result['parent_id'];
            $grandparent_id = $result['grandparent_id'];
            $value = $result['value'];

            // Store a list of parent datarecord id => grandparent id
            $grandparent_list[$parent_id] = $grandparent_id;

            // Group values by parent datarecord id
            if ( !isset($values[$parent_id]) )
                $values[$parent_id] = array();

            if ( !isset($values[$parent_id][$value]) ) {
                $values[$parent_id][$value] = array('count' => 1, 'dr_list' => array($dr_id));
            }
            else {
                $values[$parent_id][$value]['count'] += 1;
                $values[$parent_id][$value]['dr_list'][] = $dr_id;
            }
        }

        // Don't care about values which aren't duplicated
        foreach ($values as $parent_id => $children) {
            foreach ($children as $value => $data) {
                if ( $data['count'] == 1 )
                    unset( $values[$parent_id][$value] );
            }

            if ( count($values[$parent_id]) == 0 )
                unset( $values[$parent_id] );
        }
/*
print_r($values);
print_r($grandparent_list);
*/
        // Render and return a page detailing which datarecords have duplicate values...
        return $templating->render(
            'ODRAdminBundle:Reports:child_datafield_uniqueness_report.html.twig',
            array(
                'datafield' => $datafield,
                'top_level_datatype' => $top_level_datatype,
//                'user_permissions' => $user_permissions,
                'duplicate_values' => $values,
                'grandparent_list' => $grandparent_list,
            )
        );
    }


    /**
     * Given a Datafield, build a list of Datarecords (if any) that have multiple files/images uploaded in that Datafield.
     *
     * @param integer $datafield_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzefileuploadsAction($datafield_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $templating = $this->get('templating');

            /** @var DataFields $datafield */
            $datafield = $em->getRepository('ODRAdminBundle:DataFields')->find($datafield_id);
            if ( $datafield == null )
                return parent::deletedEntityError('DataField');

            $datatype = $datafield->getDataType();
            if ( $datatype == null )
                return parent::deletedEntityError('DataType');

            // Only run on file or image fields
            $typename = $datafield->getFieldType()->getTypeName();
            if ($typename !== 'File' && $typename !== 'Image')
                throw new \Exception('This is not a File or an Image datafield');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'design' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------

            // Locate any datarecords where this datafield has multiple uploaded files
            $query = null;
            if ($typename == 'File') {
                $query = $em->createQuery(
                   'SELECT dr.id AS dr_id, grandparent.id AS grandparent_id
                    FROM ODRAdminBundle:File AS e
                    JOIN ODRAdminBundle:DataRecordFields AS drf WITH e.dataRecordFields = drf
                    JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
                    JOIN ODRAdminBundle:DataRecord AS grandparent WITH dr.grandparent = grandparent
                    WHERE e.dataField = :datafield
                    AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL AND grandparent.deletedAt IS NULL'
                )->setParameters( array('datafield' => $datafield_id) );
            }
            else if ($typename == 'Image') {
                $query = $em->createQuery(
                   'SELECT dr.id AS dr_id, grandparent.id AS grandparent_id
                    FROM ODRAdminBundle:Image AS e
                    JOIN ODRAdminBundle:DataRecordFields AS drf WITH e.dataRecordFields = drf
                    JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
                    JOIN ODRAdminBundle:DataRecord AS grandparent WITH dr.grandparent = grandparent
                    WHERE e.dataField = :datafield AND e.original = 1
                    AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL AND grandparent.deletedAt IS NULL'
                )->setParameters( array('datafield' => $datafield_id) );
            }
            $results = $query->getArrayResult();

            // Count how many files/images are uploaded for this (datarecord,datafield) pair 
            $grandparent_list = array();
            $duplicate_list = array();
            foreach ($results as $num => $result) {
                $dr_id = $result['dr_id'];
                $grandparent_id = $result['grandparent_id'];

                if ( !isset($duplicate_list[$dr_id]) )
                    $duplicate_list[$dr_id] = 0;

                $duplicate_list[$dr_id]++;

                $grandparent_list[$dr_id] = $grandparent_id;
            }

            // Only want to send a list of the grandparent ids to the twig file
            $datarecord_list = array();
            foreach ($duplicate_list as $dr_id => $count) {
                $grandparent_id = $grandparent_list[$dr_id];

                if ( $count > 1 && !in_array($grandparent_id, $datarecord_list) )
                    $datarecord_list[] = $grandparent_id;
            }

            // Render and return a page detailing which datarecords have multiple uploads...
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:multiple_file_uploads_report.html.twig',
                    array(
                        'datafield' => $datafield,
//                        'user_permissions' => $user_permissions,
                        'multiple_uploads' => $datarecord_list,
                    )
                )
            );

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x835376856 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Given a Datatree, build a list of Datarecords that have children/are linked to multiple Datarecords through this Datatree.
     *
     * @param integer $datatree_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzedatarecordnumberAction($datatree_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $templating = $this->get('templating');

            /** @var DataTree $datatree */
            $datatree = $em->getRepository('ODRAdminBundle:DataTree')->find($datatree_id);
            if ($datatree == null)
                return parent::deletedEntityError('Datatree');

            $parent_datatype_id = $datatree->getAncestor()->getId();
            $child_datatype_id = $datatree->getDescendant()->getId();

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $parent_datatype_id ]) && isset($user_permissions[ $parent_datatype_id ][ 'design' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------

            $results = array();
            if ($datatree->getIsLink() == 0) {
                // Determine whether a datarecord of this datatype has multiple child datarecords...if so, then require the "multiple allowed" property of the datatree to remain true
                $query = $em->createQuery(
                   'SELECT parent.id AS ancestor_id, child.id AS descendant_id
                    FROM ODRAdminBundle:DataRecord AS parent
                    JOIN ODRAdminBundle:DataRecord AS child WITH child.parent = parent
                    WHERE parent.dataType = :parent_datatype AND child.dataType = :child_datatype AND parent.id != child.id
                    AND parent.deletedAt IS NULL AND child.deletedAt IS NULL'
                )->setParameters( array('parent_datatype' => $parent_datatype_id, 'child_datatype' => $child_datatype_id) );
                $results = $query->getArrayResult();
            }
            else {
                // Determine whether a datarecord of this datatype is linked to multiple datarecords...if so, then require the "multiple allowed" property of the datatree to remain true
                $query = $em->createQuery(
                   'SELECT ancestor.id AS ancestor_id, descendant.id AS descendant_id
                    FROM ODRAdminBundle:DataRecord AS ancestor
                    JOIN ODRAdminBundle:LinkedDataTree AS ldt WITH ldt.ancestor = ancestor
                    JOIN ODRAdminBundle:DataRecord AS descendant WITH ldt.descendant = descendant
                    WHERE ancestor.dataType = :ancestor_datatype AND descendant.dataType = :descendant_datatype
                    AND ancestor.deletedAt IS NULL AND ldt.deletedAt IS NULL AND descendant.deletedAt IS NULL'
                )->setParameters( array('ancestor_datatype' => $parent_datatype_id, 'descendant_datatype' => $child_datatype_id) );
                $results = $query->getArrayResult();
            }

            $tmp = array();
            foreach ($results as $num => $result) {
                $ancestor_id = $result['ancestor_id'];
                if ( !isset($tmp[$ancestor_id]) )
                    $tmp[$ancestor_id] = 0;

                $tmp[$ancestor_id]++;
            }

            $datarecords = array();
            foreach ($tmp as $dr_id => $count) {
                if ($count > 1)
                    $datarecords[] = $dr_id;
            }

            // Render and return a page detailing which datarecords have multiple child/linked datarecords...
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:datarecord_number_report.html.twig',
                    array(
                        'datatree' => $datatree,
                        'datarecords' => $datarecords,
                    )
                )
            );

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x531765856 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Given a Datatype, build a list of Datarecords that have children/are linked to multiple Datarecords through this Datatree.
     *
     * @param integer $local_datatype_id
     * @param integer $remote_datatype_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzedatarecordlinksAction($local_datatype_id, $remote_datatype_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $templating = $this->get('templating');

            /** @var DataType $local_datatype */
            $local_datatype = $em->getRepository('ODRAdminBundle:DataType')->find($local_datatype_id);
            if ($local_datatype == null)
                return parent::deletedEntityError('Datatype');

            /** @var DataType $remote_datatype */
            $remote_datatype = $em->getRepository('ODRAdminBundle:DataType')->find($remote_datatype_id);
            if ($remote_datatype == null)
                return parent::deletedEntityError('Datatype');


            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $local_datatype_id ]) && isset($user_permissions[ $local_datatype_id ][ 'design' ])) )
                return parent::permissionDeniedError("edit");

            $can_edit_local = false;
            if ( isset($user_permissions[ $local_datatype_id ]) && isset($user_permissions[ $local_datatype_id ][ 'edit' ]) )
                $can_edit_local = true;

            $can_edit_remote = false;
            if ( isset($user_permissions[ $remote_datatype_id ]) && isset($user_permissions[ $remote_datatype_id ][ 'edit' ]) )
                $can_edit_remote = true;
            // --------------------


            // Locate any datarecords of the local datatype that link to datarecords of the remote datatype
            $query = $em->createQuery(
                'SELECT ancestor.id AS ancestor_id, descendant.id AS descendant_id
                    FROM ODRAdminBundle:DataRecord AS ancestor
                    JOIN ODRAdminBundle:LinkedDataTree AS ldt WITH ldt.ancestor = ancestor
                    JOIN ODRAdminBundle:DataRecord AS descendant WITH ldt.descendant = descendant
                    WHERE ancestor.dataType = :local_datatype_id AND descendant.dataType = :remote_datatype_id
                    AND ancestor.deletedAt IS NULL AND ldt.deletedAt IS NULL AND descendant.deletedAt IS NULL'
            )->setParameters( array('local_datatype_id' => $local_datatype->getId(), 'remote_datatype_id' => $remote_datatype->getId()) );
            $results = $query->getArrayResult();

            $linked_datarecords = array();
            foreach ($results as $result) {
                $ancestor_id = $result['ancestor_id'];
                $descendant_id = $result['descendant_id'];

                if ( !isset($linked_datarecords[$ancestor_id]) )
                    $linked_datarecords[$ancestor_id] = array();

                $linked_datarecords[$ancestor_id][] = $descendant_id;
            }

            // Render and return a page detailing which datarecords have multiple child/linked datarecords...
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:datarecord_links_report.html.twig',
                    array(
                        'local_datatype' => $local_datatype,
                        'remote_datatype' => $remote_datatype,
                        'linked_datarecords' => $linked_datarecords,

                        'can_edit_local' => $can_edit_local,
                        'can_edit_remote' => $can_edit_remote,
                    )
                )
            );

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x17662658 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Given a datafield, build a list of all values stored in that datafield
     *
     * @param integer $datafield_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzedatafieldcontentAction($datafield_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $templating = $this->get('templating');

            /** @var DataFields $datafield */
            $datafield = $em->getRepository('ODRAdminBundle:DataFields')->find($datafield_id);
            if ( $datafield == null )
                return parent::deletedEntityError('DataField');

            $datatype = $datafield->getDataType();
            if ( $datatype == null )
                return parent::deletedEntityError('DataType');


            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'edit' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------

            // Only run this on valid fieldtypes...
            $typeclass = $datafield->getFieldType()->getTypeClass();
            switch ($typeclass) {
                case 'ShortVarchar':
                case 'MediumVarchar':
                case 'LongVarchar':
                case 'LongText':
                case 'IntegerValue':
                case 'DecimalValue':
                    // Allowed
                    break;

                default:
                    throw new \Exception('Invalid DataField');
                    break;
            }


            // Build a query to grab all values in this datafield
            $use_external_id_field = true;
            $results = array();
            if ($datatype->getExternalIdField() == null) {
                $use_external_id_field = false;
                $query = $em->createQuery(
                   'SELECT dr.id AS dr_id, e.value AS value, "" AS external_id
                    FROM ODRAdminBundle:DataRecord AS dr
                    JOIN ODRAdminBundle:DataRecordFields AS drf WITH drf.dataRecord = dr
                    JOIN ODRAdminBundle:'.$typeclass.' AS e WITH e.dataRecordFields = drf
                    WHERE dr.dataType = :datatype AND drf.dataField = :datafield
                    AND dr.deletedAt IS NULL AND drf.deletedAt IS NULL AND e.deletedAt IS NULL
                    ORDER BY dr.id'
                )->setParameters( array('datatype' => $datatype->getId(), 'datafield' => $datafield_id) );
                $results = $query->getArrayResult();
            }
            else {
                $external_id_field_typeclass = $datatype->getExternalIdField()->getFieldType()->getTypeClass();
                $typeclass = $datafield->getFieldType()->getTypeClass();
                $query = $em->createQuery(
                   'SELECT dr.id AS dr_id, e_2.value AS value, e_1.value AS external_id
                    FROM ODRAdminBundle:'.$external_id_field_typeclass.' AS e_1
                    JOIN ODRAdminBundle:DataRecordFields AS drf_1 WITH e_1.dataRecordFields = drf_1
                    JOIN ODRAdminBundle:DataRecord AS dr WITH drf_1.dataRecord = dr
                    JOIN ODRAdminBundle:DataRecordFields AS drf_2 WITH drf_2.dataRecord = dr
                    JOIN ODRAdminBundle:'.$typeclass.' AS e_2 WITH e_2.dataRecordFields = drf_2
                    WHERE dr.dataType = :datatype AND drf_2.dataField = :datafield AND drf_1.dataField = :external_id_field
                    AND e_1.deletedAt IS NULL AND drf_1.deletedAt IS NULL AND dr.deletedAt IS NULL AND drf_2.deletedAt IS NULL AND e_2.deletedAt IS NULL
                    ORDER BY dr.id'
                )->setParameters( array('datatype' => $datatype->getId(), 'datafield' => $datafield_id, 'external_id_field' => $datatype->getExternalIdField()->getId()) );
                $results = $query->getArrayResult();
            }

            $content = array();
            foreach ($results as $num => $result) {
                $dr_id = $result['dr_id'];
                $value = $result['value'];
                $external_id = $result['external_id'];

                $content[$dr_id] = array('external_id' => $external_id, 'value' => $value);
            }


            // Render and return a page detailing which datarecords have multiple child/linked datarecords...
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:datafield_content_report.html.twig',
                    array(
                        'datafield' => $datafield,
                        'datatype' => $datatype,
                        'content' => $content,
                        'use_external_id_field' => $use_external_id_field,
                    )
                )
            );

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x173658256 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Given a datafield, build a list of all values stored in that datafield
     *
     * @param integer $datafield_id
     * @param Request $request
     *
     * @return Response
     */
    public function analyzeradioselectionsAction($datafield_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $templating = $this->get('templating');

            /** @var DataFields $datafield */
            $datafield = $em->getRepository('ODRAdminBundle:DataFields')->find($datafield_id);
            if ( $datafield == null )
                return parent::deletedEntityError('DataField');

            $datatype = $datafield->getDataType();
            if ( $datatype == null )
                return parent::deletedEntityError('DataType');
            $datatype_id = $datatype->getId();


            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype_id ]) && isset($user_permissions[ $datatype_id ][ 'edit' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------

            // ----------------------------------------
            // Only run this on valid fieldtypes...
            $typeclass = $datafield->getFieldType()->getTypeClass();
            if ($typeclass !== 'Radio')
                throw new \Exception('Invalid DataField');

            // Find all selected radio options for this datafield
            $query = $em->createQuery(
               'SELECT dr.id AS dr_id, rom.optionName
                FROM ODRAdminBundle:DataRecord AS dr
                JOIN ODRAdminBundle:DataRecordFields AS drf WITH drf.dataRecord = dr
                JOIN ODRAdminBundle:RadioSelection AS rs WITH rs.dataRecordFields = drf
                JOIN ODRAdminBundle:RadioOptions AS ro WITH rs.radioOption = ro
                JOIN ODRAdminBundle:RadioOptionsMeta AS rom WITH rom.radioOption = ro
                WHERE drf.dataField = :datafield AND rs.selected = 1
                AND dr.deletedAt IS NULL AND drf.deletedAt IS NULL AND rs.deletedAt IS NULL AND ro.deletedAt IS NULL AND rom.deletedAt IS NULL'
            )->setParameters( array('datafield' => $datafield_id) );
            $results = $query->getArrayResult();

            // Determine which datarecords have multiple selected radio options
            $datarecords = array();
            foreach ($results as $num => $result) {
                $dr_id = $result['dr_id'];

                if ( !isset($datarecords[$dr_id]) )
                    $datarecords[$dr_id] = 0;

                $datarecords[$dr_id]++;
            }

            // Don't need to save datarecords that don't have multiple selections
            foreach ($datarecords as $dr_id => $count) {
                if ($count < 2)
                    unset($datarecords[$dr_id]);
            }

            // ----------------------------------------
            // Render and return a page detailing which datarecords have multiple selected Radio Options...
            $return['d'] = array(
                'html' => $templating->render(
                    'ODRAdminBundle:Reports:radio_selections_report.html.twig',
                    array(
                        'datafield' => $datafield,
                        'datatype' => $datatype,
                        'datarecords' => $datarecords,
                    )
                )
            );
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x365824256 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Returns a simple JSON array of progress made towards decrypting a given file.
     * TODO - allow for images as well?
     *
     * @param integer $file_id
     * @param Request $request
     *
     * @return Response
     */
    public function getfiledecryptprogressAction($file_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var File $file */
            $file = $em->getRepository('ODRAdminBundle:File')->find($file_id);
            if ($file == null)
                return parent::deletedEntityError('File');
            $datarecord = $file->getDataRecord();
            if ($datarecord == null)
                return parent::deletedEntityError('DataRecord');
            $datatype = $datarecord->getDataType();
            if ($datatype == null)
                return parent::deletedEntityError('DataType');

            // Files that aren't done encrypting shouldn't be checked for decryption progress
            if ($file->getOriginalChecksum() == '')
                return parent::deletedEntityError('File');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();   // <-- will return 'anon.' when nobody is logged in
            if ( $file->isPublic() ) {
                // public file, anybody can view
            }
            else if ( $user === 'anon.' ) {
                // non-public file and anonymous user, can't view
                return parent::permissionDeniedError('view');
            }
            else {
                // Grab user's permissions
                $user_permissions = parent::getPermissionsArray($user->getId(), $request);

                // If user has view permissions, show non-public sections of the datarecord
                $has_view_permission = false;
                if ( isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'view' ]) )
                    $has_view_permission = true;

                // If datatype is not public and user doesn't have permissions to view anything other than public sections of the datarecord, then don't allow them to view
                if ( !$file->isPublic() && !$has_view_permission )
                    return parent::permissionDeniedError('view');
            }
            // --------------------

            $progress = array('current_value' => 0, 'max_value' => 100);

            // Shouldn't really be necessary if the file is public, but including anyways for completeness/later use
            if ( $file->isPublic() ) {
                $absolute_path = realpath( dirname(__FILE__).'/../../../../web/'.$file->getLocalFileName() );

                if (!$absolute_path) {
                    // File doesn't exist, so no progress yet
                }
                else {
                    // Grab current filesize of file
                    clearstatcache(true, $absolute_path);
                    $current_filesize = filesize($absolute_path);

                    $progress['current_value'] = intval( (floatval($current_filesize) / floatval($file->getFilesize()) ) * 100);
                }
            }
            else {
                // Determine temporary filename
                $temp_filename = md5($file->getOriginalChecksum().'_'.$file_id.'_'.$user->getId());
                $temp_filename .= '.'.$file->getExt();
                $absolute_path = realpath( dirname(__FILE__).'/../../../../web/uploads/files/'.$temp_filename );

                if (!$absolute_path) {
                    // File doesn't exist, so no progress yet
                }
                else {
                    // Grab current filesize of file
                    clearstatcache(true, $absolute_path);
                    $current_filesize = filesize($absolute_path);

                    $progress['current_value'] = intval( (floatval($current_filesize) / floatval($file->getFilesize()) ) * 100);
                }
            }

            $return['d'] = $progress;
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x533652426 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Returns a simple JSON array of progress made towards encrypting a given file.
     * TODO - allow for images as well?
     *
     * @param integer $file_id
     * @param Request $request
     *
     * @return Response
     */
    public function getfileencryptprogressAction($file_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var File $file */
            $file = $em->getRepository('ODRAdminBundle:File')->find($file_id);
            if ($file == null)
                return parent::deletedEntityError('File');
            $datarecord = $file->getDataRecord();
            if ($datarecord == null)
                return parent::deletedEntityError('DataRecord');
            $datatype = $datarecord->getDataType();
            if ($datatype == null)
                return parent::deletedEntityError('DataType');

            if ($file->getFilesize() == '')
                throw new \Exception('filesize not set');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.context')->getToken()->getUser();   // <-- will return 'anon.' when nobody is logged in
            if ( $file->isPublic() ) {
                // public file, anybody can view
            }
            else if ( $user === 'anon.' ) {
                // non-public file and anonymous user, can't view
                return parent::permissionDeniedError('view');
            }
            else {
                // Grab user's permissions
                $user_permissions = parent::getPermissionsArray($user->getId(), $request);

                // If user has view permissions, show non-public sections of the datarecord
                $has_view_permission = false;
                if ( isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'view' ]) )
                    $has_view_permission = true;

                // If datatype is not public and user doesn't have permissions to view anything other than public sections of the datarecord, then don't allow them to view
                if ( !$file->isPublic() && !$has_view_permission )
                    return parent::permissionDeniedError('view');
            }
            // --------------------

            $progress = array('current_value' => 100, 'max_value' => 100);

            if ($file->getOriginalChecksum() == null || $file->getOriginalChecksum() == '') {
                // TODO - load from config file somehow?
                $crypto_dir = dirname(__FILE__).'/../../../../app/crypto_dir/File_'.$file_id;
                $chunk_size = 2 * 1024 * 1024;  // 2Mb in bytes

                $num_chunks = intval(floatval($file->getFilesize()) / floatval($chunk_size)) + 1;

                // Going to have to use scandir() to count how many chunks have already been encrypted
                if (file_exists($crypto_dir)) {
                    $files = scandir($crypto_dir);
                    // TODO - assumes linux machine
                    $progress['current_value'] = intval((floatval(count($files) - 2) / floatval($num_chunks)) * 100);
                }
                else {
                    // Encrypted directory doesn't exist yet, so no progress has been made
                    $progress['current_value'] = 0;
                }
            }

            $return['d'] = $progress;
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x365246261 '. $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
