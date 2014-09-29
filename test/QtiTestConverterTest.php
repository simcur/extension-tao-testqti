<?php

use qtism\data\storage\xml\XmlDocument;

/**
 * Integration test of the {@link taoQtiTest_models_classes_QtiTestConverter} class.
 *
 * @author Bertrand Chevrier <bertrand@taotesting.com>
 * @package taoQtiTest
 
 */
class QtiTestConverterTest extends TaoPhpUnitTestRunner {

//     "rubricBlocks" : [ { "content" : [  ],
//                    "rubricBlock" : { "content" : [  ],
//                        "qti-type" : "rubricBlock",
//                        "views" : [ 1 ]
//                      },
//                    "views" : [ "" ]
//                  } ],

    /**
     * Data provider 
     * @return array[] the parameters
     */
    public function dataProvider() {
        $dataPath = dirname(__FILE__) . '/data/';

        $json = json_encode(
            json_decode(
                file_get_contents($dataPath . 'qtitest.json')
            )
        );

        return array(
            array($dataPath . 'qtitest.xml', $json)
        );
    }

    /**
     * Test {@link taoQtiTest_models_classes_QtiTestConverter::toJson}
     * @dataProvider dataProvider
     * @param string $testPath the path of the QTI test to convert
     * @param string $expected the expected json result 
     */
    public function testToJson($testPath, $expected) {

        $doc = new XmlDocument('2.1');
        try {
            $doc->load($testPath);
        } catch (StorageException $e) {
            $this->fail($e->getMessage());
        }

        $converter = new taoQtiTest_models_classes_QtiTestConverter($doc);
        $result = $converter->toJson();

        $this->assertEquals($expected, $result);
    }

    /**
     * Test {@link taoQtiTest_models_classes_QtiTestConverter::fromJson}
     * @dataProvider dataProvider
     * @param string $testPath 
     * @param string $json 
     */
   public function testFromJson($testPath, $json) {

        $doc = new XmlDocument('2.1');
        $converter = new taoQtiTest_models_classes_QtiTestConverter($doc);
        $converter->fromJson($json);

        $result = preg_replace(
            array('/ {2,}/', '/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'),
            array(' ', ''), $doc->saveToString()

        );
        $expected = preg_replace(
            array('/ {2,}/', '/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'),
            array(' ', ''), file_get_contents($testPath)

        );

        $this->assertEquals($result, $expected);
    }

}