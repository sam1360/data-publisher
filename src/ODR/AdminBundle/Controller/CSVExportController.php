<?php

/**
* Open Data Repository Data Publisher
* CSVExport Controller
* (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
* (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
* Released under the GPLv2
*
* The csvexport controller handles rendering and processing a
* form that allows the user to select which datafields to export
* into a csv file, and also handles the work of exporting the data.
*
*/

namespace ODR\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

// Entities
use ODR\AdminBundle\Entity\TrackedError;
use ODR\AdminBundle\Entity\TrackedJob;
use ODR\AdminBundle\Entity\TrackedCSVExport;
use ODR\AdminBundle\Entity\ThemeElement;
use ODR\AdminBundle\Entity\ThemeElementField;
use ODR\AdminBundle\Entity\ThemeDataField;
use ODR\AdminBundle\Entity\ThemeDataType;
use ODR\AdminBundle\Entity\DataFields;
use ODR\AdminBundle\Entity\DataType;
use ODR\AdminBundle\Entity\DataTree;
use ODR\AdminBundle\Entity\DataRecord;
use ODR\AdminBundle\Entity\DataRecordFields;
use ODR\AdminBundle\Entity\Boolean;
use ODR\AdminBundle\Entity\ShortVarchar;
use ODR\AdminBundle\Entity\MediumVarchar;
use ODR\AdminBundle\Entity\LongVarchar;
use ODR\AdminBundle\Entity\LongText;
use ODR\AdminBundle\Entity\DecimalValue;
use ODR\AdminBundle\Entity\DatetimeValue;
use ODR\AdminBundle\Entity\IntegerValue;
use ODR\AdminBundle\Entity\Image;
use ODR\AdminBundle\Entity\ImageSizes;
use ODR\AdminBundle\Entity\ImageStorage;
use ODR\AdminBundle\Entity\RadioOptions;
use ODR\AdminBundle\Entity\RadioSelection;
use ODR\AdminBundle\Entity\FileChecksum;
use ODR\AdminBundle\Entity\ImageChecksum;
// Forms
// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// CSV Reader
use Ddeboer\DataImport\Workflow;
use Ddeboer\DataImport\Reader;
use Ddeboer\DataImport\Writer;
use Ddeboer\DataImport\Filter;
use Ddeboer\DataImport\Writer\CsvWriter;


class CSVExportController extends ODRCustomController
{


