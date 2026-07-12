<?php

namespace App\Domain\Geo;

use JsonException;

final class PolygonGeometry
{
    private const EARTH_RADIUS_METERS = 6_371_000.0;

    private const EPSILON_METERS = 0.05;

    private const MIN_AREA_SQUARE_METERS = 1.0;

    private const MAX_VERTICES = 2_000;

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function decodeJsonRing(string $json): array
    {
        try {
            $points = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPolygon('O desenho enviado não contém um JSON válido.', previous: $exception);
        }

        if (! is_array($points)) {
            throw new InvalidPolygon('O desenho enviado precisa ser uma lista de coordenadas.');
        }

        return $this->normalizeRing($points);
    }

    /**
     * @param  array<mixed>  $points
     * @return list<array{lat: float, lng: float}>
     */
    public function normalizeRing(array $points): array
    {
        if (count($points) > self::MAX_VERTICES + 1) {
            throw new InvalidPolygon('O polígono excede o limite de '.self::MAX_VERTICES.' vértices.');
        }

        $normalized = [];
        foreach ($points as $point) {
            if (! is_array($point)) {
                throw new InvalidPolygon('Cada vértice precisa conter latitude e longitude.');
            }

            $latitude = array_key_exists('lat', $point) ? $point['lat'] : ($point[0] ?? null);
            $longitude = array_key_exists('lng', $point) ? $point['lng'] : ($point[1] ?? null);
            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                throw new InvalidPolygon('Latitude e longitude precisam ser numéricas.');
            }

            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            if (! is_finite($latitude) || ! is_finite($longitude)) {
                throw new InvalidPolygon('As coordenadas precisam ser finitas.');
            }
            if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
                throw new InvalidPolygon('As coordenadas estão fora dos limites geográficos.');
            }

            $candidate = ['lat' => $latitude, 'lng' => $longitude];
            if ($normalized !== [] && $this->sameGeoPoint($normalized[array_key_last($normalized)], $candidate)) {
                continue;
            }

