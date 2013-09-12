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
 * Copyright (c) 2013 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

use qtism\common\enums\BaseType;
use qtism\data\AssessmentTest;
use qtism\runtime\common\State;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;
use qtism\runtime\tests\Route;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\ResponseVariable;
use qtism\data\ExtendedAssessmentItemRef;
use qtism\common\enums\Cardinality;

class taoQtiTest_helpers_TestSession extends AssessmentTestSession {
    
    /**
     * The ResultServer to be used to transmit Item and Test results.
     * 
     * @var taoResultServer_models_classes_ResultServerStateFull
     */
    private $resultServer;
    
    public function __construct(AssessmentTest $assessmentTest, Route $route, taoResultServer_models_classes_ResultServerStateFull $resultServer) {
        parent::__construct($assessmentTest, $route);
        $this->setResultServer($resultServer);
    }
    
    /**
     * Set the ResultServer to be used to transmit Item and Test results.
     * 
     * @param taoResultServer_models_classes_ResultServerStateFull $resultServer
     */
    public function setResultServer(taoResultServer_models_classes_ResultServerStateFull $resultServer) {
        $this->resultServer = $resultServer;
    }
    
    /**
     * Get the ResultServer in use to transmit Item and Test results.
     * 
     * @return taoResultServer_models_classes_ResultServerStateFull
     */
    public function getResultServer() {
        return $this->resultServer;
    }
    
    /**
	 * End an attempt for the current item in the route. If the current navigation mode
	 * is LINEAR, the TestSession moves automatically to the next step in the route or
	 * the end of the session if the responded item is the last one.
	 * 
	 * @param State $responses The collection of ResponseVariable objects that are considered to be the candidate responses for the current item.
	 * @throws taoQtiTest_helpers_TestSessionException
	 * @throws AssessmentItemSessionException
	 */
    public function endAttempt(State $responses) {
        
        common_Logger::d("Ending attempt for item '" . $this->getCurrentAssessmentItemRef()->getIdentifier() . "." . $this->getCurrentAssessmentItemRefOccurence() .  "'.");
        
        $item = $this->getCurrentAssessmentItemRef();
        $occurence = $this->getCurrentAssessmentItemRefOccurence();
        $sessionId = $this->getSessionId();
        
        try {
            parent::endAttempt($responses);
            
            // Get the item session we just responsed and send to the
            // result server.
            $itemSession = $this->getItemSession($item, $occurence);
            $resultTransmitter = new taoQtiCommon_helpers_ResultTransmitter($this->getResultServer());
            
            foreach ($itemSession->getKeys() as $identifier) {
                common_Logger::d("Examination of variable '${identifier}'");
                
                $variable = $itemSession->getVariable($identifier);
                $itemUri = self::getItemRefUri($item);
                $testUri = self::getTestDefinitionUri($item);
                $transmissionId = "${sessionId}.${item}.${occurence}";
                
                $resultTransmitter->transmitItemVariable($variable, $transmissionId, $itemUri, $testUri);
            }
        }
        catch (AssessmentTestSessionException $e) {
            // Error whith parent::endAttempt().
            $itemId = $this->getCurrentAssessmentItemRef()->getIdentifier();
            $itemOccurence = $this->getCurrentAssessmentItemRefOccurence();
            $msg = "An error occured while ending the attempt on item '${itemId}.${itemOccurence}'";
            throw new tao_helpers_TestSessionException($msg, $e->getCode(), $e);
        }
        catch (Exception $e) {
            // Error with Result Server.
            $itemId = $this->getCurrentAssessmentItemRef()->getIdentifier();
            $itemOccurence = $this->getCurrentAssessmentItemRefOccurence();
            
            $msg = "An error occured while transmitting results to the result server for item '${itemId}.${itemOccurence}'.";
            throw new tao_helpers_TestSessionException($msg, tao_helpers_TestSessionException::RESULT_ERROR, $e);
        }
    }
    
    /**
     * Get the TAO URI of an item from an ExtendedAssessmentItemRef object.
     * 
     * @param ExtendedAssessmentItemRef $itemRef
     * @return string A URI.
     */
    protected static function getItemRefUri(ExtendedAssessmentItemRef $itemRef) {
        $parts = explode('-', $itemRef->getHref());
        return $parts[0];
    }
    
    /**
     * Get the TAO Uri of the Test Definition from an ExtendedAssessmentItemRef object.
     * 
     * @param ExtendedAssessmentItemRef $itemRef
     * @return string A URI.
     */
    protected static function getTestDefinitionUri(ExtendedAssessmentItemRef $itemRef) {
        $parts = explode('-', $itemRef->getHref());
        return $parts[2];
    }
}