    /**
     * Sets up a csv export request made from a search results page.
     * 
     * @param integer $datatype_id The database id of the DataType the search was performed on.
     * @param string $search_key   The search key identifying which datarecords to potentially export
     * @param integer $offset
     * @param Request $request
     * 
     * @return Response TODO
     */
    public function csvExportAction($datatype_id, $search_key, $offset, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            $templating = $this->get('templating');
            $session = $request->getSession();

            // --------------------
            // Determine user privileges
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);
            $logged_in = true;

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype_id ]) && isset($user_permissions[ $datatype_id ][ 'edit' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------


            // ----------------------------------------
            // If this datarecord is being viewed from a search result list, attempt to grab the list of datarecords from that search result
            $encoded_search_key = '';
            $search_checksum = '';
            if ($search_key !== '') {
                // 
                $data = parent::getSavedSearch($search_key, $logged_in, $request);
                $encoded_search_key = $data['encoded_search_key'];
                $datarecord_list = $data['datarecord_list'];
                $search_checksum = $data['search_checksum'];

                // If the user is attempting to view a datarecord from a search that returned no results...
                if ($encoded_search_key !== '' && $datarecord_list === '') {
                    // ...get the search controller to redirect to "no results found" page
                    $search_controller = $this->get('odr_search_controller', $request);
                    return $search_controller->renderAction($encoded_search_key, 1, 'searching', $request);
                }
            }

            // Generate the HTML required for a header
            $templating = $this->get('templating');
            $header_html = $templating->render(
                'ODRAdminBundle:CSVExport:csvexport_header.html.twig',
                array(
                    'search_key' => $encoded_search_key,
                    'offset' => $offset,
                )
            );

            // Get the mass edit page rendered
            $page_html = self::csvExportRender($datatype_id, $search_checksum, $request);    // Using $search_checksum so Symfony doesn't screw up $search_key as it is passed around
            $return['d'] = array( 'html' => $header_html.$page_html );
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x12736279 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    /**
     * Renders and returns the html used for performing csv exporting
     * 
     * @param integer $datatype_id    The database id that the search was performed on.
     * @param string $search_checksum The md5 checksum created from a $search_key...can't use $search_key directly because Symfony automatically "decodes" the string when passed as an argument
     * @param Request $request
     * 
     * @return string
     */
    private function csvExportRender($datatype_id, $search_checksum, Request $request)
    {
        // Required objects
        $em = $this->getDoctrine()->getManager();
        $repo_datatype = $em->getRepository('ODRAdminBundle:DataType');
        $theme = $em->getRepository('ODRAdminBundle:Theme')->find(1);
        $templating = $this->get('templating');

        // --------------------
        // Determine user privileges
        $user = $this->container->get('security.context')->getToken()->getUser();
        $datatype_permissions = parent::getPermissionsArray($user->getId(), $request);
        $datafield_permissions = parent::getDatafieldPermissionsArray($user->getId(), $request);
        // --------------------

        $datatype = null;
        $theme_element = null;
        if ($datatype_id !== null) 
            $datatype = $repo_datatype->find($datatype_id);

        $indent = 0;
        $is_link = 0;
        $top_level = 1;
        $short_form = true;     // ?

$debug = true;
$debug = false;
if ($debug)
    print '<pre>';

        $tree = parent::buildDatatypeTree($user, $theme, $datatype, $theme_element, $em, $is_link, $top_level, $short_form, $debug, $indent);

if ($debug)
    print '</pre>';


        $html = $templating->render(
            'ODRAdminBundle:CSVExport:csvexport_ajax.html.twig',
            array(
//                'datafield_permissions' => $datafield_permissions,
                'search_checksum' => $search_checksum,
                'datatype_tree' => $tree,
                'theme' => $theme,
            )
        );

        return $html;
    }


    /**
     * Begins the process of mass exporting to a csv file, by creating a beanstalk job containing which datafields to export for each datarecord being exported 
     * 
     * @param Request $request
     * 
     * @return Response TODO
     */
    public function csvExportStartAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $post = $_POST;
//print_r($post);
//return;

            if ( !isset($post['search_checksum']) || !isset($post['datafields']) || !isset($post['datatype_id']) || !isset($post['csv_export_delimiter']) )
                throw new \Exception('bad post request');

            $search_checksum = $post['search_checksum'];
            $datafields = $post['datafields'];
            $datatype_id = $post['datatype_id'];
            $delimiter = $post['csv_export_delimiter'];

            $secondary_delimiter = null;
            if ( isset($post['csv_export_secondary_delimiter']) )
                $secondary_delimiter = $post['csv_export_secondary_delimiter'];

            // TODO - ensure datafields belong to datatype?

            // Grab necessary objects
            $em = $this->getDoctrine()->getManager();
            $repo_datarecord = $em->getRepository('ODRAdminBundle:DataRecord');
            $repo_datarecordfields = $em->getRepository('ODRAdminBundle:DataRecordFields');

//            $memcached = $this->get('memcached');
//            $memcached->setOption(\Memcached::OPT_COMPRESSION, true);
            $memcached_prefix = $this->container->getParameter('memcached_key_prefix');
            $session = $request->getSession();
            $api_key = $this->container->getParameter('beanstalk_api_key');
            $pheanstalk = $this->get('pheanstalk');

            $url = $this->container->getParameter('site_baseurl');
            $url .= $this->container->get('router')->generate('odr_csv_export_construct');

            // --------------------
            // Determine user privileges
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);
            $logged_in = true;

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype_id ]) && isset($user_permissions[ $datatype_id ][ 'edit' ])) )
                return parent::permissionDeniedError("edit");
            // --------------------


            // Ensure datafield ids are numeric? 
            foreach ($datafields as $num => $datafield_id) {
                if ( !is_numeric($datafield_id) )
                    throw new \Exception('bad post request');
            }

            // Translate delimiter from string to character
            switch ($delimiter) {
                case 'tab':
                    $delimiter = "\t";
                    break;
                case 'space':
                    $delimiter = " ";
                    break;
                case 'comma':
                    $delimiter = ",";
                    break;
                case 'semicolon':
                    $delimiter = ";";
                    break;
                case 'colon':
                    $delimiter = ":";
                    break;
                case 'pipe':
                    $delimiter = "|";
                    break;
                default:
                    throw new \Exception('bad post request');
                    break;
            }
            switch ($secondary_delimiter) {
/*
                case 'tab':
                    $secondary_delimiter = "\t";
                    break;
                case 'space':
                    $secondary_delimiter = " ";
                    break;
                case 'comma':
                    $secondary_delimiter = ",";
                    break;
*/
                case 'semicolon':
                    $secondary_delimiter = ";";
                    break;
                case 'colon':
                    $secondary_delimiter = ":";
                    break;
                case 'pipe':
                    $secondary_delimiter = "|";
                    break;
                case null:
                    break;
                default:
                    throw new \Exception('bad post request');
                    break;
            }


            // TODO - assumes search exists...replace with parent::getSavedSearch() to ensure it exists, or throw an error if it doesn't?
            $search_controller = $this->get('odr_search_controller', $request);
            $search_controller->setContainer($this->container);
            // Grab the list of saved searches and attempt to locate the desired search
            $saved_searches = $session->get('saved_searches');
            $search_params = $saved_searches[$search_checksum];
            // Now that the search is guaranteed to exist and be correct...get all pieces of info about the search
            $datarecords = $search_params['datarecords'];
            $encoded_search_key = $search_params['encoded_search_key'];


            // If the user is attempting to view a datarecord from a search that returned no results...
            if ($encoded_search_key !== '' && $datarecords === '') {
                // ...redirect to "no results found" page
                return $search_controller->renderAction($encoded_search_key, 1, 'searching', $request);
            }

            $datarecords = explode(',', $datarecords);
