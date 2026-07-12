<?php

namespace Tests\Unit\Domain;

use App\Domain\Geo\InvalidPolygon;
use App\Domain\Geo\PolygonGeometry;
use App\Domain\Geo\PolygonRelation;
use PHPUnit\Framework\TestCase;

class PolygonGeometryTest extends TestCase
{
    private PolygonGeometry $geometry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geometry = new PolygonGeometry;
    }

    public function test_it_calculates_gross_excluded_and_net_areas_in_one_projection(): void
    {
        $areas = $this->geometry->areaBreakdown($this->outerField(), [$this->insideExclusion()]);

        $this->assertEqualsWithDelta(476.10, $areas['bruta'], 0.01);
        $this->assertEqualsWithDelta(19.04, $areas['excluida'], 0.01);
        $this->assertEqualsWithDelta(457.06, $areas['liquida'], 0.01);
    }

    public function test_clockwise_and_counterclockwise_rings_have_the_same_area(): void
    {
        $clockwise = $this->outerField();

        $this->assertSame(
            $this->geometry->areaHectares($clockwise),
            $this->geometry->areaHectares(array_reverse($clockwise)),
        );
    }

    public function test_an_edge_cannot_cross_the_opening_of_a_concave_field(): void
    {
        $concaveField = $this->scaledRing([
            [0, 0], [4, 0], [4, 4], [3, 4], [3, 1], [1, 1], [1, 4], [0, 4],
        ]);
        $crossingTriangle = $this->scaledRing([
            [0.5, 3], [3.5, 3], [2, 0.5],
        ]);

        $this->assertFalse($this->geometry->ringStrictlyInside($crossingTriangle, $concaveField));
    }

    public function test_an_exclusion_touching_the_field_boundary_is_rejected(): void
    {
        $touching = [
            ['lat' => -15.7200, 'lng' => -47.9180],
            ['lat' => -15.7180, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9180],
        ];

        $this->assertFalse($this->geometry->ringStrictlyInside($touching, $this->outerField()));
    }

    public function test_duplicate_and_contained_exclusions_are_idempotent(): void
    {
        $existing = $this->insideExclusion();
        $rotatedAndReversed = array_reverse([$existing[2], $existing[3], $existing[0], $existing[1]]);
        $contained = [
            ['lat' => -15.7175, 'lng' => -47.9175],
            ['lat' => -15.7175, 'lng' => -47.9160],
            ['lat' => -15.7160, 'lng' => -47.9160],
            ['lat' => -15.7160, 'lng' => -47.9175],
        ];

        $this->assertCount(1, $this->geometry->appendExclusion($this->outerField(), [$existing], $rotatedAndReversed));
        $this->assertCount(1, $this->geometry->appendExclusion($this->outerField(), [$existing], $contained));
    }

    public function test_a_larger_candidate_replaces_an_exclusion_that_it_contains(): void
    {
        $small = [
            ['lat' => -15.7175, 'lng' => -47.9175],
            ['lat' => -15.7175, 'lng' => -47.9160],
            ['lat' => -15.7160, 'lng' => -47.9160],
            ['lat' => -15.7160, 'lng' => -47.9175],
        ];

        $result = $this->geometry->appendExclusion($this->outerField(), [$small], $this->insideExclusion());

        $this->assertCount(1, $result);
        $this->assertSame(PolygonRelation::Equal, $this->geometry->relation($result[0], $this->insideExclusion()));
    }

    public function test_partial_overlap_between_exclusions_is_rejected(): void
    {
        $overlap = [
            ['lat' => -15.7160, 'lng' => -47.9160],
            ['lat' => -15.7160, 'lng' => -47.9120],
            ['lat' => -15.7120, 'lng' => -47.9120],
            ['lat' => -15.7120, 'lng' => -47.9160],
        ];

        $this->expectException(InvalidPolygon::class);
        $this->geometry->appendExclusion($this->outerField(), [$this->insideExclusion()], $overlap);
    }

    public function test_self_intersections_and_invalid_coordinates_are_rejected(): void
    {
        foreach ([
            $this->scaledRing([[0, 0], [2, 2], [0, 2], [2, 0]]),
            [['lat' => '1e400', 'lng' => 0], ['lat' => 0, 'lng' => 1], ['lat' => 1, 'lng' => 0]],
            [['lat' => 91, 'lng' => 0], ['lat' => 0, 'lng' => 1], ['lat' => 1, 'lng' => 0]],
        ] as $invalidRing) {
            try {
                $this->geometry->normalizeRing($invalidRing);
                $this->fail('O polígono inválido deveria ter sido rejeitado.');
            } catch (InvalidPolygon) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidPolygon::class);
        $this->geometry->decodeJsonRing('{json-invalido');
    }

    private function outerField(): array
    {
        return [
            ['lat' => -15.7200, 'lng' => -47.9200],
            ['lat' => -15.7200, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9200],
        ];
    }

    private function insideExclusion(): array
    {
        return [
            ['lat' => -15.7180, 'lng' => -47.9180],
            ['lat' => -15.7180, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9180],
        ];
    }

    private function scaledRing(array $points): array
    {
        return array_map(
            fn (array $point) => [
                'lat' => -15.0 + ($point[1] * 0.001),
                'lng' => -48.0 + ($point[0] * 0.001),
            ],
            $points,
        );
    }
}