            $normalized[] = $candidate;
        }

        if (count($normalized) > 1 && $this->sameGeoPoint($normalized[0], $normalized[array_key_last($normalized)])) {
            array_pop($normalized);
        }

        if (count($normalized) > self::MAX_VERTICES) {
            throw new InvalidPolygon('O polígono excede o limite de '.self::MAX_VERTICES.' vértices.');
        }

        if (count($normalized) < 3) {
            throw new InvalidPolygon('O polígono precisa de pelo menos três vértices distintos.');
        }

        for ($first = 0, $count = count($normalized); $first < $count; $first++) {
            for ($second = $first + 1; $second < $count; $second++) {
                if ($this->sameGeoPoint($normalized[$first], $normalized[$second])) {
                    throw new InvalidPolygon('O polígono contém vértices repetidos.');
                }
            }
        }

        [$projected] = $this->projectTogether([$normalized]);
        $this->assertSimpleProjectedRing($projected);

        return array_values($normalized);
    }

    /**
     * @param  array<mixed>  $ring
     * @param  array<mixed>  $outer
     */
    public function ringStrictlyInside(array $ring, array $outer): bool
    {
        $ring = $this->normalizeRing($ring);
        $outer = $this->normalizeRing($outer);
        [$projectedOuter, $projectedRing] = $this->projectTogether([$outer, $ring]);

        foreach ($projectedRing as $point) {
            if ($this->classifyPoint($point, $projectedOuter) !== PointLocation::Inside) {
                return false;
            }
        }

        foreach ($this->edges($projectedRing) as [$start, $end]) {
            foreach ($this->edges($projectedOuter) as [$outerStart, $outerEnd]) {
                if ($this->segmentsIntersectOrTouch($start, $end, $outerStart, $outerEnd)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $first
     * @param  array<mixed>  $second
     */
    public function relation(array $first, array $second): PolygonRelation
    {
        $first = $this->normalizeRing($first);
        $second = $this->normalizeRing($second);
        [$first, $second] = $this->projectTogether([$first, $second]);

        if ($this->ringsEqual($first, $second)) {
            return PolygonRelation::Equal;
        }

        $touches = false;
        foreach ($this->edges($first) as [$firstStart, $firstEnd]) {
            foreach ($this->edges($second) as [$secondStart, $secondEnd]) {
                $intersection = $this->segmentIntersectionType($firstStart, $firstEnd, $secondStart, $secondEnd);
                if ($intersection === 2) {
                    return PolygonRelation::PartialOverlap;
                }
                $touches = $touches || $intersection === 1;
            }
        }

        $firstLocations = array_map(fn (array $point) => $this->classifyPoint($point, $second), $first);
        $secondLocations = array_map(fn (array $point) => $this->classifyPoint($point, $first), $second);
        $firstInside = in_array(PointLocation::Inside, $firstLocations, true);
        $secondInside = in_array(PointLocation::Inside, $secondLocations, true);

        if ($touches) {
            return ($firstInside || $secondInside) ? PolygonRelation::PartialOverlap : PolygonRelation::Touching;
        }
        if ($firstInside) {
            return PolygonRelation::ContainedBy;
        }
        if ($secondInside) {
            return PolygonRelation::Contains;
        }

        return PolygonRelation::Disjoint;
    }

    /**
     * @param  array<mixed>  $outer
     * @param  array<int, array<mixed>>  $existing
     * @param  array<mixed>  $candidate
     * @return list<list<array{lat: float, lng: float}>>
     */
    public function appendExclusion(array $outer, array $existing, array $candidate): array
    {
        $outer = $this->normalizeRing($outer);
        $candidate = $this->normalizeRing($candidate);
        if (! $this->ringStrictlyInside($candidate, $outer)) {
            throw new InvalidPolygon('A área excluída precisa ficar estritamente dentro do polígono do talhão.');
        }

        $normalizedExisting = array_map(fn (array $ring) => $this->normalizeRing($ring), $existing);
        $this->assertValidExclusionSet($outer, $normalizedExisting);
        $result = [];

        foreach ($normalizedExisting as $ring) {
            $relation = $this->relation($ring, $candidate);
            if ($relation === PolygonRelation::Equal || $relation === PolygonRelation::Contains) {
                return $normalizedExisting;
            }
            if ($relation === PolygonRelation::ContainedBy) {
                continue;
            }
            if ($relation !== PolygonRelation::Disjoint) {
                throw new InvalidPolygon('A nova exclusão não pode cruzar nem tocar outra área excluída.');
            }

            $result[] = $ring;
        }

        $result[] = $candidate;

        return $result;
    }

    /**
     * @param  array<mixed>  $outer
     * @param  array<int, array<mixed>>  $exclusions
     * @return array{bruta: float, excluida: float, liquida: float}
     */
    public function areaBreakdown(array $outer, array $exclusions): array
    {
        $outer = $this->normalizeRing($outer);
        $exclusions = array_map(fn (array $ring) => $this->normalizeRing($ring), $exclusions);
        $this->assertValidExclusionSet($outer, $exclusions);

        $projected = $this->projectTogether([$outer, ...$exclusions]);
        $grossSquareMeters = abs($this->signedArea($projected[0]));
        $excludedSquareMeters = 0.0;
        foreach (array_slice($projected, 1) as $ring) {
            $excludedSquareMeters += abs($this->signedArea($ring));
        }

        if ($excludedSquareMeters > $grossSquareMeters + self::MIN_AREA_SQUARE_METERS) {
            throw new InvalidPolygon('A área excluída não pode ser superior à área bruta.');
        }

        return [
            'bruta' => round($grossSquareMeters / 10_000, 2),
            'excluida' => round($excludedSquareMeters / 10_000, 2),
            'liquida' => round(($grossSquareMeters - $excludedSquareMeters) / 10_000, 2),
        ];
    }

    /**
     * @param  array<mixed>  $ring
     */
    public function areaHectares(array $ring): float
    {
        $ring = $this->normalizeRing($ring);
        [$projected] = $this->projectTogether([$ring]);

        return round(abs($this->signedArea($projected)) / 10_000, 2);
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $outer
     * @param  list<list<array{lat: float, lng: float}>>  $exclusions
     */
    private function assertValidExclusionSet(array $outer, array $exclusions): void
    {
        foreach ($exclusions as $index => $ring) {
            if (! $this->ringStrictlyInside($ring, $outer)) {
                throw new InvalidPolygon('Uma área excluída existente não está contida no talhão.');
            }

            for ($other = 0; $other < $index; $other++) {
                if ($this->relation($exclusions[$other], $ring) !== PolygonRelation::Disjoint) {
                    throw new InvalidPolygon('As áreas excluídas existentes possuem sobreposição ou tangência.');
                }
            }
        }
    }

    /**
     * @param  list<array{x: float, y: float}>  $ring
     */
    private function assertSimpleProjectedRing(array $ring): void
    {
        if (abs($this->signedArea($ring)) < self::MIN_AREA_SQUARE_METERS) {
            throw new InvalidPolygon('O polígono precisa possuir área mensurável.');
        }

        $count = count($ring);
        for ($index = 0; $index < $count; $index++) {
            $previous = $ring[($index - 1 + $count) % $count];
            $current = $ring[$index];
            $next = $ring[($index + 1) % $count];
            if ($this->orientation($previous, $current, $next) === 0
                && $this->dot($current, $previous, $next) > 0.0) {
                throw new InvalidPolygon('O polígono contém um retrocesso colinear.');
            }
        }

        $edges = $this->edges($ring);
        foreach ($edges as $firstIndex => [$firstStart, $firstEnd]) {
            foreach ($edges as $secondIndex => [$secondStart, $secondEnd]) {
                if ($secondIndex <= $firstIndex
                    || $secondIndex === $firstIndex + 1
                    || ($firstIndex === 0 && $secondIndex === count($edges) - 1)) {
                    continue;
                }

                if ($this->segmentsIntersectOrTouch($firstStart, $firstEnd, $secondStart, $secondEnd)) {
                    throw new InvalidPolygon('O polígono não pode possuir autointerseções.');
                }
            }
        }
    }

    /**
     * @param  list<list<array{lat: float, lng: float}>>  $rings
     * @return list<list<array{x: float, y: float}>>
     */
    private function projectTogether(array $rings): array
    {
        $originPoints = $rings[0];
        $originLatitude = array_sum(array_column($originPoints, 'lat')) / count($originPoints);
        $originLongitude = array_sum(array_column($originPoints, 'lng')) / count($originPoints);
        $latitudeRadians = deg2rad($originLatitude);

        return array_map(
            fn (array $ring) => array_map(
                fn (array $point) => [
                    'x' => deg2rad($point['lng'] - $originLongitude)
                        * self::EARTH_RADIUS_METERS
                        * cos($latitudeRadians),
                    'y' => deg2rad($point['lat'] - $originLatitude) * self::EARTH_RADIUS_METERS,
                ],
                $ring,
            ),
            $rings,
        );
    }

    /**
     * @param  list<array{x: float, y: float}>  $polygon
     */
    private function classifyPoint(array $point, array $polygon): PointLocation
    {
        foreach ($this->edges($polygon) as [$start, $end]) {
            if ($this->pointOnSegment($point, $start, $end)) {
                return PointLocation::Boundary;
            }
        }

        $inside = false;
        for ($index = 0, $previous = count($polygon) - 1; $index < count($polygon); $previous = $index++) {
            $currentPoint = $polygon[$index];
            $previousPoint = $polygon[$previous];
            $intersects = (($currentPoint['y'] > $point['y']) !== ($previousPoint['y'] > $point['y']))
                && ($point['x'] < (($previousPoint['x'] - $currentPoint['x'])
                    * ($point['y'] - $currentPoint['y'])
                    / ($previousPoint['y'] - $currentPoint['y'])) + $currentPoint['x']);
            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside ? PointLocation::Inside : PointLocation::Outside;
    }

    private function segmentsIntersectOrTouch(array $firstStart, array $firstEnd, array $secondStart, array $secondEnd): bool
    {
        return $this->segmentIntersectionType($firstStart, $firstEnd, $secondStart, $secondEnd) !== 0;
    }

    /**
     * 0 = disjoint, 1 = touch/collinear, 2 = proper crossing.
     */
    private function segmentIntersectionType(array $firstStart, array $firstEnd, array $secondStart, array $secondEnd): int
    {
        $firstOrientation = $this->orientation($firstStart, $firstEnd, $secondStart);
        $secondOrientation = $this->orientation($firstStart, $firstEnd, $secondEnd);
        $thirdOrientation = $this->orientation($secondStart, $secondEnd, $firstStart);
        $fourthOrientation = $this->orientation($secondStart, $secondEnd, $firstEnd);

        if ($firstOrientation * $secondOrientation < 0 && $thirdOrientation * $fourthOrientation < 0) {
            return 2;
        }

        if (($firstOrientation === 0 && $this->pointOnSegment($secondStart, $firstStart, $firstEnd))
            || ($secondOrientation === 0 && $this->pointOnSegment($secondEnd, $firstStart, $firstEnd))
            || ($thirdOrientation === 0 && $this->pointOnSegment($firstStart, $secondStart, $secondEnd))
            || ($fourthOrientation === 0 && $this->pointOnSegment($firstEnd, $secondStart, $secondEnd))) {
            return 1;
        }

        return 0;
    }

    private function pointOnSegment(array $point, array $start, array $end): bool
    {
        if ($this->orientation($start, $end, $point) !== 0) {
            return false;
        }

        return $point['x'] >= min($start['x'], $end['x']) - self::EPSILON_METERS
            && $point['x'] <= max($start['x'], $end['x']) + self::EPSILON_METERS
            && $point['y'] >= min($start['y'], $end['y']) - self::EPSILON_METERS
            && $point['y'] <= max($start['y'], $end['y']) + self::EPSILON_METERS;
    }

    private function orientation(array $start, array $end, array $point): int
    {
        $cross = (($end['x'] - $start['x']) * ($point['y'] - $start['y']))
            - (($end['y'] - $start['y']) * ($point['x'] - $start['x']));
        $length = max(1.0, hypot($end['x'] - $start['x'], $end['y'] - $start['y']));
        $tolerance = self::EPSILON_METERS * $length;

        return abs($cross) <= $tolerance ? 0 : ($cross <=> 0.0);
    }

    private function dot(array $origin, array $first, array $second): float
    {
        return (($first['x'] - $origin['x']) * ($second['x'] - $origin['x']))
            + (($first['y'] - $origin['y']) * ($second['y'] - $origin['y']));
    }

    /**
     * @param  list<array{x: float, y: float}>  $ring
     * @return list<array{0: array{x: float, y: float}, 1: array{x: float, y: float}}>
     */
    private function edges(array $ring): array
    {
        $edges = [];
        for ($index = 0, $count = count($ring); $index < $count; $index++) {
            $edges[] = [$ring[$index], $ring[($index + 1) % $count]];
        }

        return $edges;
    }

    /**
     * @param  list<array{x: float, y: float}>  $ring
     */
    private function signedArea(array $ring): float
    {
        $sum = 0.0;
        foreach ($this->edges($ring) as [$start, $end]) {
            $sum += ($start['x'] * $end['y']) - ($end['x'] * $start['y']);
        }

        return $sum / 2;
    }

    /**
     * @param  list<array{x: float, y: float}>  $first
     * @param  list<array{x: float, y: float}>  $second
     */
    private function ringsEqual(array $first, array $second): bool
    {
        if (count($first) !== count($second)) {
            return false;
        }

        $count = count($first);
        for ($offset = 0; $offset < $count; $offset++) {
            foreach ([1, -1] as $direction) {
                $equal = true;
                for ($index = 0; $index < $count; $index++) {
                    $otherIndex = ($offset + ($direction * $index)) % $count;
                    if ($otherIndex < 0) {
                        $otherIndex += $count;
                    }
                    if (hypot(
                        $first[$index]['x'] - $second[$otherIndex]['x'],
                        $first[$index]['y'] - $second[$otherIndex]['y'],
                    ) > self::EPSILON_METERS) {
                        $equal = false;
                        break;
                    }
                }
                if ($equal) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sameGeoPoint(array $first, array $second): bool
    {
        return abs($first['lat'] - $second['lat']) <= 1.0e-12
            && abs($first['lng'] - $second['lng']) <= 1.0e-12;
    }
}