//print_r($datarecords);
//print_r($datafields);
//return;

            if ( count($datarecords) > 0 ) {
                // ----------------------------------------
                // Get/create an entity to track the progress of this datatype recache
                $job_type = 'csv_export';
                $target_entity = 'datatype_'.$datatype_id;
                $additional_data = array('description' => 'Exporting data from DataType '.$datatype_id);
                $restrictions = '';
                $total = count($datarecords);
                $reuse_existing = false;
//$reuse_existing = true;

                // Determine if this user already has an export job going for this datatype
                $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->findOneBy( array('job_type' => $job_type, 'target_entity' => $target_entity, 'createdBy' => $user->getId(), 'completed' => null) );
                if ($tracked_job !== null)
                    throw new \Exception('You already have an export job going for this datatype...wait until that one finishes before starting a new one');
                else
                    $tracked_job = self::ODR_getTrackedJob($em, $user, $job_type, $target_entity, $additional_data, $restrictions, $total, $reuse_existing);

                $tracked_job_id = $tracked_job->getId();


                // ----------------------------------------
                // Create a beanstalk job for each of these datarecords
                foreach ($datarecords as $num => $datarecord_id) {

                    $priority = 1024;   // should be roughly default priority
                    $payload = json_encode(
                        array(
                            'tracked_job_id' => $tracked_job_id,
                            'user_id' => $user->getId(),

                            'delimiter' => $delimiter,
                            'secondary_delimiter' => $secondary_delimiter,
                            'datarecord_id' => $datarecord_id,
                            'datafields' => $datafields,
                            'memcached_prefix' => $memcached_prefix,    // debug purposes only
                            'datatype_id' => $datatype_id,              // debug purposes only?
                            'url' => $url,
                            'api_key' => $api_key,
                        )
                    );

//print_r($payload);
//return;

                    $delay = 1; // one second
                    $pheanstalk->useTube('csv_export_start')->put($payload, $priority, $delay);
                }

                // TODO - Notify user that job has begun?
            }
            else {
                throw new \Exception('No datarecords selected to export');
            }
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x24397429 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**
     * Given a datarecord id and a list of datafield ids, builds a line of csv data used by Ddeboer\DataImport\Writer\CsvWriter later
     * 
     * @param Request $request
     * 
     * @return Response TODO
     */
    public function csvExportConstructAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $post = $_POST;
