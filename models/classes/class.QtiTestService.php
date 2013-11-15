<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2008-2010 (original work) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *
 */
use qtism\data\storage\StorageException;
use qtism\data\storage\xml\XmlDocument;
use qtism\data\QtiComponentCollection;
use qtism\data\SectionPartCollection;

/**
 * the QTI TestModel service
 *
 * @access public
 * @author Joel Bout, <joel.bout@tudor.lu>
 * @author Bertrand Chevrier <bertrand@taotesting.com>
 * @package taoQtiTest
 * @subpackage models_classes
 */
class taoQtiTest_models_classes_QtiTestService extends tao_models_classes_Service {

    const CONFIG_QTITEST_FOLDER = 'qtiTestFolder';

    /**
     * Get the QTI Test document formated in JSON.
     * 
     * @param core_kernel_classes_Resource $test
     * @return string the json
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    public function getJsonTest(core_kernel_classes_Resource $test){
        
        $doc = $this->getDoc($test);
        $converter = new taoQtiTest_models_classes_QtiTestConverter($doc);
        return $converter->toJson();
    }
    
    /**
     * Save the json formated test into the test resource.
     * 
     * @param core_kernel_classes_Resource $test
     * @param string $json
     * @return boolean true if saved
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    public function saveJsonTest(core_kernel_classes_Resource $test, $json){
        $saved = false;
        
        if(!empty($json)){
            $doc = $this->getDoc($test);
            
            $converter = new taoQtiTest_models_classes_QtiTestConverter($doc);
            $converter->fromJson($json);
            
            $saved = $this->saveDoc($test, $doc);
        }
        return $saved;
    }
    
    public function fromJson($json){
        $doc = new XmlDocument('2.1');
        $converter = new taoQtiTest_models_classes_QtiTestConverter($doc);
        $converter->fromJson($json);
        return $doc;
    }
    
    /**
     * Get the items that are part of a given $test.
     * 
     * @param core_kernel_classes_Resource $test A Resource describing a QTI Assessment Test.
     * @return array An array of core_kernel_classes_Resource objects.
     */
    public function getItems( core_kernel_classes_Resource $test ) {
    	return $this->getDocItems($this->getDoc($test));
    }
    
    /**
     * Assign items to a test and save it.
     * @param core_kernel_classes_Resource $test
     * @param array $items
     * @return boolean true if set
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    public function setItems(core_kernel_classes_Resource $test, array $items){
        
        $doc = $this->getDoc($test);
        $bound = $this->setItemsToDoc($doc, $items);
        
        if($this->saveDoc($test, $doc)){
            return $bound == count($items);
        }
        
        return false;
    }
    
      /**
     * Save the QTI test : set the items sequence and some options.
     * 
     * @param core_kernel_classes_Resource $test A Resource describing a QTI Assessment Test.
     * @param array $items the items sequence
     * @param array $options the test's options
     * @return boolean if nothing goes wrong
     * @throws StorageException If an error occurs while serializing/unserializing QTI-XML content. 
     */
    public function save( core_kernel_classes_Resource $test, array $items) {
    	
        try {
            $doc = $this->getDoc($test);
            
            $this->setItemsToDoc($doc, $items);
            $saved  = $this->saveDoc($test, $doc);
        }
    	catch (StorageException $e) {
    		throw new taoQtiTest_models_classes_QtiTestServiceException(
                        "An error occured while dealing with the QTI-XML test: ".$e->getMessage(), 
                        taoQtiTest_models_classes_QtiTestServiceException::TEST_WRITE_ERROR
                   );
    	}
    	
    	return $saved;
    }

