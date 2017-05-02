<?php
namespace FluidTYPO3\Flux\Tests\Unit\ViewHelpers\Form;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Doctrine\DBAL\Statement;
use FluidTYPO3\Flux\Service\RecordService;
use FluidTYPO3\Flux\Tests\Fixtures\Data\Records;
use FluidTYPO3\Flux\Tests\Unit\ViewHelpers\AbstractViewHelperTestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * DataViewHelperTest
 */
class DataViewHelperTest extends AbstractViewHelperTestCase
{

    /**
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $GLOBALS['TCA'] = array(
            'tt_content' => array(
                'columns' => array(
                    'pi_flexform' => array()
                )
            ),
            'be_users' => array(
                'columns' => array(
                    'username' => array()
                )
            ),
        );
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass()
    {
        unset($GLOBALS['TCA']);
    }

    /**
     * @test
     */
    public function failsWithInvalidTable()
    {
        $arguments = array(
            'table' => 'invalid',
            'field' => 'pi_flexform',
            'uid' => 1
        );
        $viewHelper = $this->buildViewHelperInstance($arguments);
        $prophecy = $this->prophesize(RecordService::class);
        $prophecy->getSingle()->shouldNotBeCalled();
        $prophecy->reveal();

        $this->expectViewHelperException(
            'Invalid table:field "' . $arguments['table'] . ':' . $arguments['field'] . '" - does not exist in TYPO3 TCA.'
        );

        $viewHelper->initializeArgumentsAndRender();
    }

    /**
     * @test
     */
    public function failsWithMissingArguments()
    {
        $arguments = array(
            'table' => 'tt_content',
            'field' => 'pi_flexform',
        );
        $statement = $this->prophesize(Statement::class);
        $statement->fetchAll()->willReturn([]);
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        $prophecy = $this->prophesize(ConnectionPool::class);
        $prophecy->getQueryBuilderForTable('tt_content')->willReturn($queryBuilder->reveal());

        GeneralUtility::addInstance(ConnectionPool::class, $prophecy->reveal());

        $this->expectViewHelperException(
            'Either table "' . $arguments['table'] . '", field "' . $arguments['field'] . '" or record with uid 0 do not exist and you did not manually provide the "record" attribute.'
        );
        $this->executeViewHelper($arguments);
    }

    /**
     * @test
     */
    public function failsWithInvalidField()
    {
        $arguments = array(
            'table' => 'tt_content',
            'field' => 'invalid',
            'uid' => 1
        );
        $this->expectViewHelperException(
            'Invalid table:field "' . $arguments['table'] . ':' . $arguments['field'] . '" - does not exist in TYPO3 TCA.'
        );
        $this->executeViewHelper($arguments);
    }

    /**
     * @test
     */
    public function canExecuteViewHelper()
    {
        $arguments = array(
            'table' => 'tt_content',
            'field' => 'pi_flexform',
            'record' => [
                'foo' => 'bar'
            ]
        );

        $prophecy = $this->prophesize(RecordService::class);
        $prophecy->getSingle()->shouldNotBeCalled();
        $prophecy->reveal();

        $this->executeViewHelper($arguments);
    }

    /**
     * @test
     */
    public function canUseRecordAsArgument()
    {
        $arguments = array(
            'table' => 'tt_content',
            'field' => 'pi_flexform',
            'record' => Records::$contentRecordIsParentAndHasChildren
        );
        $result = $this->executeViewHelper($arguments);
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function canUseChildNodeAsRecord()
    {
        $arguments = array(
            'table' => 'tt_content',
            'field' => 'pi_flexform',
            'uid' => 1
        );
        $record = Records::$contentRecordWithoutParentAndWithoutChildren;
        $content = $this->createNode('Array', $record);
        $viewHelper = $this->buildViewHelperInstance($arguments, array(), $content);
        $output = $viewHelper->initializeArgumentsAndRender();
        $this->assertIsArray($output);
    }

    /**
     * @test
     */
    public function supportsAsArgument()
    {
        $row = Records::$contentRecordWithoutParentAndWithoutChildren;
        $row['pi_flexform'] = $row['test'];
        $arguments = array(
            'record' => $row,
            'table' => 'tt_content',
            'field' => 'pi_flexform',
            'as' => 'test'
        );
        $output = $this->executeViewHelperUsingTagContent('Some text', $arguments);
        $this->assertEquals($output, 'Some text');
    }

    /**
     * @test
     */
    public function supportsAsArgumentAndBacksUpExistingVariable()
    {
        $row = Records::$contentRecordWithoutParentAndWithoutChildren;
        $row['pi_flexform'] = $row['test'];
        $arguments = array(
            'record' => $row,
            'table' => 'tt_content',
            'field' => 'pi_flexform',
            'as' => 'test'
        );
        $output = $this->executeViewHelperUsingTagContent('Some text', $arguments, array('test' => 'somevar'));
        $this->assertEquals($output, 'Some text');
    }

    /**
     * @test
     */
    public function readDataArrayFromProvidersOrUsingDefaultMethodCallsConfigurationServiceConvertOnEmptyProviderArray()
    {
        $mock = $this->createInstance();
        $configurationService = $this->getMockBuilder('FluidTYPO3\\Flux\\Service\\FluxService')->setMethods(array('convertFlexFormContentToArray'))->getMock();
        $providers = array();
        $record = array();
        $field = null;
        $mock->injectConfigurationService($configurationService);
        $result = $this->callInaccessibleMethod($mock, 'readDataArrayFromProvidersOrUsingDefaultMethod', $providers, $record, $field);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function readDataArrayFromProvidersOrUsingDefaultMethodUsesProvidersToReadData()
    {
        $mock = $this->createInstance();
        $provider1 = $this->getMockBuilder('FluidTYPO3\\Flux\\Provider\\Provider')->setMethods(array('getFlexFormValues'))->getMock();
        $provider1->expects($this->once())->method('getFlexFormValues')->willReturn(array('foo' => array('bar' => 'test')));
        $provider2 = $this->getMockBuilder('FluidTYPO3\\Flux\\Provider\\Provider')->setMethods(array('getFlexFormValues'))->getMock();
        $provider2->expects($this->once())->method('getFlexFormValues')
            ->willReturn(array('foo' => array('bar' => 'test2', 'baz' => 'test'), 'bar' => 'test'));
        $providers = array($provider1, $provider2);
        $record = Records::$contentRecordIsParentAndHasChildren;
        $field = 'pi_flexform';
        $result = $this->callInaccessibleMethod($mock, 'readDataArrayFromProvidersOrUsingDefaultMethod', $providers, $record, $field);
        $this->assertEquals(array('foo' => array('bar' => 'test2', 'baz' => 'test'), 'bar' => 'test'), $result);
    }
}