//print_r($post);
//return;

            if ( !isset($post['tracked_job_id']) || !isset($post['user_id']) || !isset($post['datarecord_id']) || !isset($post['datatype_id']) || !isset($post['datafields']) || !isset($post['api_key']) || !isset($post['delimiter']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $user_id = $post['user_id'];
            $datarecord_id = $post['datarecord_id'];
            $datatype_id = $post['datatype_id'];
            $datafields = $post['datafields'];
            $api_key = $post['api_key'];
            $delimiter = $post['delimiter'];

            $secondary_delimiter = null;
            if ( isset($post['secondary_delimiter']) )
                $secondary_delimiter = $post['secondary_delimiter'];

            // Load symfony objects
            $memcached_prefix = $this->container->getParameter('memcached_key_prefix');
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            $pheanstalk = $this->get('pheanstalk');
            $logger = $this->get('logger');
            $memcached = $this->get('memcached');
            $memcached->setOption(\Memcached::OPT_COMPRESSION, true);

            $em = $this->getDoctrine()->getManager();
            $repo_tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob');


            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            $url = $this->container->getParameter('site_baseurl');
            $url .= $this->container->get('router')->generate('odr_csv_export_worker');


            $datarecord_data = array();

            // ----------------------------------
            // Load FieldTypes of the datafields
            $query = $em->createQuery(
               'SELECT df.id AS df_id, df.fieldName AS fieldname, ft.typeClass AS typeclass, ft.typeName AS typename
                FROM ODRAdminBundle:DataFields AS df
                JOIN ODRAdminBundle:FieldType AS ft WITH df.fieldType = ft
                WHERE df IN (:datafields)'
            )->setParameters( array('datafields' => $datafields) );
            $results = $query->getArrayResult();
//print_r($results);

            $typeclasses = array();
            foreach ($results as $num => $result) {
                $typeclass = $result['typeclass'];
                $typename = $result['typename'];

                if ($typeclass !== 'File' && $typeclass !== 'Image' && $typename !== 'Markdown') {
                    if ( !isset($typeclasses[ $result['typeclass'] ]) )
                        $typeclasses[ $result['typeclass'] ] = array();

                    $typeclasses[ $result['typeclass'] ][] = $result['df_id'];

                    if ($typeclass == 'Radio') {
                        $datarecord_data[ $result['df_id'] ] = array();

                        if ( ($typename == "Multiple Radio" || $typename == "Multiple Select") && $secondary_delimiter == null)
                            throw new \Exception('Invalid Form');
                    }
                    else {
                        $datarecord_data[ $result['df_id'] ] = '';
                    }
                }
            }

//print_r($typeclasses);
//return;

            // ----------------------------------
            // Need to grab external id for this datarecord
/*
            $query = $em->createQuery(
               'SELECT dr.external_id AS external_id
                FROM ODRAdminBundle:DataRecord AS dr
                WHERE dr.id = :datarecord AND dr.deletedAt IS NULL'
            )->setParameters( array('datarecord' => $datarecord_id) );
            $result = $query->getArrayResult();
//print_r($result);
            $external_id = $result[0]['external_id'];
*/
            $datarecord = $em->getRepository('ODRAdminBundle:DataRecord')->find($datarecord_id);
            $external_id = $datarecord->getExternalId();

            // ----------------------------------
            // Grab data for each of the datafields selected for export
            foreach ($typeclasses as $typeclass => $df_list) {
                if ($typeclass == 'Radio') {
                    $query = $em->createQuery(
                       'SELECT df.id AS df_id, ro.optionName AS option_name
                        FROM ODRAdminBundle:RadioSelection AS rs
                        JOIN ODRAdminBundle:RadioOptions AS ro WITH rs.radioOption = ro
                        JOIN ODRAdminBundle:DataRecordFields AS drf WITH rs.dataRecordFields = drf
                        JOIN ODRAdminBundle:DataFields AS df WITH drf.dataField = df
                        WHERE rs.selected = 1 AND drf.dataRecord = :datarecord AND df.id IN (:datafields)
                        AND rs.deletedAt IS NULL AND ro.deletedAt IS NULL AND df.deletedAt IS NULL'
                    )->setParameters( array('datarecord' => $datarecord_id, 'datafields' => $df_list) );
                    $results = $query->getArrayResult();
//print_r($results);

                    foreach ($results as $num => $result) {
                        $df_id = $result['df_id'];
                        $option_name = $result['option_name'];

                        $datarecord_data[$df_id][] = $option_name;
                    }
                }
                else {
                    $query = $em->createQuery(
                       'SELECT df.id AS df_id, e.value AS value
                        FROM ODRAdminBundle:'.$typeclass.' AS e
                        JOIN ODRAdminBundle:DataRecordFields AS drf WITH e.dataRecordFields = drf
                        JOIN ODRAdminBundle:DataRecord AS dr WITH drf.dataRecord = dr
                        JOIN ODRAdminBundle:DataFields AS df WITH drf.dataField = df
                        JOIN ODRAdminBundle:FieldType AS ft WITH df.fieldType = ft
                        WHERE dr.id = :datarecord AND df.id IN (:datafields) AND ft.typeClass = :typeclass
                        AND e.deletedAt IS NULL AND drf.deletedAt IS NULL AND dr.deletedAt IS NULL AND df.deletedAt IS NULL'
                    )->setParameters( array('datarecord' => $datarecord_id, 'datafields' => $df_list, 'typeclass' => $typeclass) );
                    $results = $query->getArrayResult();

                    foreach ($results as $num => $result) {
                        $df_id = $result['df_id'];
                        $value = $result['value'];

                        // TODO - special handling for boolean

                        if ($typeclass == 'DatetimeValue') {
                            $date = $value->format('Y-m-d');
                            if ( strpos($date, '-0001-11-30') !== false )
                                $date = '0000-00-00';

                            $datarecord_data[$df_id] = $date;
                        }
                        else {
                            // Change nulls to empty string so they get passed to beanstalk properly
                            if ($value == null)
                                $value = '';

                            $datarecord_data[$df_id] = $value;
                        }
                    }
                }
            }

            // Convert any Radio fields from an array into a string
            foreach ($datarecord_data as $df_id => $data) {
                if ( is_array($data) ) {
                    if ( count($data) > 0 )
                        $datarecord_data[$df_id] = implode($secondary_delimiter, $data);
                    else
                        $datarecord_data[$df_id] = '';
                }
            }

            // Sort by datafield id to ensure columns are always in same order in csv file
            ksort($datarecord_data);
//print_r($datarecord_data);
//return;

            $line = array();
            $line[] = $external_id;

            foreach ($datarecord_data as $df_id => $data)
                $line[] = $data;

print_r($line);
//return;

            // ----------------------------------------
            // Create a beanstalk job for this datarecord
            $priority = 1024;   // should be roughly default priority
            $payload = json_encode(
                array(
                    'tracked_job_id' => $tracked_job_id,
                    'user_id' => $user_id,
                    'delimiter' => $delimiter,
                    'datarecord_id' => $datarecord_id,
                    'datatype_id' => $datatype_id,
                    'datafields' => $datafields,
                    'line' => $line,
                    'memcached_prefix' => $memcached_prefix,    // debug purposes only
                    'url' => $url,
                    'api_key' => $api_key,
                )
            );

//print_r($payload);

            $delay = 1; // one second
            $pheanstalk->useTube('csv_export_worker')->put($payload, $priority, $delay);

            $return['d'] = '';
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x24463979 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**
     * Writes a line of csv data to a file
     * 
     * @param Request $request
     * 
     * @return Response TODO
     */
    public function csvExportWorkerAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $post = $_POST;
//print_r($post);
//return;

            if ( !isset($post['tracked_job_id']) || !isset($post['user_id']) || !isset($post['line']) || !isset($post['datafields']) || !isset($post['random_key']) || !isset($post['api_key']) || !isset($post['delimiter']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $user_id = $post['user_id'];
            $line = $post['line'];
            $datafields = $post['datafields'];
            $random_key = $post['random_key'];
            $api_key = $post['api_key'];
            $delimiter = $post['delimiter'];

            // Load symfony objects
            $memcached_prefix = $this->container->getParameter('memcached_key_prefix');
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            $pheanstalk = $this->get('pheanstalk');
            $logger = $this->get('logger');
            $memcached = $this->get('memcached');
            $memcached->setOption(\Memcached::OPT_COMPRESSION, true);

            $em = $this->getDoctrine()->getManager();
            $repo_tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob');


            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            // ----------------------------------------
            // Ensure the random key is stored in the database for later retrieval by the finalization process
            $tracked_csv_export = $em->getRepository('ODRAdminBundle:TrackedCSVExport')->findOneBy( array('random_key' => $random_key) );
            if ($tracked_csv_export == null) {
                // ...TECHNICALLY, THIS IS OVERKILL BECAUSE $random_key IS UNIQUE...
                // TODO - CURRENTLY WORKS, BUT MIGHT WANT TO LOOK INTO AN OFFICIAL MUTEX...

                $query =
                   'INSERT INTO odr_tracked_csv_export (random_key, tracked_job_id)
                    SELECT * FROM (SELECT :random_key AS random_key, :tj_id AS tj_id) AS tmp
                    WHERE NOT EXISTS (
                        SELECT random_key FROM odr_tracked_csv_export WHERE random_key = :random_key AND tracked_job_id = :tj_id
                    ) LIMIT 1;';
                $params = array('random_key' => $random_key, 'tj_id' => $tracked_job_id);
                $conn = $em->getConnection();
                $rowsAffected = $conn->executeUpdate($query, $params);

//print 'rows affected: '.$rowsAffected."\n";
            }

            // Open the indicated file
            $csv_export_path = dirname(__FILE__).'/../../../../web/uploads/csv_export/';
            $filename = 'f_'.$random_key.'.csv';
            $handle = fopen($csv_export_path.'tmp/'.$filename, 'a');
            if ($handle !== false) {
                // Write the line given to the file
                // https://github.com/ddeboer/data-import/blob/master/src/Ddeboer/DataImport/Writer/CsvWriter.php
//                $delimiter = "\t";
                $enclosure = "\"";
                $writer = new CsvWriter($delimiter, $enclosure);

                $writer->setStream($handle);
                $writer->writeItem($line);

                // Close the file
                fclose($handle);
            }
            else {
                // Unable to open file
                throw new \Exception('could not open csv export file...');
            }


            // ----------------------------------------
            // Update the job tracker if necessary
            $completed = false;
            if ($tracked_job_id !== -1) {
                $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->find($tracked_job_id);

                $total = $tracked_job->getTotal();
                $count = $tracked_job->incrementCurrent($em);

                if ($count >= $total) {
                    $tracked_job->setCompleted( new \DateTime() );
                    $completed = true;
                }

                $em->persist($tracked_job);
                $em->flush();
//print '  Set current to '.$count."\n";
            }


            // ----------------------------------------
            // If this was the last line to write to be written to a file for this particular export...
            // NOTE - incrementCurrent()'s current implementation can't guarantee that only a single process will enter this block...so have to ensure that only one process starts the finalize step
            $random_keys = array();
            if ($completed) {
                // Make a hash from all the random keys used
                $query = $em->createQuery(
                   'SELECT tce.id AS id, tce.random_key AS random_key
                    FROM ODRAdminBundle:TrackedCSVExport AS tce
                    WHERE tce.trackedJob = :tracked_job AND tce.finalize = 0
                    ORDER BY tce.id'
                )->setParameters( array('tracked_job' => $tracked_job_id) );
                $results = $query->getArrayResult();

                // Due to ORDER BY, every process entering this section should compute the same $random_key_hash
                $random_key_hash = '';
                foreach ($results as $num => $result) {
                    $random_keys[ $result['id'] ] = $result['random_key'];
                    $random_key_hash .= $result['random_key'];
                }
                $random_key_hash = md5($random_key_hash);
//print $random_key_hash."\n";


                // Attempt to insert this hash back into the database...
                // TODO - CURRENTLY WORKS, BUT MIGHT WANT TO LOOK INTO AN OFFICIAL MUTEX...
                $query =
                   'INSERT INTO odr_tracked_csv_export (random_key, tracked_job_id, finalize)
                    SELECT * FROM (SELECT :random_key_hash AS random_key_hash, :tj_id AS tj_id, :finalize AS final_flag) AS tmp
                    WHERE NOT EXISTS (
                        SELECT random_key FROM odr_tracked_csv_export WHERE random_key = :random_key_hash AND tracked_job_id = :tj_id AND finalize = :finalize
                    ) LIMIT 1;';
                $params = array('random_key_hash' => $random_key_hash, 'tj_id' => $tracked_job_id, 'finalize' => true);
                $conn = $em->getConnection();
                $rowsAffected = $conn->executeUpdate($query, $params);

//print 'rows affected: '.$rowsAffected."\n";

                if ($rowsAffected == 1) {
                    // This is the first process to attempt to insert this key...it will be in charge of creating the information used to concatenate the temporary files together
                    $completed = true;
                }
                else {
                    // This is not the first process to attempt to insert this key, do nothing so multiple finalize jobs aren't created
                    $completed = false;
                }
            }


            // ----------------------------------------
            if ($completed) {
                // Determine the contents of the header line
                $header_line = array(0 => '_external_id');
                $query = $em->createQuery(
                   'SELECT df.id AS id, df.fieldName AS fieldName
                    FROM ODRAdminBundle:DataFields AS df
                    WHERE df.id IN (:datafields) AND df.deletedAt IS NULL'
                )->setParameters( array('datafields' => $datafields) );
                $results = $query->getArrayResult();
                foreach ($results as $num => $result) {
                    $df_id = $result['id'];
                    $df_name = $result['fieldName'];

                    $header_line[$df_id] = $df_name;
                }

                // Sort by datafield id so order of header columns matches order of data
                ksort($header_line);

//print_r($header_line);

                // Make a "final" file for the export, and insert the header line
                $final_filename = 'export_'.$user_id.'_'.$tracked_job_id.'.csv';
                $final_file = fopen($csv_export_path.$final_filename, 'w');

                if ($final_file !== false) {
//                    $delimiter = "\t";
                    $enclosure = "\"";
                    $writer = new CsvWriter($delimiter, $enclosure);

                    $writer->setStream($final_file);
                    $writer->writeItem($header_line);
                }
                else {
                    throw new \Exception('could not open csv export file...b');
                }

                fclose($final_file);

                // ----------------------------------------
                // Now that the "final" file exists, need to splice the temporary files together into it
                $url = $this->container->getParameter('site_baseurl');
                $url .= $this->container->get('router')->generate('odr_csv_export_finalize');

                // 
                $priority = 1024;   // should be roughly default priority
                $payload = json_encode(
                    array(
                        'tracked_job_id' => $tracked_job_id,
                        'final_filename' => $final_filename,
                        'random_keys' => $random_keys,

                        'user_id' => $user_id,
                        'memcached_prefix' => $memcached_prefix,    // debug purposes only
                        'url' => $url,
                        'api_key' => $api_key,
                    )
                );

//print_r($payload);

                $delay = 1; // one second
                $pheanstalk->useTube('csv_export_finalize')->put($payload, $priority, $delay);
            }

            $return['d'] = '';
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x302421399 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**
     * Takes a list of temporary files used for csv exporting, and appends each of their contents to a "final" export file
     * 
     * @param Request $request
     * 
     * @return Response TODO
     */
    public function csvExportFinalizeAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $post = $_POST;
//print_r($post);
//return;

            if ( !isset($post['tracked_job_id']) || !isset($post['final_filename']) || !isset($post['random_keys']) || !isset($post['user_id']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $user_id = $post['user_id'];
            $final_filename = $post['final_filename'];
            $random_keys = $post['random_keys'];
            $api_key = $post['api_key'];

            // Load symfony objects
            $memcached_prefix = $this->container->getParameter('memcached_key_prefix');
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            $pheanstalk = $this->get('pheanstalk');
            $logger = $this->get('logger');
            $memcached = $this->get('memcached');
            $memcached->setOption(\Memcached::OPT_COMPRESSION, true);

            $em = $this->getDoctrine()->getManager();
            $repo_tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob');


            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');



            // -----------------------------------------
            // Append the contents of one of the temporary files to the final file
            $csv_export_path = dirname(__FILE__).'/../../../../web/uploads/csv_export/';
            $final_file = fopen($csv_export_path.$final_filename, 'a');

            // Go through and append the contents of each of the temporary files to the "final" file
            $tracked_csv_export_id = null;
            foreach ($random_keys as $tracked_csv_export_id => $random_key) {
                $tmp_filename = 'f_'.$random_key.'.csv';
                $str = file_get_contents($csv_export_path.'tmp/'.$tmp_filename);
//print $str."\n\n";

                if ( fwrite($final_file, $str) === false )
                    print 'could not write to "'.$csv_export_path.$final_filename.'"'."\n";

                // Done with this intermediate file, get rid of it
                if ( unlink($csv_export_path.'tmp/'.$tmp_filename) === false )
                    print 'could not unlink "'.$csv_export_path.'tmp/'.$tmp_filename.'"'."\n";

                $tracked_csv_export = $em->getRepository('ODRAdminBundle:TrackedCSVExport')->find($tracked_csv_export_id);
                $em->remove($tracked_csv_export);
                $em->flush();

                fclose($final_file);

                // Only want to append the contents of a single temporary file to the final file at a time
                break;
            }


            // -----------------------------------------
            // Done with this temporary file
            unset($random_keys[$tracked_csv_export_id]);

            if ( count($random_keys) >= 1 ) {
                // Create another beanstalk job to get another file fragment appended to the final file
                $url = $this->container->getParameter('site_baseurl');
                $url .= $this->container->get('router')->generate('odr_csv_export_finalize');

                // 
                $priority = 1024;   // should be roughly default priority
                $payload = json_encode(
                    array(
                        'tracked_job_id' => $tracked_job_id,
                        'final_filename' => $final_filename,
                        'random_keys' => $random_keys,

                        'user_id' => $user_id,
                        'memcached_prefix' => $memcached_prefix,    // debug purposes only
                        'url' => $url,
                        'api_key' => $api_key,
                    )
                );

//print_r($payload);

                $delay = 1; // one second
                $pheanstalk->useTube('csv_export_finalize')->put($payload, $priority, $delay);
            }
            else {
                // Remove finalize marker from ODRAdminBundle:TrackedCSVExport
                $tracked_csv_export = $em->getRepository('ODRAdminBundle:TrackedCSVExport')->findOneBy( array('trackedJob' => $tracked_job_id) );  // should only be one left
                $em->remove($tracked_csv_export);
                $em->flush();

                // TODO - Notify user that export is ready
            }

            $return['d'] = '';
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x32439779 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }


    /**         
     * Sidesteps symfony to set up an CSV file download...
     * 
     * @param integer $user_id The user requesting the download
     * @param integer $tracked_job_id The tracked job that stored the progress of the csv export
     * @param Request $request
     *          
     * @return Response TODO
     */
    public function downloadCSVAction($user_id, $tracked_job_id, Request $request) {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = ''; 
            
        $response = new Response();
        
        try {
            // Grab necessary objects
            $em = $this->getDoctrine()->getManager();
            $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->find($tracked_job_id);
            if ($tracked_job == null)
                return parent::deletedEntityError("Job");

            $job_type = $tracked_job->getJobType();
            if ($job_type !== 'csv_export')
                return parent::deletedEntityError("Job");

            $target_entity = $tracked_job->getTargetEntity();
            $tmp = explode('_', $target_entity);
            $datatype = $em->getRepository('ODRAdminBundle:DataType')->find( $tmp[1] );
            if ($datatype == null)
                return parent::deletedEntityError("DataType");

            // --------------------
            // Determine user privileges
            $user = $this->container->get('security.context')->getToken()->getUser();
            $user_permissions = parent::getPermissionsArray($user->getId(), $request);

            // Ensure user has permissions to be doing this
            if ( !(isset($user_permissions[ $datatype->getId() ]) && isset($user_permissions[ $datatype->getId() ][ 'view' ])) )
                return parent::permissionDeniedError();
            // --------------------


            $csv_export_path = dirname(__FILE__).'/../../../../web/uploads/csv_export/';
            $filename = 'export_'.$user_id.'_'.$tracked_job_id.'.csv';

            $handle = fopen($csv_export_path.$filename, 'r');
            if ($handle !== false) {
        
                // Set up a response to send the file back
                $response->setPrivate();
                $response->headers->set('Content-Type', mime_content_type($csv_export_path.$filename));
                $response->headers->set('Content-Length', filesize($csv_export_path.$filename));
                $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'";');
    
                $response->sendHeaders();

                $content = file_get_contents($csv_export_path.$filename);   // using file_get_contents() because apparently readfile() tacks on # of bytes read at end of file for firefox
                $response->setContent($content);

                fclose($handle);
            }
            else {
                throw new \Exception('Could not open requested file');
            }
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x848128635 ' . $e->getMessage();
        }

        if ($return['r'] !== 0) {
            // If error encountered, do a json return
            $response = new Response(json_encode($return));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        else {
            // Return the previously created response
            return $response;
        }

    }

}
