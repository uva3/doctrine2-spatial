<?php
/**
 * Copyright (C) 2020 Alexandre Tranchant
 * Copyright (C) 2015 Derek J. Lambert
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace CrEOF\Spatial\Tests\ORM\Query\AST\Functions\Standard;

use CrEOF\Spatial\Exception\InvalidValueException;
use CrEOF\Spatial\Exception\UnsupportedPlatformException;
use CrEOF\Spatial\Tests\Helper\PolygonHelperTrait;
use CrEOF\Spatial\Tests\OrmTestCase;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\ORMException;

/**
 * ST_Envelope DQL function tests.
 *
 * @author  Derek J. Lambert <dlambert@dereklambert.com>
 * @author  Alexandre Tranchant <alexandre.tranchant@gmail.com>
 * @license https://dlambert.mit-license.org MIT
 *
 * @group dql
 *
 * @internal
 * @coversDefaultClass
 */
class StEnvelopeTest extends OrmTestCase
{
    use PolygonHelperTrait;

    /**
     * Setup the function type test.
     *
     * @throws DBALException                when connection failed
     * @throws ORMException                 when cache is not set
     * @throws UnsupportedPlatformException when platform is unsupported
     */
    protected function setUp(): void
    {
        $this->usesEntity(self::POLYGON_ENTITY);
        $this->supportsPlatform('postgresql');
        $this->supportsPlatform('mysql');

        parent::setUp();
    }

    /**
     * Test a DQL containing function to test in the select.
     *
     * @throws DBALException                when connection failed
     * @throws ORMException                 when cache is not set
     * @throws UnsupportedPlatformException when platform is unsupported
     * @throws InvalidValueException        when geometries are not valid
     *
     * @group geometry
     */
    public function testSelectStEnvelope()
    {
        $this->createBigPolygon();
        $this->createHoleyPolygon();
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $query = $this->getEntityManager()->createQuery(
            'SELECT ST_AsText(ST_Envelope(p.polygon)) FROM CrEOF\Spatial\Tests\Fixtures\PolygonEntity p'
        );
        $result = $query->getResult();

        switch ($this->getPlatform()->getName()) {
            case 'mysql':
                //polygon is equals, but not the same
                $expected = 'POLYGON((0 0,10 0,10 10,0 10,0 0))';
                break;
            case 'postgresql':
            default:
                $expected = 'POLYGON((0 0,0 10,10 10,10 0,0 0))';
        }
        static::assertEquals($expected, $result[0][1]);
        static::assertEquals($expected, $result[1][1]);
    }

    /**
     * Test a DQL containing function to test in the predicate.
     *
     * @throws DBALException                when connection failed
     * @throws ORMException                 when cache is not set
     * @throws UnsupportedPlatformException when platform is unsupported
     * @throws InvalidValueException        when geometries are not valid
     *
     * @group geometry
     */
    public function testStEnvelopeWhereParameter()
    {
        $holeyPolygon = $this->createHoleyPolygon();
        $this->createSmallPolygon();
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $query = $this->getEntityManager()->createQuery(
            // phpcs:disable Generic.Files.LineLength.MaxExceeded
            'SELECT p FROM CrEOF\Spatial\Tests\Fixtures\PolygonEntity p WHERE ST_Envelope(p.polygon) = ST_GeomFromText(:p)'
            // phpcs:enable
        );

        switch ($this->getPlatform()->getName()) {
            case 'mysql':
                $parameter = 'POLYGON((0 0,10 0,10 10,0 10,0 0))';
                break;
            case 'postgresql':
            default:
                $parameter = 'POLYGON((0 0,0 10,10 10,10 0,0 0))';
        }

        $query->setParameter('p', $parameter, 'string');

        $result = $query->getResult();

        static::assertCount(1, $result);
        static::assertEquals($holeyPolygon, $result[0]);
    }
}