    /**
     * Get an identifier for a component of $qtiType.
     * This identifier must be unique across the whole document.
     * 
     * @param XmlDocument $doc
     * @param type $qtiType the type name
     * @return the identifier
     */
    public function getIdentifierFor(XmlDocument $doc, $qtiType){
        $components = $doc->getDocumentComponent()->getIdentifiableComponents();
        $i = 1;
        do {
            $identifier = $this->generateIdentifier($doc, $qtiType, $i);
            $i++;
        } while(!$this->isIdentifierUnique($components, $identifier));
        
        return $identifier;
    }
    
    /**
     * Check whether an identifier is unique against a list of components
     * @param QtiComponentCollection $components
     * @param type $identifier
     * @return boolean
     */
    private function isIdentifierUnique(QtiComponentCollection $components, $identifier){
        foreach($components as $component){
            if($component->getIdentifier() == $identifier){
                return false;
            }
        }
        return true;
    }
    
    /**
     * Generate an identifier from a qti type, using the syntax "qtitype-index"
     * @param \qtism\data\storage\xml\XmlDocument $doc
     * @param type $qtiType
     * @param type $offset
     * @return the identifier
     */
    private function generateIdentifier(XmlDocument $doc, $qtiType, $offset = 1){
        $typeList = $doc->getDocumentComponent()->getComponentsByClassName($qtiType);
        return $qtiType . '-' . (count($typeList) + $offset);
    }
    
