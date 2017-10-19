<?php

class EarthIT_ConfigLoader_ConfigTest extends TOGoS_SimplerTest_TestCase
{
	public function testLeafFromFile() {
		$loader = new EarthIT_ConfigLoader('src/test/configs');
		$this->assertEquals( "frat", $loader->get("wot/wat/rat") );
	}
	public function testTreeFromFile() {
		$loader = new EarthIT_ConfigLoader('src/test/configs');
		$this->assertEquals( array(
			'bat' => 'fat',
			'rat' => 'frat',			
		), $loader->get("wot/wat") );
	}
	public function testTreeFromDir() {
		$loader = new EarthIT_ConfigLoader('src/test/configs');
		$this->assertEquals( array(
			'wot' => 'wot wot',
			'wat' => array(
				'bat' => 'fat',
				'rat' => 'frat'
			)
		), $loader->get("wot") );
	}
	
	public function testLeafCreatedByEnv() {
		$loader = new EarthIT_ConfigLoader('src/test/configs', array(
			'foo_bar_baz' => 'quux'
		));
		$this->assertEquals( "quux", $loader->get("foo/bar/baz") );
	}
	
	public function testLeafReplacedByLeafFromEnv() {
		$loader = new EarthIT_ConfigLoader('src/test/configs', array(
			'wot_wat_rat' => 'override'
		));
		$this->assertEquals( "override", $loader->get("wot/wat/rat") );
	}
	
	public function testLeafReplacedByTreeFromEnv() {
		$loader = new EarthIT_ConfigLoader('src/test/configs', array(
			'wot_wat_rat' => 'override'
		));
		$this->assertEquals( array(
			'bat' => 'fat',
			'rat' => 'override',
		), $loader->get("wot/wat") );
	}

	public function testTreeReplacedByLeafFromEnv() {
		$loader = new EarthIT_ConfigLoader('src/test/configs', array(
			'wot_wat' => 'rover ride'
		));
		$this->assertEquals( 'rover ride', $loader->get("wot/wat") );
	}
	
	public function testTreeMergedFromEnv() {
		$loader = new EarthIT_ConfigLoader('src/test/configs', array(
			'wot_wat' => array('rat' => 'brat', 'snek' => 'rover ride')
		));
		$this->assertEquals( array(
			'bat' => 'fat',
			'rat' => 'brat',
			'snek' => 'rover ride'
		), $loader->get("wot/wat") );
	}	
}
