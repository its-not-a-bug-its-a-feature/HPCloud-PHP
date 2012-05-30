<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
/**
 * @file
 *
 * Unit tests for HPCloud::DBaaS::Snapshot.
 */
namespace HPCloud\Tests\Services\DBaaS;

require_once __DIR__ . '/DBaaSTestCase.php';

use \HPCloud\Services\DBaaS;
use \HPCloud\Services\DBaaS\Snapshot;

/**
 * @group dbaas
 */
class DBaaSSnapshot extends DBaaSTestCase {

  public function testConstruct() {
    $ident = $this->identity();
    $dbaas = DBaaS::newFromIdentity($ident);

    $snap = $dbaas->snapshot();

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Snapshot', $snap);
  }

  public function testCreate() {
    $dbname = self::conf('hpcloud.dbaas.database');
    $this->assertNotEmpty($dbname);

    $this->destroyDatabase();

    $dbaas = $this->dbaas();
    $inst = $dbaas->instance();

    $details = $inst->create($dbname);
    $this->waitUntilRunning($inst, $details, TRUE);

    $id = $details->id();

    $this->assertNotEmpty($id);

    $snap = $dbaas->snapshot();
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\Snapshot', $snap);

    $name = $id . '-SNAPSHOT';

    $snap->listSnapshots();

    $details = $snap->create($id, $name);
    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $details);

    $this->waitUntilSnapshotReady($snap, $details, TRUE);

    $this->assertNotEmpty($details->id());
    $this->assertNotEmpty($details->instanceId());
    $this->assertNotEmpty($details->status());
    $this->assertNotEmpty($details->createdOn());
    $this->assertIsArray($details->links());

    return $details;
  }

  protected function waitUntilSnapshotReady($snap, &$details, $verbose = FALSE, $max = 15, $sleep = 5) {
    if ($details->status() == 'running') {
      return TRUE;
    }
    for ($i = 0; $i < $max; ++$i) {

      if ($verbose) fwrite(STDOUT, '⌛');
      fprintf(STDOUT, "Status: %s\n", $details->status());

      sleep($sleep);
      $list= $snap->listSnapshots($details->instanceId());
      $details = $list[0];

      if ($details->status() == 'running') {
        return TRUE;
      }
    }

    throw \Exception(sprintf("Instance did not start after %d attempts (%d seconds)", $max, $max * $sleep));
  }

  /**
   * @depends testCreate
   */
  /*
  public function testDescribe($info) {
    $snap = $this->dbaas()->snapshot();

    $details = $snap->describe($info->id());

    $this->assertEquals($info->id(), $details->id());
    $this->assertEquals($info->instanceId(), $details->instanceId());
  }
   */

  /**
   * @depends testCreate
   */
  public function testListSnapshots($info) {
    $snap = $this->dbaas()->snapshot();

    // Test listing all
    $all = $snap->listSnapshpts();
    $this->assertNotEmpty($all);

    $found;
    foreach ($all as $item) {
      if ($item->id() == $info->id()) {
        $found = $item;
      }
    }

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $found);


    // Test listing just for specific instance ID.
    $all = $snap->listSnapshpts($info->instanceId());
    $this->assertEquals(1, count($all));

    $found = NULL;
    foreach ($all as $item) {
      if ($item->id() == $info->id()) {
        $found = $item;
      }
    }

    $this->assertInstanceOf('\HPCloud\Services\DBaaS\SnapshotDetails', $found);
    $this->assertEqual($item->id(), $found->id());
  }

  /**
   * @depends testCreate
   */
  public function testDelete($info) {
    $snap = $this->dbaas()->snapshot();

    $res = $snap->delete($info->id());

    $this->assertTrue($res);

    $snaps = $snap->listSnapshots($info->id());

    $this->assertEmpty($snaps);
  }

}