    /**
     * 
     * @param core_kernel_classes_Resource $testResource
     * @param unknown $file
     * @return common_report_Report
     * @throws common_exception_NotImplemented
     */
    public function importTest(core_kernel_classes_Resource $testResource, $file, $itemClass){
        $report = new common_report_Report();

        $qtiPackageParser = new taoQtiCommon_models_classes_PackageParser($file);
        $qtiPackageParser->validate();

        if($qtiPackageParser->isValid()){
            //extract the package
            $folder = $qtiPackageParser->extract();
            if(!is_dir($folder)){
                throw new taoQTI_models_classes_QTI_exception_ExtractException();
            }

            //load and validate the manifest
            $qtiManifestParser = new taoQtiCommon_models_classes_ManifestParser($folder.'/imsmanifest.xml');
            $qtiManifestParser->validate();
            if($qtiManifestParser->isValid()){
                $tests = $qtiManifestParser->getResources('imsqti_test_xmlv2p1');
                if(empty($tests)){
                    $report->add(new common_report_ErrorElement(__('No test found in package')));
                }elseif(count($tests) > 1){
                    $report->add(new common_report_ErrorElement(__('Found more than one test in package')));
                }else{
                    $testQtiResource = current($tests);
                    // import items
                    $itemService = taoQTI_models_classes_QTI_ImportService::singleton();
                    $itemMap = array();
                    foreach($qtiManifestParser->getResources() as $qtiResource){
                        if(taoQTI_models_classes_QTI_Resource::isAssessmentItem($qtiResource->getType())){
                            try{
                                $qtiFile = $folder.DIRECTORY_SEPARATOR.$qtiResource->getFile();
                                $rdfItem = $itemService->importQTIFile($qtiFile, $itemClass);
                                $itemPath = taoItems_models_classes_ItemsService::singleton()->getItemFolder($rdfItem);

                                foreach($qtiResource->getAuxiliaryFiles() as $auxResource){
                                    // $auxResource is a relativ URL, so we need to replace the slashes with directory separators
                                    $auxPath = $folder.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $auxResource);
                                    $relPath = helpers_File::getRelPath($qtiFile, $auxPath);
                                    $destPath = $itemPath.$relPath;
                                    tao_helpers_File::copy($auxPath, $destPath, true);
                                }
                                $itemMap[$qtiResource->getIdentifier()] = $rdfItem;
                            }catch(taoQTI_models_classes_QTI_exception_ParsingException $e){
                                
                            }catch(Exception $e){
                                // an error occured during a specific item
                                // skip to next
                            }
                        }
                    }

                    $testDefinition = new XmlDocument();
                    $testDefinition->load($folder.DIRECTORY_SEPARATOR.$testQtiResource->getFile());
                    $this->importTestContent($testResource, $testDefinition, $itemMap);
                    
                    $report->add(new common_report_SuccessElement(__('Test successfully imported')));
                }
            }else{
                $report->add($qtiManifestParser->getReport());
            }
        }else{
            $report->add($qtiPackageParser->getReport());
        }
        return $report;
    }

    /**
     * Finalize the QTI Test import by importing its XML definition into the system, after
     * the QTI Items composing the test were also imported.
     * 
     * The $itemMapping argument makes the implementation of this method able to know
     * what are the items that were imported. The $itemMapping is an associative array
     * where keys are the assessmentItemRef's identifiers and the values are the core_kernel_classes_Resources of
     * the items that are now stored in the system.
     *
     * @param core_kernel_classes_Resource $testResource A Test Resource the new content must be bind to.
     * @param XmlDocument $testDefinition An XmlAssessmentTestDocument object.
     * @param array $itemMapping An associative array that represents the mapping between assessmentItemRef elements and the imported items.
     * @return core_kernel_file_File The newly created test content.
     * @throws taoQtiTest_models_classes_QtiTestServiceException If an error occurs during the import process.
     */
    public function importTestContent(core_kernel_classes_Resource $testResource, XmlDocument $testDefinition, array $itemMapping){
        $assessmentItemRefs = $testDefinition->getDocumentComponent()->getComponentsByClassName('assessmentItemRef');
        $assessmentItemRefsCount = count($assessmentItemRefs);
        $itemMappingCount = count($itemMapping);

        if($assessmentItemRefsCount === 0){
            $msg = "The QTI Test to be imported does not contain any item.";
            throw new taoQtiTest_models_classes_QtiTestServiceException($msg);
        }else if($assessmentItemRefsCount !== $itemMappingCount){
            $msg = "The item mapping count does not corresponds to the number of items referenced by the QTI Test.";
            throw new taoQtiTest_models_classes_QtiTestServiceException($msg);
        }

        foreach($assessmentItemRefs as $itemRef){
            $itemRefIdentifier = $itemRef->getIdentifier();

            if(isset($itemMapping[$itemRefIdentifier]) === false){
                $msg = "No mapping found for assessmentItemRef '${itemRefIdentifier}'.";
                throw new taoQtiTest_models_classes_QtiTestServiceException($msg);
            }

            $itemRef->setHref($itemMapping[$itemRefIdentifier]->getUri());
        }

        // Bind the newly created test content to the Test Resource in database.
        $testContent = $this->createContent($testResource);
        $testPath = $testContent->getAbsolutePath();

        try{
            $testDefinition->save($testPath);
        }catch(StorageException $e){
            $msg = "An error occured while saving the new test content of '${testPath}'.";
            throw new taoQtiTest_models_classes_QtiTestServiceException($msg);
        }
        
        return $testContent;
    }
    
    /**
     * Get the file from a test
     * @param core_kernel_classes_Resource $test
     * @return null
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function getTestFile(core_kernel_classes_Resource $test){
        
        if(is_null($test)){
            throw new taoQtiTest_models_classes_QtiTestServiceException(
                    'The selected test is null', 
                    taoQtiTest_models_classes_QtiTestServiceException::TEST_READ_ERROR
               );
        }
            
        $testModel = $test->getOnePropertyValue(new core_kernel_classes_Property(PROPERTY_TEST_TESTMODEL));
        if(is_null($testModel) || $testModel->getUri() != INSTANCE_TEST_MODEL_QTI) {
            throw new taoQtiTest_models_classes_QtiTestServiceException(
                    'The selected test is not a QTI test', 
                    taoQtiTest_models_classes_QtiTestServiceException::TEST_READ_ERROR
               );
        }
        $file = $test->getOnePropertyValue(new core_kernel_classes_Property(TEST_TESTCONTENT_PROP));
        if(!is_null($file)){
            return new core_kernel_classes_File($file);
        }
        return null;
    }
    
    /**
     * Get the QTI reprensentation of a test content.
     * 
     * @param core_kernel_classes_Resource $test the test to get the content from
     * @param type $validate enable validation
     * @return \qtism\data\storage\xml\XmlAssessmentTestDocument the QTI representation from the test content
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function getDoc(core_kernel_classes_Resource $test) {
        
        $doc = new XmlDocument('2.1');
        
        if(!is_null($test)){
            
            $file = $this->getTestFile($test);
            if (is_null($file)) {
                $file = $this->createContent($test);
            } else {
                $file = new core_kernel_file_File($file);
            }
            $testPath = $file->getAbsolutePath();
            try {
                $doc->load($testPath);
            } catch (StorageException $e) {
                throw new taoQtiTest_models_classes_QtiTestServiceException(
                        "An error occured while loading QTI-XML test file '${testPath}' : ".$e->getMessage(), 
                        taoQtiTest_models_classes_QtiTestServiceException::TEST_READ_ERROR
                    );
            }
        }
        return $doc;
    }
    
        /**
     * Get the items from a QTI test document.
     * @param \qtism\data\storage\xml\XmlDocument $doc
     * @return \core_kernel_classes_Resource
     */
    private function getDocItems( XmlDocument $doc ){
        $itemArray = array();
    	foreach ($doc->getDocumentComponent()->getComponentsByClassName('assessmentItemRef') as $itemRef) {
            $itemArray[] = new core_kernel_classes_Resource($itemRef->getHref());
    	}
    	return $itemArray;
    }
    
    /**
     * Assign items to a QTI test.
     * @param \qtism\data\storage\xml\XmlAssessmentTestDocument $doc
     * @param array $items
     * @return type
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function setItemsToDoc( XmlDocument $doc, array $items, $sectionIndex = 0){
        
        $sections = $doc->getDocumentComponent()->getComponentsByClassName('assessmentSection');
        if(!isset($sections[$sectionIndex])){
            throw new taoQtiTest_models_classes_QtiTestServiceException(
                        'No section found in test at index : ' . $sectionIndex, 
                        taoQtiTest_models_classes_QtiTestServiceException::TEST_READ_ERROR
                    );
        }
        $section = $sections[$sectionIndex];
        
        $itemContentProperty = new core_kernel_classes_Property(TAO_ITEM_CONTENT_PROPERTY);
        $itemRefs = new SectionPartCollection();
        $itemRefIdentifiers = array();
        foreach ($items as $itemResource) {
            $itemContent = new core_kernel_file_File($itemResource->getUniquePropertyValue($itemContentProperty));

            $itemDoc = new XmlAssessmentItemDocument();

            try {
                $itemDoc->load($itemContent->getAbsolutePath());
            }
            catch (StorageException $e) {
                $msg = "An error occured while reading QTI-XML item '".$itemResource->getUri()."'.";

                if (is_null($e->getPrevious()) !== true) {
                    $msg .= ": " . $e->getPrevious()->getMessage();
                }
            $itemRefIdentifiers = array();
                throw new taoQtiTest_models_classes_QtiTestServiceException(
                        $msg, 
                        taoQtiTest_models_classes_QtiTestServiceException::ITEM_READ_ERROR
                    );
            }
            $itemRefIdentifier = $itemDoc->getIdentifier();

            //enable more than one reference
            if(array_key_exists($itemRefIdentifier, $itemRefIdentifiers)){
                    $itemRefIdentifiers[$itemRefIdentifier] += 1;
                    $itemRefIdentifier .= '-'. $itemRefIdentifiers[$itemRefIdentifier];
            } else {
                $itemRefIdentifiers[$itemRefIdentifier] = 0;
            }
            $itemRefs[] = new AssessmentItemRef($itemRefIdentifier, $itemResource->getUri());

        }
        $section->setSectionParts($itemRefs);
            
           
        
        return count($itemRefs);
    }
    
    /**
     * Save the content of test from a QTI Document
     * @param core_kernel_classes_Resource $test
     * @param \qtism\data\storage\xml\XmlDocument $doc
     * @return boolean true if saved
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function saveDoc( core_kernel_classes_Resource $test, XmlDocument $doc){
        $saved = false;
        
        if(!is_null($test) && !is_null($doc)){
            $file = $this->getTestFile($test);
            if (!is_null($file)) {
                $testPath = $file->getAbsolutePath();
                try {
                    $doc->save($testPath);
                    $saved = true;
                } catch (StorageException $e) {
                    throw new taoQtiTest_models_classes_QtiTestServiceException(
                        "An error occured while writing QTI-XML test '${testPath}': ".$e->getMessage(), 
                         taoQtiTest_models_classes_QtiTestServiceException::ITEM_WRITE_ERROR
                    );
                }
            }
        }
        return $saved;
    }

    /**
     * Create the defautl content of a QTI test.
     * @param core_kernel_classes_Resource $test
     * @return core_kernel_classes_File the content file
     */
    private function createContent( core_kernel_classes_Resource $test) {
    	common_Logger::i('CREATE CONTENT');
    	$props = self::getQtiTestDirectory()->getPropertiesValues(array(
				PROPERTY_FILE_FILESYSTEM,
				PROPERTY_FILE_FILEPATH
			));
        $repository = new core_kernel_versioning_Repository(current($props[PROPERTY_FILE_FILESYSTEM]));
        $path = (string) current($props[PROPERTY_FILE_FILEPATH]);
        $file = $repository->createFile(md5($test->getUri()).'.xml', $path);
        $ext = common_ext_ExtensionsManager::singleton()->getExtensionById('taoQtiTest');
        $emptyTestXml = file_get_contents($ext->getDir().'models'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'qtiTest.xml');
        $file->setContent($emptyTestXml);
        
        common_Logger::i('Created '.$file->getAbsolutePath());
        $test->editPropertyValues(new core_kernel_classes_Property(TEST_TESTCONTENT_PROP), $file);
        return $file;
    }
    
    /**
     * Delete the content of a QTI test
     * @param core_kernel_classes_Resource $test
     * @throws common_exception_Error
     */
    public function deleteContent( core_kernel_classes_Resource $test) {
        $content = $test->getOnePropertyValue(new core_kernel_classes_Property(TEST_TESTCONTENT_PROP));
        if (!is_null($content)) {
            $file = new core_kernel_file_File($content);
            if (file_exists($file->getAbsolutePath())) {
                if (!@unlink($file->getAbsolutePath())) {
                    throw new common_exception_Error('Unable to remove the file ' . $file->getAbsolutePath());
                }
            }
            $file->delete();
            $test->removePropertyValue(new core_kernel_classes_Property(TEST_TESTCONTENT_PROP), $file);
        }
    }
    
    /**
     * Set the directory where the tests' contents are stored.
     * @param core_kernel_file_File $folder
     */
    public function setQtiTestDirectory(core_kernel_file_File $folder) {
    	$ext = common_ext_ExtensionsManager::singleton()->getExtensionById('taoQtiTest');
    	$ext->setConfig(self::CONFIG_QTITEST_FOLDER, $folder->getUri());
    }
    
    /**
     * Get the directory where the tests' contents are stored.
     * @return \core_kernel_file_File
     * @throws common_Exception
     */
    public function getQtiTestDirectory() {
    	
    	$ext = common_ext_ExtensionsManager::singleton()->getExtensionById('taoQtiTest');
        $uri = $ext->getConfig(self::CONFIG_QTITEST_FOLDER);
        if (empty($uri)) {
            throw new common_Exception('No default repository defined for uploaded files storage.');
        }
        return new core_kernel_file_File($uri);
    }
}
?>
