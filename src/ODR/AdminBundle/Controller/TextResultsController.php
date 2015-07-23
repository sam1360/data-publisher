<?php

/**
* Open Data Repository Data Publisher
* TextResults Controller
* (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
* (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
* Released under the GPLv2
*
* The textresults controller handles the selection of Datafields that are
* displayed by the jQuery Datatables plugin, in addition to ajax
* communication with the Datatables plugin for display of data and state storage.
* @see https://www.datatables.net/
*
*/

namespace ODR\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

// Entities
use ODR\AdminBundle\Entity\Theme;
use ODR\AdminBundle\Entity\ThemeDataField;
use ODR\AdminBundle\Entity\ThemeDataType;
use ODR\AdminBundle\Entity\ThemeElement;
use ODR\AdminBundle\Entity\ThemeElementField;
use ODR\AdminBundle\Entity\DataFields;
use ODR\AdminBundle\Entity\DataType;
use ODR\AdminBundle\Entity\DataTree;
use ODR\AdminBundle\Entity\RadioOptions;
use ODR\AdminBundle\Entity\RenderPluginFields;
use ODR\AdminBundle\Entity\RenderPluginInstance;
use ODR\AdminBundle\Entity\RenderPluginMap;
use ODR\AdminBundle\Entity\RenderPluginOptions;
use ODR\AdminBundle\Entity\UserPermissions;
// Forms
use ODR\AdminBundle\Form\DecimalValueForm;
use ODR\AdminBundle\Form\IntegerValueForm;
use ODR\AdminBundle\Form\DatafieldsForm;
use ODR\AdminBundle\Form\DatatypeForm;
use ODR\AdminBundle\Form\UpdateDataFieldsForm;
use ODR\AdminBundle\Form\UpdateDataTypeForm;
use ODR\AdminBundle\Form\UpdateThemeElementForm;
use ODR\AdminBundle\Form\UpdateThemeDatafieldForm;
// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class TextResultsController extends ODRCustomController
{

    /**
     * Updates the database with the new TextResults field order specified by the user.
     * 
     * @param string $unused_field_ids The database ids of datafields not added to TextResults
     * @param string $used_field_ids   The database ids of datafields added to TextResults, in order
     * @param Request $request
     *
     * @return an empty Symfony JSON response, unless an error occurred
     */
    public function fieldorderAction($unused_field_ids, $used_field_ids, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            $em = $this->getDoctrine()->getManager();
            $repo_datafield = $em->getRepository('ODRAdminBundle:DataFields');

            // Turn the field ids into an array...
            if ($unused_field_ids !== '')
                $unused_field_ids = preg_split("/,/", $unused_field_ids);
            if ($used_field_ids !== '')
                $used_field_ids = preg_split("/,/", $used_field_ids);

            // Ensure all the fields belong to the same datatype...
            $datatype = null;
            if ($unused_field_ids !== '') {
                for ($i = 0; $i < count($unused_field_ids); $i++) {
                    // Grab datafield being referred to
                    $datafield = $repo_datafield->find( $unused_field_ids[$i] );
                    if ($datatype == null)
                        $datatype = $datafield->getDataType();

                    // If datatype of this field doesn't match a previous field, exit
                    if ($datatype !== $datafield->getDataType())
                        throw new \Exception("Malformed URL!");
                }
            }
            if ($used_field_ids !== '') {
                for ($i = 0; $i < count($used_field_ids); $i++) {
                    // Grab datafield being referred to
                    $datafield = $repo_datafield->find( $used_field_ids[$i] );
                    if ($datatype == null)
                        $datatype = $datafield->getDataType();

                    // If datatype of this field doesn't match a previous field, exit
                    // If datatype of this field doesn't match a previous field, exit
                    if ($datatype !== $datafield->getDataType())
                        throw new \Exception("Malformed URL!");
                }
            }
            // ..now that all datafields have been verified to belong to the same datatype...

            // --------------------
            // Determine user privileges
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'design' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------


            // If user has permissions, go through all of the datafields setting the order
            if ($unused_field_ids !== '') {
                for ($i = 0; $i < count($unused_field_ids); $i++) {
                    $datafield = $repo_datafield->find( $unused_field_ids[$i] );
                    $datafield->setDisplayOrder(-1);
                    $datafield->setUpdatedBy($user);

                    $em->persist($datafield);
                }
            }
            if ($used_field_ids !== '') {
                for ($i = 0; $i < count($used_field_ids); $i++) {
                    $datafield = $repo_datafield->find( $used_field_ids[$i] );
                    $datafield->setDisplayOrder( $i+1 );
                    $datafield->setUpdatedBy($user);

                    $em->persist($datafield);
                }
            }

            // Store whether the datatype has textresults
            if ($used_field_ids !== '')
                $datatype->setHasTextresults(true);
            else
                $datatype->setHasTextresults(false);

            $em->flush();

            // Schedule the cache for an update
            $options = array();
            $options['mark_as_updated'] = true;
            $options['force_textresults_recache'] = true;

            parent::updateDatatypeCache($datatype->getId(), $options);
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x828368002 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Takes an AJAX request from the jQuery DataTables plugin and builds an array of TextResults rows for the plugin to display.
     * @see http://datatables.net/manual/server-side
     *
     * @param Request $request
     *
     * @return JSON array TODO
     */
    public function datatablesrowrequestAction(Request $request)
    {
        $return = array();
        $return['data'] = '';

        try {
            // ----------------------------------------
            $session = $request->getSession();

            // Grab data from post...
            $post = $_POST;
//print_r($post);
//return;

            $odr_tab_id = '';
            if ( isset($post['odr_tab_id']) && trim($post['odr_tab_id']) !== '' )
                $odr_tab_id = $post['odr_tab_id'];

            $datatype_id = intval( $post['datatype_id'] );
            $draw = intval( $post['draw'] );    // intval because of recommendation by datatables documentation
            $start = intval( $post['start'] );

            // Need to deal with requests for a sorted table...
            $sort_column = 0;
            $sort_dir = 'asc';
            if ( isset($post['order']) && isset($post['order']['0']) ) {
                $sort_column = $post['order']['0']['column'];
                $sort_dir = strtoupper( $post['order']['0']['dir'] );
            }

            // Deal with page_length changes...
            $length = intval( $post['length'] );
            if ($odr_tab_id !== '') {
                $stored_tab_data = array();
                if ( $session->has('stored_tab_data') )
                    $stored_tab_data = $session->get('stored_tab_data');
                $old_length = '';

                // Save page_length for this tab if different or doesn't exist
                if ( isset($stored_tab_data[$odr_tab_id]) && isset($stored_tab_data[$odr_tab_id]['page_length']) )
                    $old_length = $stored_tab_data[$odr_tab_id]['page_length'];
                else
                    $stored_tab_data[$odr_tab_id] = array();

                if ( $length !== $old_length ) {
                    $stored_tab_data[$odr_tab_id]['page_length'] = $length;
                    $session->set('stored_tab_data', $stored_tab_data);
                }
            }

            // search_key is optional
            $search_key = '';
            if ( isset($post['search_key']) )
                $search_key = urldecode($post['search_key']);   // apparently have to decode this because it's coming through a POST request instead of a GET?


            // ----------------------------------------
            // Get Entity Manager and setup objects
            $em = $this->getDoctrine()->getManager();
            $repo_datarecord = $em->getRepository('ODRAdminBundle:DataRecord');
            $repo_datatype = $em->getRepository('ODRAdminBundle:DataType');

            $datatype = $repo_datatype->find($datatype_id);
            // TODO - deleted datatype?
            // TODO - user permissions?

            // ----------------------------------------
            $datarecord_count = 0;
            $list = array();
            if ( $search_key == '' ) {
                // Grab the sorted list of datarecords for this datatype
                $list = parent::getSortedDatarecords($datatype);

                // TODO - reaaaaaaaaallly need to get the above method to change its return type depending on user needs
                $list = explode(',', $list);
            }
            else {
                // Get all datarecords from the search key
                $logged_in = true;
                $encoded_search_key = '';
                $datarecord_list = '';
                if ($search_key !== '') {
                    // 
                    $data = parent::getSavedSearch($search_key, $logged_in, $request);
                    $encoded_search_key = $data['encoded_search_key'];
                    $datarecord_list = $data['datarecord_list'];

/*
                    // If the user is attempting to view a datarecord from a search that returned no results...
                    if ($encoded_search_key !== '' && $datarecords === '') {
                        // ...redirect to "no results found" page
                        return $search_controller->renderAction($encoded_search_key, 1, 'searching', $request);
                    }
*/

                }

                // Convert the comma-separated list into an array
                if ( trim($datarecord_list) !== '')
                    $list = explode(',', $datarecord_list);
            }

            // Save how many records total there are before filtering...
            $datarecord_count = count($list);


            // ----------------------------------------
            if ($sort_column >= 2) {    // column 0 is datarecord id, column 1 is default sort column...
                // Adjust datatables column number to datafield display order number
                $sort_column--;

                // Need the typeclass of the datafield first
                $query = $em->createQuery(
                   'SELECT ft.typeClass AS type_class
                    FROM ODRAdminBundle:DataFields AS df
                    JOIN ODRAdminBundle:FieldType AS ft WITH df.fieldType = ft
                    WHERE df.dataType = :datatype AND df.displayOrder = :display_order
                    AND df.deletedAt IS NULL AND ft.deletedAt IS NULL'
                )->setParameters( array('datatype' => $datatype_id, 'display_order' => $sort_column) );
                $results = $query->getArrayResult();
                $typeclass = $results[0]['type_class'];


                if ($typeclass == 'Radio') {
                    // Get the list of radio options
                    $query = $em->createQuery(
                       'SELECT ro.optionName AS option_name, dr.id AS dr_id
                        FROM ODRAdminBundle:RadioOptions AS ro
                        JOIN ODRAdminBundle:RadioSelection AS rs WITH rs.radioOption = ro
                        JOIN ODRAdminBundle:DataRecordFields AS drf WITH rs.dataRecordFields = drf
                        JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
                        JOIN ODRAdminBundle:DataFields AS df WITH drf.dataField = df
                        WHERE dr.id IN (:datarecords) AND df.displayOrder = :display_order AND rs.selected = 1'
                    )->setParameters( array('datarecords' => $list, 'display_order' => $sort_column) );
                    $results = $query->getArrayResult();

                    // Build an array so php can sort the list
                    $tmp = array();
                    foreach ($results as $num => $data) {
                        $option_name = $data['option_name'];
                        $datarecord_id = $data['dr_id'];

                        $key = $option_name.'_'.$datarecord_id;
                        $tmp[ $key ] = $datarecord_id;
                    }

                    // 
                    if ($sort_dir == 'DESC')
                        krsort($tmp);
                    else
                        ksort($tmp);

                    // Convert back into a list of datarecord ids for printing
                    $list = array();
                    foreach ($tmp as $key => $dr_id)
                        $list[] = $dr_id;
                }
                else if ($typeclass == 'File') {
                    // Get the list of file names...have to left join the file table because datarecord id is required, but there may not always be a file uploaded
                    $query = $em->createQuery(
                       'SELECT f.originalFileName AS file_name, dr.id AS dr_id
                        FROM ODRAdminBundle:DataRecord AS dr
                        JOIN ODRAdminBundle:DataRecordFields AS drf WITH drf.dataRecord = dr
                        JOIN ODRAdminBundle:DataFields AS df WITH drf.dataField = df
                        LEFT JOIN ODRAdminBundle:File AS f WITH f.dataRecordFields = drf
                        WHERE dr.id IN (:datarecords) AND df.displayOrder = :display_order
                        AND f.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL AND df.deletedAt IS NULL
                        ORDER BY f.originalFileName '.$sort_dir
                    )->setParameters( array('datarecords' => $list, 'display_order' => $sort_column) );
                    $results = $query->getArrayResult();

                    // TODO - sort in php instead of SQL?
                    // Redo the list of datarecords based on the sorted order
                    $list = array();
                    foreach ($results as $num => $result) {
                        $list[] = $result['dr_id'];
                    }
                }
                else {
                    // Get SQL to sort the list of datarecords
                    $query = $em->createQuery(
                       'SELECT dr.id AS dr_id
                        FROM ODRAdminBundle:DataRecord AS dr
                        JOIN ODRAdminBundle:DataRecordFields AS drf WITH drf.dataRecord = dr
                        JOIN ODRAdminBundle:DataFields AS df WITH drf.dataField = df
                        JOIN ODRAdminBundle:'.$typeclass.' AS e WITH e.dataRecordFields = drf
                        WHERE dr.id IN (:datarecords) AND df.displayOrder = :display_order
                        AND dr.deletedAt IS NULL AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND df.deletedAt IS NULL
                        ORDER BY e.value '.$sort_dir
                    )->setParameters( array('datarecords' => $list, 'display_order' => $sort_column) );
                    $results = $query->getArrayResult();

                    // TODO - sort in php instead of SQL?
                    // Redo the list of datarecords based on the sorted order
                    $list = array();
                    foreach ($results as $num => $result) {
                        $list[] = $result['dr_id'];
                    }
                }
            }

            // Only save the subset of records pointed to by the $start and $length values
            $datarecord_list = array();
            for ($index = $start; $index < ($start + $length); $index++) {
                if ( !isset($list[$index]) )
                    break;

                $datarecord_list[] = $list[$index];
            }


            // ----------------------------------------
            // Save the sorted list of datarecords in the user's session
            if ($odr_tab_id !== '') {
                $stored_tab_data = array();
                if ( $session->has('stored_tab_data') )
                    $stored_tab_data = $session->get('stored_tab_data');

                if ( !isset($stored_tab_data[$odr_tab_id]) )
                    $stored_tab_data[$odr_tab_id] = array();

                $stored_tab_data[$odr_tab_id]['datarecord_list'] = implode(',', $list);
                $session->set('stored_tab_data', $stored_tab_data);
//print_r($stored_tab_data);
            }


            // ----------------------------------------
            // Get the rows that will fulfill the request
            $data = array();
            if ( $datarecord_count > 0 )
                $data = parent::renderTextResultsList($datarecord_list, $datatype, $request);

            // Build the json array to return to the datatables request
            $json = array(
                'draw' => $draw,
                'recordsTotal' => $datarecord_count,
                'recordsFiltered' => $datarecord_count,
                'data' => $data,
            );
            $return = $json;

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x122280082 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Saves a datatables state object in Symfony's session object
     *
     * @param Request $request
     *
     * @return TODO 
     */
    public function datatablesstatesaveAction(Request $request)
    {
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $session = $request->getSession();

            // Grab data from post...
            $post = $_POST;

            // Don't want to store the tab_id as part of the datatables state array
            $odr_tab_id = $post['odr_tab_id'];
            unset( $post['odr_tab_id'] );

            // Save the sorted list of datarecords in the user's session
            $stored_tab_data = array();
            if ( $session->has('stored_tab_data') )
                $stored_tab_data = $session->get('stored_tab_data');

            if ( !isset($stored_tab_data[$odr_tab_id]) )
                $stored_tab_data[$odr_tab_id] = array();

            $stored_tab_data[$odr_tab_id]['state'] = $post;
            $session->set('stored_tab_data', $stored_tab_data);

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x182060382 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**
     * Loads and returns a datatables state object from Symfony's session
     *
     * @param Request $request
     *
     * @return TODO 
     */
    public function datatablesstateloadAction(Request $request)
    {
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $session = $request->getSession();

            // Grab data from post...
            $post = $_POST;

            if ( isset($post['odr_tab_id']) ) {
                $odr_tab_id = $post['odr_tab_id'];

                // Grab the requested state object from the user's session
                $stored_tab_data = array();
                if ( $session->has('stored_tab_data') )
                    $stored_tab_data = $session->get('stored_tab_data');

                if ( !isset($stored_tab_data[$odr_tab_id]) )
                    $stored_tab_data[$odr_tab_id] = array();

                $state = array();
                if ( isset($stored_tab_data[$odr_tab_id]['state']) )
                    $state = $stored_tab_data[$odr_tab_id]['state'];

                $return = $state;
            }
            else {
                $return = array();
            }
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x906023282 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**
     * Deletes a datatables state object from Symfony's session
     * TODO - transfer settings from old to new tab?
     *
     * @param string $odr_tab_id
     * @param Request $request
     *
     * @return TODO
     */
    public function datatablesstatedestroyAction($odr_tab_id, Request $request)
    {
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $session = $request->getSession();

            // Locate the sorted list of datarecords in the user's session
            if ( $session->has('stored_tab_data') ) {
                $stored_tab_data = $session->get('stored_tab_data');

                if ( isset($stored_tab_data[$odr_tab_id]) ) {
                    unset( $stored_tab_data[$odr_tab_id] );
                    $session->set('stored_tab_data', $stored_tab_data);
                }
            }
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x923362822 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

}