<?php

/**
 * Open Data Repository Data Publisher
 * Worker Controller
 * (C) 2015 by Nathan Stone (nate.stone@opendatarepository.org)
 * (C) 2015 by Alex Pires (ajpires@email.arizona.edu)
 * Released under the GPLv2
 *
 * The worker controller holds all of the functions that are called
 * by the worker processes, excluding those in the XML, CSV, and
 * MassEdit controllers.
 *
 */

namespace ODR\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

// Entities
use ODR\AdminBundle\Entity\DataFields;
use ODR\AdminBundle\Entity\DataRecord;
use ODR\AdminBundle\Entity\DataTree;
use ODR\AdminBundle\Entity\DataType;
use ODR\AdminBundle\Entity\FieldType;
use ODR\AdminBundle\Entity\File;
use ODR\AdminBundle\Entity\Image;
use ODR\AdminBundle\Entity\ImageSizes;
use ODR\AdminBundle\Entity\Layout;
use ODR\AdminBundle\Entity\LayoutData;
use ODR\AdminBundle\Entity\LayoutMeta;
use ODR\AdminBundle\Entity\RadioSelection;
use ODR\AdminBundle\Entity\Theme;
use ODR\AdminBundle\Entity\TrackedJob;
use ODR\OpenRepository\UserBundle\Entity\User;
// Forms
// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class WorkerController extends ODRCustomController
{

    /**
     * Called by the recaching background process to rebuild all the different versions of a DataRecord and store them in memcached.
     * @deprecated
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function recacherecordAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        $ret = '';

        try {

            throw new \Exception('DO NOT CONTINUE [DEPRECATED]');

            $post = $_POST;
            if ( !isset($post['tracked_job_id']) || !isset($post['datarecord_id']) || !isset($post['api_key']) || !isset($post['scheduled_at']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $datarecord_id = $post['datarecord_id'];
            $api_key = $post['api_key'];
//            $scheduled_at = \DateTime::createFromFormat('Y-m-d H:i:s', $post['scheduled_at']);
//            $delay = new \DateInterval( 'PT1S' );    // one second delay

            // Load symfony objects
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
//            $pheanstalk = $this->get('pheanstalk');
            $logger = $this->get('logger');
            $redis = $this->container->get('snc_redis.default');;
            // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            $redis_prefix = $this->container->getParameter('memcached_key_prefix');

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $repo_tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob');

            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');


            // ----------------------------------------
            // Grab necessary objects
            $block = false;
            /** @var DataRecord $datarecord */
            $datarecord = $em->getRepository('ODRAdminBundle:DataRecord')->find($datarecord_id);
            if ($datarecord == null)
                throw new \Exception('RecacheRecordCommand.php: Recache request for deleted DataRecord '.$datarecord_id.', skipping');

            if ($datarecord->getProvisioned() == true)
                throw new \Exception('RecacheRecordCommand.php: Recache request for provisioned Datarecord '.$datarecord_id.', skipping');

            $datatype = $datarecord->getDataType();
            if ($datatype->getDeletedAt() !== null)
                throw new \Exception('RecacheRecordCommand.php: Recache request involving DataRecord '.$datarecord_id.' requires deleted DataType, skipping');
            $datatype_id = $datatype->getId();


            // ----------------------------------------
            // See if there are migration jobs in progress for this datatype
            $tracked_job = $repo_tracked_job->findOneBy( array('job_type' => 'migrate', 'restrictions' => 'datatype_'.$datatype_id, 'completed' => null) );
            if ($tracked_job !== null) {
                $target_entity = $tracked_job->getTargetEntity();
                $tmp = explode('_', $target_entity);
                $datafield_id = $tmp[1];

                $ret = 'RecacheRecordCommand.php: Datafield '.$datafield_id.' is currently being migrated to a different fieldtype...'."\n";
                $return['r'] = 2;
                $block = true;
            }

            $tracked_job = null;
            $tracked_job_target = null;
            if (!$block) {
                // TODO - can this get moved to later, or does it have to stay here...STAYS HERE, UNTIL SYSTEM NEEDS TO GET TORN APART
                // Stores tracked job target incase ODRCustomController:updateDatatypeCache() starts another update while this action is running
                if ($tracked_job_id !== -1) {
                    $tracked_job = $repo_tracked_job->find($tracked_job_id);
                    if ($tracked_job !== null)
                        $tracked_job_target = $tracked_job->getRestrictions();
                }
            }
            /** @var TrackedJob $tracked_job */


            // ----------------------------------------
            if (!$block) {

                // ----------------------------------------
                // Ensure the memcached version of the datarecord's datatype exists
                $datatype_data = parent::getRedisData(($redis->get($redis_prefix.'.cached_datatype_'.$datatype_id)));
                if ($datatype_data == false)
                    self::getDatatypeData($em, self::getDatatreeArray($em), $datatype_id);

                // Ensure the memcached version of the datarecord exists
                $datarecord_data = parent::getRedisData(($redis->get($redis_prefix.'.cached_datarecord_'.$datarecord_id)));
                if ($datarecord_data == false)
                    self::getDatarecordData($em, $datarecord_id);

                // Ensure the memcached version of the datarecord's
                $datarecord_table_data = parent::getRedisData(($redis->get($redis_prefix.'.datarecord_table_data_'.$datarecord_id)));
                if ($datarecord_table_data == false)
                    self::Text_GetDisplayData($em, $datarecord_id, $request);

                // Also recreate the XML version of the datarecord
                $xml_export_path = dirname(__FILE__).'/../../../../web/uploads/xml_export/';

                // Ensure directory exists
                if ( !file_exists($xml_export_path) )
                    mkdir( $xml_export_path );

                $filename = 'DataRecord_'.$datarecord_id.'.xml';
                $handle = fopen($xml_export_path.$filename, 'w');
                if ($handle !== false) {
                    $content = parent::XML_GetDisplayData($em, $datarecord->getId(), $request);
                    fwrite($handle, $content);
                    fclose($handle);
                }

$ret .= '>> Recached DataRecord '.$datarecord->getId()."\n";
$logger->info('WorkerController::recacherecordAction() >> Recached DataRecord '.$datarecord->getId());

                // Update the job tracker if necessary
                if ($tracked_job_id !== -1 && $tracked_job !== null) {

                    $em->refresh($tracked_job);

$ret .= '  original tracked_job_target: '.$tracked_job_target.'  current tracked_job_target: '.$tracked_job->getRestrictions()."\n";

                    if ( $tracked_job !== null && intval($tracked_job_target) == intval($tracked_job->getRestrictions()) ) {
                        $total = $tracked_job->getTotal();
                        $count = $tracked_job->incrementCurrent($em);

                        if ($count >= $total)
                            $tracked_job->setCompleted( new \DateTime() );
                        
                        $em->persist($tracked_job);
                        $em->flush();
$ret .= '  Set current to '.$count."\n";
                    }
                }

            }
            else {
$ret = '>> Ignored update request for DataRecord '.$datarecord->getId()."\n";
$logger->info('WorkerController::recacherecordAction() >> Ignored update request for DataRecord '.$datarecord->getId());
            }

            $return['d'] = $ret;
        }
        catch (\Exception $e) {
            // TODO - increment tracked job counter on error?

            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x6642397853 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Called by the migration background process to transfer data from one storage entity to another compatible storage entity.
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function migrateAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        $ret = '';

        try {
            $post = $_POST;
//print_r($post);
            if ( !isset($post['tracked_job_id']) || !isset($post['datarecord_id']) || !isset($post['datafield_id']) || !isset($post['user_id']) || !isset($post['old_fieldtype_id']) || !isset($post['new_fieldtype_id']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $datarecord_id = $post['datarecord_id'];
            $datafield_id = $post['datafield_id'];
            $user_id = $post['user_id'];
            $old_fieldtype_id = $post['old_fieldtype_id'];
            $new_fieldtype_id = $post['new_fieldtype_id'];
            $api_key = $post['api_key'];

            // Load symfony objects
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
//            $pheanstalk = $this->get('pheanstalk');
            $logger = $this->get('logger');
            $redis = $this->container->get('snc_redis.default');;
            // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            $redis_prefix = $this->container->getParameter('memcached_key_prefix');

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $repo_user = $this->getDoctrine()->getRepository('ODROpenRepositoryUserBundle:User');
            $repo_fieldtype = $em->getRepository('ODRAdminBundle:FieldType');
            $repo_datarecord = $em->getRepository('ODRAdminBundle:DataRecord');
            $repo_datafield = $em->getRepository('ODRAdminBundle:DataFields');
            $repo_datarecordfields = $em->getRepository('ODRAdminBundle:DataRecordFields');
            $repo_radio_selection = $em->getRepository('ODRAdminBundle:RadioSelection');

            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            $ret = '';

            // Grab necessary objects
            /** @var User $user */
            $user = $repo_user->find( $user_id );
            /** @var DataRecord $datarecord */
            $datarecord = $repo_datarecord->find( $datarecord_id );
            /** @var DataFields $datafield */
            $datafield = $repo_datafield->find( $datafield_id );
            $em->refresh($datafield);

            /** @var FieldType $old_fieldtype */
            $old_fieldtype = $repo_fieldtype->find( $old_fieldtype_id );
            $old_typeclass = $old_fieldtype->getTypeClass();
            /** @var FieldType $new_fieldtype */
            $new_fieldtype = $repo_fieldtype->find( $new_fieldtype_id );
            $new_typeclass = $new_fieldtype->getTypeClass();

            // Ensure datarecord/datafield pair exist
            if ($datarecord == null)
                throw new \Exception('Datarecord '.$datarecord_id.' is deleted');
            if ($datafield == null)
                throw new \Exception('Datafield '.$datafield_id.' is deleted');


            // Radio options need typename to distinguish...
            $old_typename = $old_fieldtype->getTypeName();
            $new_typename = $new_fieldtype->getTypeName();
            if ($old_typename == $new_typename)
                throw new \Exception('Not allowed to migrate between the same Fieldtype');

            // Need to handle radio options separately...
            if ( ($old_typename == 'Multiple Radio' || $old_typename == 'Multiple Select') && ($new_typename == 'Single Radio' || $new_typename == 'Single Select') ) {
                // If migrating from multiple radio/select to single radio/select, and more than one radio option selected...then need to deselect all but one option

                // Grab all selected radio options
                $query = $em->createQuery(
                   'SELECT dr.id AS dr_id, rs.id AS rs_id, rs.selected AS selected, ro.id AS ro_id, rom.optionName AS option_name
                    FROM ODRAdminBundle:DataRecord AS dr
                    JOIN ODRAdminBundle:DataRecordFields AS drf WITH drf.dataRecord = dr
                    JOIN ODRAdminBundle:RadioSelection AS rs WITH rs.dataRecordFields = drf
                    JOIN ODRAdminBundle:RadioOptions AS ro WITH rs.radioOption = ro
                    JOIN ODRAdminBundle:RadioOptionsMeta AS rom WITH rom.radioOption = ro
                    WHERE drf.dataRecord = :datarecord AND drf.dataField = :datafield AND rs.selected = 1
                    AND dr.deletedAt IS NULL AND drf.deletedAt IS NULL AND rs.deletedAt IS NULL AND ro.deletedAt IS NULL AND rom.deletedAt IS NULL
                    ORDER BY rom.displayOrder, ro.id'
                )->setParameters( array('datarecord' => $datarecord->getId(), 'datafield' => $datafield->getId()) );
                $results = $query->getArrayResult();

                // If more than one radio option selected...
                if ( count($results) > 1 ) {
                    // ...deselect all but the first one in the list
                    for ($i = 1; $i < count($results); $i++) {
                        $rs_id = $results[$i]['rs_id'];
                        $ro_id = $results[$i]['ro_id'];
                        $option_name = $results[$i]['option_name'];

                        /** @var RadioSelection $radio_selection */
                        $radio_selection = $repo_radio_selection->find($rs_id);

                        if ($radio_selection->getSelected() == 1) {
                            // Ensure this RadioSelection is unselected
                            $properties = array('selected' => 0);
                            parent::ODR_copyRadioSelection($em, $user, $radio_selection, $properties);

                            $ret .= '>> Deselected Radio Option '.$ro_id.' ('.$option_name.')'."\n";
                        }
                    }
                    $em->flush();
                }
            }
            else if ( $new_typeclass !== 'Radio' ) {
                // Grab the source entity repository
                $src_repository = $em->getRepository('ODRAdminBundle:'.$old_typeclass);

                // Grab the entity that needs to be migrated
                $src_entity = $src_repository->findOneBy(array('dataField' => $datafield->getId(), 'dataRecord' => $datarecord->getId()));

                // No point migrating anything if the src entity doesn't exist in the first place...would be no data in it
                if ($src_entity !== null) {
                    $logger->info('WorkerController::migrateAction() >> Attempting to migrate data from "'.$old_typeclass.'" '.$src_entity->getId().' to "'.$new_typeclass.'"');
                    $ret .= '>> Attempting to migrate data from "'.$old_typeclass.'" '.$src_entity->getId().' to "'.$new_typeclass.'"'."\n";

                    $value = null;
                    if ( ($old_typeclass == 'ShortVarchar' || $old_typeclass == 'MediumVarchar' || $old_typeclass == 'LongVarchar' || $old_typeclass == 'LongText') && ($new_typeclass == 'ShortVarchar' || $new_typeclass == 'MediumVarchar' || $new_typeclass == 'LongVarchar' || $new_typeclass == 'LongText') ) {
                        // text -> text requires nothing special
                        $value = $src_entity->getValue();
                    }
                    else if ( ($old_typeclass == 'IntegerValue' || $old_typeclass == 'DecimalValue') && ($new_typeclass == 'ShortVarchar' || $new_typeclass == 'MediumVarchar' || $new_typeclass == 'LongVarchar' || $new_typeclass == 'LongText') ) {
                        // number -> text is easy
                        $value = strval($src_entity->getValue());
                    }
                    else if ($old_typeclass == 'IntegerValue' && $new_typeclass == 'DecimalValue') {
                        // integer -> decimal
                        $value = floatval($src_entity->getValue());
                    }
                    else if ($old_typeclass == 'DecimalValue' && $new_typeclass == 'IntegerValue') {
                        // decimal -> integer
                        $value = intval($src_entity->getValue());
                    }
                    else if ( ($old_typeclass == 'ShortVarchar' || $old_typeclass == 'MediumVarchar' || $old_typeclass == 'LongVarchar' || $old_typeclass == 'LongText') && ($new_typeclass == 'IntegerValue') ) {
                        // text -> integer
                        $pattern = '/[^0-9\.\-]+/i';
                        $replacement = '';
                        $new_value = preg_replace($pattern, $replacement, $src_entity->getValue());

                        $value = intval($new_value);
                    }
                    else if ( ($old_typeclass == 'ShortVarchar' || $old_typeclass == 'MediumVarchar' || $old_typeclass == 'LongVarchar' || $old_typeclass == 'LongText') && ($new_typeclass == 'DecimalValue') ) {
                        // text -> decimal
                        $pattern = '/[^0-9\.\-]+/i';
                        $replacement = '';
                        $new_value = preg_replace($pattern, $replacement, $src_entity->getValue());

                        $value = floatval($new_value);
                    }
                    else if ( $old_typeclass == 'DatetimeValue' ) {
                        // date -> anything
                        $value = null;
                    }
                    else if ( $new_typeclass == 'DatetimeValue' ) {
                        // anything -> date
                        $value = new \DateTime('9999-12-31 00:00:00');
                    }

                    // Save changes
                    if ( $new_typeclass == 'DatetimeValue' )
                        $ret .= 'set dest_entity to "'.$value->format('Y-m-d H:i:s').'"'."\n";
                    else
                        $ret .= 'set dest_entity to "'.$value.'"'."\n";
                    $em->remove($src_entity);

                    $new_obj = parent::ODR_addStorageEntity($em, $user, $datarecord, $datafield);

                    parent::ODR_copyStorageEntity($em, $user, $new_obj, array('value' => $value));


                    // TODO - code to directly update the cached version of the datarecord
                    // Locate and clear the cache entry for this datarecord
                    parent::tmp_updateDatarecordCache($em, $datarecord->getGrandparent(), $user);
                }
                else {
                    $ret .= '>> No '.$old_typeclass.' source entity for datarecord "'.$datarecord->getId().'" datafield "'.$datafield->getId().'", skipping'."\n";
                }
            }

            // ----------------------------------------
            // Update the job tracker if necessary
            if ($tracked_job_id !== -1) {
                $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->find($tracked_job_id);

                $total = $tracked_job->getTotal();
                $count = $tracked_job->incrementCurrent($em);

                if ($count >= $total)
                    $tracked_job->setCompleted( new \DateTime() );

                $em->persist($tracked_job);
                $em->flush();
$ret .= '  Set current to '.$count."\n";
            }


            // ----------------------------------------
            // Delete the cached versions of this datarecord
            parent::tmp_updateDatarecordCache($em, $datarecord, $user);

            $return['d'] = $ret;
        }
        catch (\Exception $e) {
            // TODO - increment tracked job counter on error?

            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x6642397856: '.$e->getMessage()."\n".$ret;
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Begins the process of rebuilding the image thumbnails for a specific datatype.
     * 
     * @param integer $datatype_id Which datatype should have all its image thumbnails rebuilt
     * @param Request $request
     *
     * @return Response
     */
    public function startrebuildthumbnailsAction($datatype_id, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $pheanstalk = $this->get('pheanstalk');
            $router = $this->container->get('router');
            $redis = $this->container->get('snc_redis.default');;
            // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            $redis_prefix = $this->container->getParameter('memcached_key_prefix');
            $api_key = $this->container->getParameter('beanstalk_api_key');

            /** @var DataType $datatype */
            $datatype = $em->getRepository('ODRAdminBundle:DataType')->find($datatype_id);
            if ($datatype == null)
                return parent::deletedEntityError('DataType');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.token_storage')->getToken()->getUser();
//            $user_permissions = parent::getPermissionsArray($user->getId(), $request);
            // TODO - check for permissions?  restrict rebuild of thumbnails to certain datatypes?

            if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
                return parent::permissionDeniedError();
            // --------------------


            // ----------------------------------------
            // Generate the url for cURL to use
            $url = $this->container->getParameter('site_baseurl');
            $url .= $router->generate('odr_rebuild_thumbnails');

            // Grab a list of all full-size images on the site
            $query = $em->createQuery(
               'SELECT e.id
                FROM ODRAdminBundle:Image AS e
                JOIN ODRAdminBundle:DataRecord AS dr WITH e.dataRecord = dr
                WHERE dr.dataType = :datatype AND e.parent IS NULL
                AND e.deletedAt IS NULL AND dr.deletedAt IS NULL'
            )->setParameters(array('datatype' => $datatype_id));
            $results = $query->getArrayResult();

//print_r($results);
//return;

            if (count($results) > 0) {
                // ----------------------------------------
                // Get/create an entity to track the progress of this thumbnail rebuild
                $job_type = 'rebuild_thumbnails';
                $target_entity = 'datatype_'.$datatype_id;
                $additional_data = array('description' => 'Rebuild of all image thumbnails for DataType '.$datatype_id);
                $restrictions = '';
                $total = count($results);
                $reuse_existing = false;

                $tracked_job = parent::ODR_getTrackedJob($em, $user, $job_type, $target_entity, $additional_data, $restrictions, $total, $reuse_existing);
                $tracked_job_id = $tracked_job->getId();

                // ----------------------------------------
                $object_type = 'image';
                foreach ($results as $num => $result) {
                    $object_id = $result['id'];

                    // Insert the new job into the queue
                    $priority = 1024;   // should be roughly default priority
                    $payload = json_encode(
                        array(
                            "tracked_job_id" => $tracked_job_id,
                            "object_type" => $object_type,
                            "object_id" => $object_id,
                            "redis_prefix" => $redis_prefix,    // debug purposes only
                            "url" => $url,
                            "api_key" => $api_key,
                        )
                    );

                    $delay = 1;
                    $pheanstalk->useTube('rebuild_thumbnails')->put($payload, $priority, $delay);
                }
            }

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x38472782 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Called by the rebuild_thumbnails worker process to rebuild the thumbnails of one of the uploaded images on the site. 
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function rebuildthumbnailsAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = "";
        $return['d'] = "";

        $tracked_job_id = -1;

        try {
            $post = $_POST;
            if ( !isset($post['tracked_job_id']) || !isset($post['object_type']) || !isset($post['object_id']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $tracked_job_id = intval($post['tracked_job_id']);
            $object_type = $post['object_type'];
            $object_id = $post['object_id'];
            $api_key = $post['api_key'];

            // Load symfony objects
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            // ----------------------------------------
            // Ensure the full-size image exists
            parent::decryptObject($object_id, $object_type);

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var Image $img */
            $img = $em->getRepository('ODRAdminBundle:Image')->find($object_id);
            if ($img == null)
                throw new \Exception('Image '.$object_id.' has been deleted');

            /** @var User $user */
            $user = $em->getRepository('ODROpenRepositoryUserBundle:User')->find(2);    // TODO - need an actual system user...


            // Ensure an ImageSizes entity exists for this image
            /** @var ImageSizes[] $image_sizes */
            $image_sizes = $em->getRepository('ODRAdminBundle:ImageSizes')->findBy( array('dataFields' => $img->getDataField()->getId()) );
            if ( count($image_sizes) == 0 ) {
                // Create missing ImageSizes entities for this datafield
                parent::ODR_checkImageSizes($em, $user, $img->getDataField());

                // Reload the newly created ImageSizes for this datafield
                while ( count($image_sizes) == 0 ) {
                    sleep(1);   // wait a second so whichever process is creating the ImageSizes entities has time to finish
                    $image_sizes = $em->getRepository('ODRAdminBundle:ImageSizes')->findBy( array('dataFields' => $img->getDataField()->getId()) );
                }

                // Set this image to point to the correct ImageSizes entity, since it didn't exist before
                foreach ($image_sizes as $size) {
                    if ($size->getOriginal() == true) {
                        $img->setImageSize($size);
                        $em->persist($img);
                    }
                }

                $em->flush($img);
                $em->refresh($img);
            }

            // Recreate the thumbnail from the full-sized image
            parent::resizeImages($img, $user);


            // ----------------------------------------
            // Update the job tracker if necessary
            if ($tracked_job_id !== -1) {
                /** @var TrackedJob $tracked_job */
                $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->find($tracked_job_id);

                if ($tracked_job !== null) {
                    $total = $tracked_job->getTotal();
                    $count = $tracked_job->incrementCurrent($em);

                    if ($count >= $total)
                        $tracked_job->setCompleted(new \DateTime());

                    $em->persist($tracked_job);
                    $em->flush();
//$ret .= '  Set current to '.$count."\n";
                }
            }

            $return['d'] = '>> Rebuilt thumbnails for '.$object_type.' '.$object_id."\n";
        }
        catch (\Exception $e) {
            // Update the job tracker even if an error occurred...right? TODO
            if ($tracked_job_id !== -1) {
                $em = $this->getDoctrine()->getManager();
                /** @var TrackedJob $tracked_job */
                $tracked_job = $em->getRepository('ODRAdminBundle:TrackedJob')->find($tracked_job_id);

                if ($tracked_job !== null) {
                    $total = $tracked_job->getTotal();
                    $count = $tracked_job->incrementCurrent($em);

                    if ($count >= $total)
                        $tracked_job->setCompleted(new \DateTime());

                    $em->persist($tracked_job);
                    $em->flush();
                }
            }

            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x38472782 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Performs an asynchronous encrypt or decrypt on a specified file.  Also has the option
     *
     * @param Request $request
     *
     * @return Response
     */
    public function cryptorequestAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = "";
        $return['d'] = "";

        $error_prefix = 'Error 0x65384782: ';
        $handle = null;

        try {
            $post = $_POST;
//print_r($post);
//return;
            if ( !isset($post['crypto_type']) || !isset($post['object_type']) || !isset($post['object_id']) || !isset($post['target_filename']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $crypto_type = $post['crypto_type'];
            $object_type = strtolower( $post['object_type'] );
            $object_id = $post['object_id'];
            $target_filename = $post['target_filename'];
            $api_key = $post['api_key'];

            $error_prefix .= $crypto_type.' for '.$object_type.' '.$object_id.'...';

            // These two are only used if the files are being decrypted into a zip archive
            $archive_filepath = '';
            if ( isset($post['archive_filepath']) )
                $archive_filepath = $post['archive_filepath'];

            $desired_filename = '';
            if ( isset($post['desired_filename']) )
                $desired_filename = $post['desired_filename'];


            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            if ( !is_numeric($post['object_id']) )
                throw new \Exception('$object_id is not numeric');
            else
                $object_id = intval($object_id);

            $base_obj = null;
            if ($object_type == 'file')
                $base_obj = $em->getRepository('ODRAdminBundle:File')->find($object_id);
//            else if ($object_type == 'image')
//                $base_obj = $em->getRepository('ODRAdminBundle:Image')->find($object_id);

            // NOTE - encryption after image upload is currently done inline in ODRCustomController::finishUploadAction()
            // Also, they're decrypted inline when needed...if they were done asynch, the browser couldn't display non-public versions in <img> tags


            if ($base_obj == null)
                throw new \Exception('could not load object '.$object_id.' of type "'.$object_type.'"');
            /** @var File|Image $base_obj */


            // ----------------------------------------
            if ($crypto_type == 'encrypt') {

                // ----------------------------------------
                // Move file from completed directory to decrypted directory in preparation for encryption...
                $destination_path = dirname(__FILE__).'/../../../../web';
                $destination_filename = $base_obj->getUploadDir().'/File_'.$object_id.'.'.$base_obj->getExt();
                rename( $base_obj->getLocalFileName(), $destination_path.'/'.$destination_filename );

                // Update local filename and checksum in database...
                $base_obj->setLocalFileName($destination_filename);

                $original_checksum = md5_file($destination_path.'/'.$destination_filename);
                $base_obj->setOriginalChecksum($original_checksum);

                // Encryption of a given file/image is simple...
                parent::encryptObject($object_id, $object_type);

                if ($object_type == 'file') {
                    $base_obj->setProvisioned(false);

                    $em->persist($base_obj);
                    $em->flush();
                    $em->refresh($base_obj);
                }

                // Update the datarecord cache so whichever controller is handling the "are you done encrypting yet?" javascript requests can return the correct HTML
                $datarecord = $base_obj->getDataRecord();
                parent::tmp_updateDatarecordCache($em, $datarecord, $base_obj->getCreatedBy());
            }
            else if ($crypto_type == 'decrypt') {
                // This is (currently) the only request the user has made for this file...begin manually decrypting it because the crypto bundle offers limited control over filenames
                $crypto = $this->get("dterranova_crypto.crypto_adapter");
                $crypto_dir = dirname(__FILE__).'/../../../../app/crypto_dir/';     // TODO - load from config file somehow?
                $crypto_dir .= 'File_'.$object_id;

                $base_filepath = dirname(__FILE__).'/../../../../web/'.$base_obj->getUploadDir();
                $local_filepath = $base_filepath.'/'.$target_filename;

                // Don't decrypt the file if it already exists on the server
                if ( !file_exists($local_filepath) ) {
                    // Grab the hex string representation that the file was encrypted with
                    $key = $base_obj->getEncryptKey();
                    // Convert the hex string representation to binary...php had a function to go bin->hex, but didn't have a function for hex->bin for at least 7 years?!?
                    $key = pack("H*", $key);   // don't have hex2bin() in current version of php...this appears to work based on the "if it decrypts to something intelligible, you did it right" theory

                    // Open the target file
                    $handle = fopen($local_filepath, "wb");
                    if (!$handle)
                        throw new \Exception('Unable to open "'.$local_filepath.'" for writing');

                    // Decrypt each chunk and write to target file
                    $chunk_id = 0;
                    while (file_exists($crypto_dir.'/'.'enc.'.$chunk_id)) {
                        if (!file_exists($crypto_dir.'/'.'enc.'.$chunk_id))
                            throw new \Exception('Encrypted chunk not found: '.$crypto_dir.'/'.'enc.'.$chunk_id);

                        $data = file_get_contents($crypto_dir.'/'.'enc.'.$chunk_id);
                        fwrite($handle, $crypto->decrypt($data, $key));
                        $chunk_id++;
                    }
                }

                if ( $archive_filepath == '' ) {
                    $redis = $this->container->get('snc_redis.default');;
                    // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
                    $redis_prefix = $this->container->getParameter('memcached_key_prefix');

                    $file_decryptions = parent::getRedisData(($redis->get($redis_prefix.'_file_decryptions')));

                    unset($file_decryptions[$target_filename]);
                    $redis->set($redis_prefix.'_file_decryptions', gzcompress(serialize($file_decryptions)));
                }
                else {
                    // Attempt to open the specified zip archive
                    $handle = fopen($archive_filepath, 'c');    // create file if it doesn't exist, otherwise do not fail and position pointer at beginning of file
                    if (!$handle)
                        throw new \Exception('unable to open "'.$archive_filepath.'" for writing');

                    // Attempt to acquire a lock on the zip archive so only one process is adding to it at a time
                    $lock = false;
                    while (!$lock) {
                        $lock = flock($handle, LOCK_EX);
                        if (!$lock)
                            usleep(200000);     // sleep for a fifth of a second to try to acquire a lock...
                    }

                    // Open the archive for appending, or create if it doesn't exist
                    $zip_archive = new \ZipArchive();
                    $zip_archive->open($archive_filepath, \ZipArchive::CREATE);

                    // Add the specified file to the zip archive
                    $zip_archive->addFile($local_filepath, $desired_filename);
                    $zip_archive->close();

                    // Delete decrypted version of non-public files off the server
                    if (!$base_obj->isPublic())
                        unlink($local_filepath);

                    // Release the lock on the zip archive
                    flock($handle, LOCK_UN);
                    fclose($handle);
                }
            }
            else {
                throw new \Exception('bad value for $crypto_type, got "'.$crypto_type.'"');
            }

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = $error_prefix.$e->getMessage();

            // TODO - delete the partial non-public file if some sort of error during decryption?

            if ($handle != null) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Debug function...clears existing cached versions of datatypes (optionally for a specific datatype).
     * Should only be used when changes have been made to the structure of the cached array for datatypes
     *
     * @param integer $datatype_id
     * @param Request $request
     *
     * @return Response
     */
    public function dtclearAction($datatype_id, Request $request)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $redis = $this->container->get('snc_redis.default');;
        // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis_prefix = $this->container->getParameter('memcached_key_prefix');

        /** @var User $user */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
            return parent::permissionDeniedError();

        $results = array();
        if ($datatype_id == 0) {
            $query = $em->createQuery(
               'SELECT dt.id AS dt_id
                FROM ODRAdminBundle:DataType AS dt');

            $results = $query->getArrayResult();
        }
        else {
            $results = array('dt_id' => $datatype_id);
        }


        foreach ($results as $result) {
            $dt_id = $result['dt_id'];

            $redis->del($redis_prefix.'.cached_datatype_'.$dt_id);
        }

        $return = array(
            'r' => 0,
            't' => '',
            'd' => '',
        );

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Debug function...clears existing cached versions of datarecords (optionally for a given datatype).
     * Should only be used when changes have been made to the structure of the cached array for datarecords
     *
     * @param integer $datatype_id
     * @param Request $request
     *
     * @return Response
     */
    public function drclearAction($datatype_id, Request $request)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $redis = $this->container->get('snc_redis.default');;
        // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis_prefix = $this->container->getParameter('memcached_key_prefix');

        /** @var User $user */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
            return parent::permissionDeniedError();

        $query = null;
        if ($datatype_id == 0) {
            $query = $em->createQuery(
               'SELECT dr.id AS dr_id
                FROM ODRAdminBundle:DataRecord AS dr');
        }
        else {
            $query = $em->createQuery(
               'SELECT dr.id AS dr_id
                FROM ODRAdminBundle:DataRecord AS dr
                WHERE dr.dataType = :datatype_id'
            )->setParameters( array('datatype_id' => $datatype_id) );
        }
        $results = $query->getArrayResult();

        foreach ($results as $result) {
            $dr_id = $result['dr_id'];

            $redis->del($redis_prefix.'.associated_datarecords_for_'.$dr_id);
            $redis->del($redis_prefix.'.cached_datarecord_'.$dr_id);
            $redis->del($redis_prefix.'.datarecord_table_data_'.$dr_id);
        }

        $return = array(
            'r' => 0,
            't' => '',
            'd' => '',
        );

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Debug function...clears all existing cached search result.
     */
    public function searchclearAction(Request $request)
    {
        $redis = $this->container->get('snc_redis.default');;
        // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis_prefix = $this->container->getParameter('memcached_key_prefix');

        /** @var User $user */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
            return parent::permissionDeniedError();

        $redis->del($redis_prefix.'.cached_search_results');

        $return = array(
            'r' => 0,
            't' => '',
            'd' => '',
        );

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Debug function...clears all existing cached search result.
     */
    public function permissionsclearAction(Request $request)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $redis = $this->container->get('snc_redis.default');;
        // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis_prefix = $this->container->getParameter('memcached_key_prefix');

        /** @var User $user */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
            return parent::permissionDeniedError();

        // ----------------------------------------
        // Clear all cached user permissions
        $query = $em->createQuery(
           'SELECT u.id AS user_id
            FROM ODROpenRepositoryUserBundle:User AS u'
        );
        $results = $query->getArrayResult();

        foreach ($results as $result) {
            $user_id = $result['user_id'];
            $redis->del($redis_prefix.'.user_'.$user_id.'_permissions');
        }


        // ----------------------------------------
        // Clear all cached group permissions
        $query = $em->createQuery(
           'SELECT g.id AS group_id
            FROM ODRAdminBundle:Group AS g'
        );
        $results = $query->getArrayResult();

        foreach ($results as $result) {
            $group_id = $result['group_id'];
            $redis->del($redis_prefix.'.group_'.$group_id.'_permissions');
        }


        $return = array(
            'r' => 0,
            't' => '',
            'd' => '',
        );

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * Begins the process of forcibly (re)encrypting every uploaded file/image on the site
     *
     * @param string $object_type "File" or "Image"...which type of entity to encrypt
     * @param Request $request
     *
     */
    public function startencryptAction($object_type, Request $request)
    {
/*
        $em = $this->getDoctrine()->getManager();
        $pheanstalk = $this->get('pheanstalk');
        $router = $this->container->get('router');
        $redis = $this->container->get('snc_redis.default');;
        $redis_prefix = $this->container->getParameter('memcached_key_prefix');

        $api_key = $this->container->getParameter('beanstalk_api_key');

        // Generate the url for cURL to use
        $url = $this->container->getParameter('site_baseurl');
        $url .= $router->generate('odr_force_encrypt');

        if ($object_type == 'file' || $object_type == 'File')
            $object_type = 'File';
        else if ($object_type == 'image' || $object_type == 'Image')
            $object_type = 'Image';
        else
            return null;

        $query = $em->createQuery(
           'SELECT e.id
            FROM ODRAdminBundle:'.$object_type.' AS e
            WHERE e.deletedAt IS NULL'
        );
        $results = $query->getResult();

//print_r($results);
//return;

        $object_type = strtolower($object_type);
        foreach ($results as $num => $result) {
            $object_id = $result['id'];

            // Insert the new job into the queue
            $priority = 1024;   // should be roughly default priority
            $payload = json_encode(
                array(
                    "object_type" => $object_type,
                    "object_id" => $object_id,
                    "redis_prefix" => $redis_prefix,    // debug purposes only
                    "url" => $url,
                    "api_key" => $api_key,
                )
            );

            $delay = 1;
            $pheanstalk->useTube('mass_encrypt')->put($payload, $priority, $delay);

//return;
        }
*/
    }


    /**
     * Called by the mass_encrypt worker background process to (re)encrypt a single file or image.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function encryptAction(Request $request)
    {
/*
        $return = array();
        $return['r'] = 0;
        $return['t'] = "";
        $return['d'] = "";

        try {
            $post = $_POST;
            if ( !isset($post['object_type']) || !isset($post['object_id']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

            // Pull data from the post
            $object_type = $post['object_type'];
            $object_id = $post['object_id'];
            $api_key = $post['api_key'];

            // Load symfony objects
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');
            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            parent::decryptObject($object_id, $object_type);    // ensure a decrypted object exists prior to attempting to encrypt
            parent::encryptObject($object_id, $object_type);

            $return['d'] = '>> Encrypted '.$object_type.' '.$object_id."\n";
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x38378231 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
*/
    }


    /**
     * Begins the process of forcibly decrypting every uploaded file/image on the site.
     *
     * @param string $object_type "File" or "Image"...which type of entity to encrypt
     * @param Request $request
     *
     */
    public function startdecryptAction($object_type, Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $pheanstalk = $this->get('pheanstalk');
            $router = $this->container->get('router');
            $redis = $this->container->get('snc_redis.default');;
            $redis_prefix = $this->container->getParameter('memcached_key_prefix');

            $api_key = $this->container->getParameter('beanstalk_api_key');

            // Generate the url for cURL to use
            $url = $this->container->getParameter('site_baseurl');
            $url .= $router->generate('odr_crypto_request');

            if ($object_type == 'file' || $object_type == 'File')
                $object_type = 'File';
//            else if ($object_type == 'image' || $object_type == 'Image')
//                $object_type = 'Image';
            else
                return null;

            $query = null;
            if ($object_type == 'File') {
                $query = $em->createQuery(
                    'SELECT e.id
                    FROM ODRAdminBundle:'.$object_type.' AS e
                    WHERE e.deletedAt IS NULL AND (e.original_checksum IS NULL OR e.filesize = 0)'
                );
            }
/*
            else if ($object_type == 'Image') {
                $query = $em->createQuery(
                    'SELECT e.id
                    FROM ODRAdminBundle:'.$object_type.' AS e
                    WHERE e.deletedAt IS NULL AND e.original_checksum IS NULL'
                );
            }
*/
            $results = $query->getResult();

            $object_type = strtolower($object_type);
            foreach ($results as $num => $file) {
                // Insert the new job into the queue
                $priority = 1024;   // should be roughly default priority
                $payload = json_encode(
                    array(
                        "object_type" => $object_type,
                        "object_id" => $file['id'],
                        "target_filename" => '',
                        "crypto_type" => 'decrypt',

                        "archive_filepath" => '',
                        "desired_filename" => '',

                        "redis_prefix" => $redis_prefix,    // debug purposes only
                        "url" => $url,
                        "api_key" => $api_key,
                    )
                );

                $delay = 1;
                $pheanstalk->useTube('crypto_requests')->put($payload, $priority, $delay);
            }

        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x45387831 '.$e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * @param Request $request
     *
     * @return Response
     */
    public function startbuildlayoutdataAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = "";
        $return['d'] = "";

        try {
            // Grab necessary objects
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $pheanstalk = $this->get('pheanstalk');
            $router = $this->container->get('router');

            $redis = $this->container->get('snc_redis.default');;
            $redis_prefix = $this->container->getParameter('memcached_key_prefix');
            // $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);

            $api_key = $this->container->getParameter('beanstalk_api_key');

//            throw new \Exception('DO NOT CONTINUE');

            // --------------------
            // Determine user privileges
            /** @var User $user */
            $user = $this->container->get('security.token_storage')->getToken()->getUser();

            if ( !$user->hasRole('ROLE_SUPER_ADMIN') )
                return parent::permissionDeniedError();
            // --------------------


            // Generate the url for cURL to use
            $url = $this->container->getParameter('site_baseurl');
            $url .= $router->generate('odr_layout_metadata');


            $top_level_datatypes = parent::getTopLevelDatatypes();
            foreach ($top_level_datatypes as $num => $dt_id) {
                // Insert the new job into the queue
                $priority = 1024;   // should be roughly default priority
                $payload = json_encode(
                    array(
                        "object_type" => 'datatype',
                        "object_id" => $dt_id,
                        "redis_prefix" => $redis_prefix,    // debug purposes only
                        "url" => $url,
                        "api_key" => $api_key,
                    )
                );
                $delay = 1;
                $pheanstalk->useTube('build_metadata')->put($payload, $priority, $delay);
            }
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = '0x1878321483: '.$e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * @param Request $request
     *
     * @return Response
     */
    public function buildlayoutdataAction(Request $request)
    {
        $return = array();
        $return['r'] = 0;
        $return['t'] = '';
        $return['d'] = '';

        try {
            $post = $_POST;
            if ( !isset($post['object_type']) || !isset($post['object_id']) || !isset($post['api_key']) )
                throw new \Exception('Invalid Form');

//            throw new \Exception('DO NOT CONTINUE');

            // Pull data from the post
            $object_type = $post['object_type'];
            $object_id = $post['object_id'];
            $api_key = $post['api_key'];

            // Load symfony objects
            $beanstalk_api_key = $this->container->getParameter('beanstalk_api_key');

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $repo_datatree = $em->getRepository('ODRAdminBundle:DataTree');
            $repo_theme = $em->getRepository('ODRAdminBundle:Theme');
            $repo_theme_datatype = $em->getRepository('ODRAdminBundle:ThemeDataType');


            if ($api_key !== $beanstalk_api_key)
                throw new \Exception('Invalid Form');

            $write = false;
//            $write = true;

            /** @var DataType $datatype */
            $datatype = $em->getRepository('ODRAdminBundle:DataType')->find($object_id);
//            if ($datatype == null)
//                throw new \Exception('Deleted Datatype');


$ret = 'Creating layouts for datatype '.$object_id.'...'."\n";

            /** @var Theme[] $themes */
            $themes = $datatype->getThemes();
            foreach ($themes as $theme) {
                if ($theme->getThemeType() == "master") {
$ret .= ' - created new "master" layout based off Theme '.$theme->getId()."\n";
                    $layout = new Layout();
                    $layout->setDataType($datatype);
                    $layout->setIsTableLayout(false);
                    $layout->setIsBaseLayout(true);

                    $layout->setCreated( $theme->getCreated() );
                    $layout->setCreatedBy( $theme->getCreatedBy() );
                    $layout->setUpdated( $theme->getUpdated() );
                    $layout->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout);
                        $em->flush();
                        $em->refresh($layout);
                    }

                    $layout_meta = new LayoutMeta();
                    $layout_meta->setLayoutName('');
                    $layout_meta->setLayoutDescription('');
                    $layout_meta->setPublicDate( $theme->getCreated() );
                    $layout_meta->setIsOfficial(true);  // no user-generated themes yet, so consider all of these to be "official"

                    $layout_meta->setIsSearchDefault(false);
                    // Currently only one "master" theme, so have this layout become the default view/edit layout
                    $layout_meta->setIsViewDefault(true);
                    $layout_meta->setIsEditDefault(true);

                    $layout_meta->setHasSearchIntent(false);

                    $layout_meta->setLayout($layout);

                    $layout_meta->setCreated( $theme->getCreated() );
                    $layout_meta->setCreatedBy( $theme->getCreatedBy() );
                    $layout_meta->setUpdated( $theme->getUpdated() );
                    $layout_meta->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_meta);
                        $em->flush();
                    }

$ret .= '   > created default LayoutData entry'."\n";
                    $layout_data = new LayoutData();
                    $layout_data->setLayout($layout);
                    $layout_data->setTheme($theme);
                    $layout_data->setDataTree(null);

                    $layout_data->setDisplayType(0);    // not going to be used, set to accordion

                    $layout_data->setCreated( $theme->getCreated() );
                    $layout_data->setCreatedBy( $theme->getCreatedBy() );
                    $layout_data->setUpdated( $theme->getUpdated() );
                    $layout_data->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_data);
                        $em->flush();
                    }

                    // Now, need to create LayoutData entries for child/linked datatypes of this top-level datatype...
                    /** @var DataTree[] $tmp */
                    $tmp = $repo_datatree->findBy( array('ancestor' => $datatype->getId()) );

                    $datatree_entries = array($datatype->getId() => array());
                    foreach ($tmp as $dt)
                        $datatree_entries[ $datatype->getId() ] [$dt->getId() ] = $dt;

                    while ( count($datatree_entries) > 0 ) {
                        $new_datatree_entries = array();
                        foreach ($datatree_entries as $ancestor_datatype_id => $dt_list) {
                            /** @var DataTree[] $dt_list */
                            foreach ($dt_list as $dt_id => $dt) {
                                // These properties are straight off the datatree entry...
                                $is_link = $dt->getIsLink();
                                $descendant_datatype_id = $dt->getDescendant()->getId();

                                // Due to the current structure of the database, only need to look up the "master" theme for the descendant...doesn't matter whether it's a child or a linked datatype
                                /** @var Theme $child_master_theme */
                                $child_master_theme = $repo_theme->findOneBy(array('dataType' => $descendant_datatype_id, 'themeType' => 'master'));

                                // Finally, need to locate the original theme_datatype entry to extract the accordion/tabbed/dropdown/list flag
                                $query = $em->createQuery(
                                   'SELECT tdt.display_type AS display_type
                                    FROM ODRAdminBundle:ThemeDataType AS tdt
                                    JOIN ODRAdminBundle:ThemeElement AS te WITH tdt.themeElement = te
                                    JOIN ODRAdminBundle:Theme AS t WITH te.theme = t
                                    WHERE tdt.dataType = :descendant_datatype AND t.dataType = :ancestor_datatype AND t.themeType = :theme_type
                                    AND tdt.deletedAt IS NULL AND te.deletedAt IS NULL AND t.deletedAt IS NULL'
                                )->setParameters(array('descendant_datatype' => $descendant_datatype_id, 'ancestor_datatype' => $ancestor_datatype_id, 'theme_type' => 'master'));
                                $results = $query->getArrayResult();

                                $child_display_type = $results[0]['display_type'];
if (!$is_link)
    $ret .= '   > created LayoutData entry for ancestor datatype '.$ancestor_datatype_id.' child datatype '.$descendant_datatype_id.' pointing to Theme '.$child_master_theme->getId().', display_type == '.$child_display_type."\n";
else
    $ret .= '   > created LayoutData entry for ancestor datatype '.$ancestor_datatype_id.' linked datatype '.$descendant_datatype_id.' pointing to Theme '.$child_master_theme->getId().', display_type == '.$child_display_type."\n";

                                $layout_data = new LayoutData();
                                $layout_data->setLayout($layout);
                                $layout_data->setTheme($child_master_theme);
                                $layout_data->setDataTree($dt);

                                $layout_data->setDisplayType($child_display_type);

                                $layout_data->setCreated( $theme->getCreated() );
                                $layout_data->setCreatedBy( $theme->getCreatedBy() );
                                $layout_data->setUpdated( $theme->getUpdated() );
                                $layout_data->setUpdatedBy( $theme->getUpdatedBy() );

                                if ($write) {
                                    $em->persist($layout_data);
                                    $em->flush();
                                }

                                // Also locate any children of this child datatype
                                /** @var DataTree[] $grandchild_datatree_entries */
                                $grandchild_datatree_entries = $repo_datatree->findBy( array('ancestor' => $descendant_datatype_id) );
                                if ( count($grandchild_datatree_entries) > 0 ) {
                                    if ( !isset($new_datatree_entries[$descendant_datatype_id]) )
                                        $new_datatree_entries[$descendant_datatype_id] = array();

                                    foreach ($grandchild_datatree_entries as $g_dt)
                                        $new_datatree_entries[$descendant_datatype_id][ $g_dt->getId() ] = $g_dt;
                                }
                            }
                        }

                        $datatree_entries = $new_datatree_entries;
                    }
                }
                else if ($theme->getThemeType() == "search_results") {
$ret .= ' - created new "derivative" layout for search purposes based off Theme '.$theme->getId();
                    $layout = new Layout();
                    $layout->setDataType($datatype);
                    $layout->setIsTableLayout(false);
                    $layout->setIsBaseLayout(false);

                    $layout->setCreated( $theme->getCreated() );
                    $layout->setCreatedBy( $theme->getCreatedBy() );
                    $layout->setUpdated( $theme->getUpdated() );
                    $layout->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout);
                        $em->flush();
                        $em->refresh($layout);
                    }

                    $layout_meta = new LayoutMeta();
                    $layout_meta->setLayoutName('');
                    $layout_meta->setLayoutDescription('');
                    $layout_meta->setPublicDate( $theme->getCreated() );
                    $layout_meta->setIsOfficial(true);  // no user-generated themes yet, so consider all of these to be "official"

                    // Only set this layout as search default if the datatype was originally set to use this specific search result theme as it's search result display
                    if ($datatype->getUseShortResults() == true && $theme->getIsDefault() == true) {
$ret .= ' (set as search default)';
                        $layout_meta->setIsSearchDefault(true);
                    }
                    else {
                        $layout_meta->setIsSearchDefault(false);
                    }
$ret .= "\n";

                    $layout_meta->setIsViewDefault(false);
                    $layout_meta->setIsEditDefault(false);

                    $layout_meta->setHasSearchIntent(true);

                    $layout_meta->setLayout($layout);

                    $layout_meta->setCreated( $theme->getCreated() );
                    $layout_meta->setCreatedBy( $theme->getCreatedBy() );
                    $layout_meta->setUpdated( $theme->getUpdated() );
                    $layout_meta->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_meta);
                        $em->flush();
                    }

$ret .=  '   > created default LayoutData entry'."\n";
                    $layout_data = new LayoutData();
                    $layout_data->setLayout($layout);
                    $layout_data->setTheme($theme);
                    $layout_data->setDataTree(null);

                    $layout_data->setDisplayType(0);    // not going to be used, set to accordion

                    $layout_data->setCreated( $theme->getCreated() );
                    $layout_data->setCreatedBy( $theme->getCreatedBy() );
                    $layout_data->setUpdated( $theme->getUpdated() );
                    $layout_data->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_data);
                        $em->flush();
                    }
                }
                else if ($theme->getThemeType() == "table") {
$ret .= ' - created new "table" layout based off Theme '.$theme->getId();
                    $layout = new Layout();
                    $layout->setDataType($datatype);
                    $layout->setIsTableLayout(true);
                    $layout->setIsBaseLayout(false);

                    $layout->setCreated( $theme->getCreated() );
                    $layout->setCreatedBy( $theme->getCreatedBy() );
                    $layout->setUpdated( $theme->getUpdated() );
                    $layout->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout);
                        $em->flush();
                        $em->refresh($layout);
                    }

                    $layout_meta = new LayoutMeta();
                    $layout_meta->setLayoutName('');
                    $layout_meta->setLayoutDescription('');
                    $layout_meta->setPublicDate( $theme->getCreated() );
                    $layout_meta->setIsOfficial(true);  // no user-generated themes yet, so consider all of these to be "official"

                    // Only set this layout as search default if the datatype was originally set to use this specific table theme as it's search result display
                    if ($datatype->getUseShortResults() == false && $theme->getIsDefault() == true) {
$ret .= ' (set as search default)';
                        $layout_meta->setIsSearchDefault(true);
                    }
                    else {
                        $layout_meta->setIsSearchDefault(false);
                    }
$ret .= "\n";

                    $layout_meta->setIsViewDefault(false);
                    $layout_meta->setIsEditDefault(false);

                    $layout_meta->setHasSearchIntent(true);

                    $layout_meta->setLayout($layout);

                    $layout_meta->setCreated( $theme->getCreated() );
                    $layout_meta->setCreatedBy( $theme->getCreatedBy() );
                    $layout_meta->setUpdated( $theme->getUpdated() );
                    $layout_meta->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_meta);
                        $em->flush();
                    }

$ret .= '   > created default LayoutData entry'."\n";
                    $layout_data = new LayoutData();
                    $layout_data->setLayout($layout);
                    $layout_data->setTheme($theme);
                    $layout_data->setDataTree(null);

                    $layout_data->setDisplayType(0);    // not going to be used, set to accordion

                    $layout_data->setCreated( $theme->getCreated() );
                    $layout_data->setCreatedBy( $theme->getCreatedBy() );
                    $layout_data->setUpdated( $theme->getUpdated() );
                    $layout_data->setUpdatedBy( $theme->getUpdatedBy() );

                    if ($write) {
                        $em->persist($layout_data);
                        $em->flush();
                    }
                }
            }

            $return['d'] = $ret;
        }
        catch (\Exception $e) {
            $return['r'] = 1;
            $return['t'] = 'ex';
            $return['d'] = 'Error 0x212738622 ' . $e->getMessage();
        }

        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
