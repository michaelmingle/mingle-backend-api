<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    /** Mean earth radius in metres, used by the haversine helpers below. */
    public const EARTH_RADIUS_METERS = 6371000;

    /** Degrees -> radians. Inlined as a literal so the SQL needs no RADIANS()/PI(). */
    private const DEG_TO_RAD = 0.017453292519943295;

    /**
     * Fields that count towards `profile_completion_percent`. Weighted equally;
     * the pair of relation-backed fields (skills/interests) are appended by
     * {@see completionBreakdown()}.
     */
    public const COMPLETION_FIELDS = [
        'avatar_url',
        'professional_title',
        'bio',
        'industry',
        'location_text',
        'looking_for',
    ];

    protected $fillable = [
        'user_id',
        'avatar_url',
        'professional_title',
        'bio',
        'industry',
        'location_text',
        'latitude',
        'longitude',
        'is_discoverable',
        'discovery_radius_meters',
        'looking_for',
        'portfolio_url',
        'linkedin_url',
        'github_url',
        'website_url',
        'profile_completion_percent',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_discoverable' => 'boolean',
            'discovery_radius_meters' => 'integer',
            'looking_for' => 'array',
            'profile_completion_percent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ------------------------------------------------------------------- scopes

    /**
     * Haversine distance in metres between the row's coordinates and a fixed
     * point, as a raw SQL expression.
     *
     * Uses the asin/sqrt formulation rather than the acos one: the argument to
     * sqrt is naturally bounded to [0, 1], so two rows sharing identical
     * coordinates cannot push the input out of domain and produce NaN. Only
     * sin/cos/asin/sqrt are used, all of which exist in both MySQL and SQLite
     * (SQLite >= 3.35 with math functions, which ships with modern PHP).
     */
    public static function distanceExpression(
        float $latitude,
        float $longitude,
        string $latColumn = 'profiles.latitude',
        string $lngColumn = 'profiles.longitude',
    ): string {
        $r = self::EARTH_RADIUS_METERS;
        $d = self::DEG_TO_RAD;
        $lat = $latitude * self::DEG_TO_RAD;
        $lng = $longitude * self::DEG_TO_RAD;

        return "(2 * $r * asin(sqrt("
            ."sin((($latColumn * $d) - $lat) / 2) * sin((($latColumn * $d) - $lat) / 2)"
            ." + cos($lat) * cos($latColumn * $d)"
            ." * sin((($lngColumn * $d) - $lng) / 2) * sin((($lngColumn * $d) - $lng) / 2)"
            .')))';
    }

    /** Adds a `distance_meters` column to the selection. */
    public function scopeWithDistance(Builder $query, float $latitude, float $longitude): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('profiles.*');
        }

        return $query->selectRaw(
            self::distanceExpression($latitude, $longitude).' as distance_meters'
        );
    }

    /**
     * Discoverable profiles within $radiusKm of a point, ordered nearest first
     * and carrying a `distance_meters` column.
     */
    public function scopeDiscoverableNearby(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm = 5,
    ): Builder {
        $expression = self::distanceExpression($latitude, $longitude);

        return $query
            ->where('profiles.is_discoverable', true)
            ->whereNotNull('profiles.latitude')
            ->whereNotNull('profiles.longitude')
            ->withDistance($latitude, $longitude)
            ->whereRaw($expression.' <= '.self::numericLiteral($radiusKm * 1000))
            ->orderByRaw("$expression asc");
    }

    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query->where('profiles.is_discoverable', true);
    }

    /**
     * Renders a number as a plain SQL numeric literal.
     *
     * Comparing the distance expression against a *bound* float breaks on
     * SQLite: PDO sends floats as strings, and SQLite's affinity rules then
     * compare REAL against TEXT, where every number sorts before every string --
     * so `199219 <= '10000.0'` is true and the radius filter matches everything.
     * Casting to float first keeps this injection-safe.
     */
    public static function numericLiteral(float|int $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.') ?: '0';
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array{percent: int, missing_fields: array<int, string>}
     */
    public function completionBreakdown(): array
    {
        $missing = [];

        foreach (self::COMPLETION_FIELDS as $field) {
            $value = $this->{$field};

            if ($value === null || $value === '' || $value === []) {
                $missing[] = $field;
            }
        }

        if (! $this->relationLoaded('user')) {
            $this->load('user');
        }

        if ($this->user?->skills()->count() === 0) {
            $missing[] = 'skills';
        }

        if ($this->user?->interests()->count() === 0) {
            $missing[] = 'interests';
        }

        $total = count(self::COMPLETION_FIELDS) + 2;
        $percent = (int) round((($total - count($missing)) / $total) * 100);

        return ['percent' => $percent, 'missing_fields' => $missing];
    }

    /** Recomputes and persists the cached completion percentage. */
    public function recalculateCompletion(): int
    {
        $percent = $this->completionBreakdown()['percent'];

        if ($this->profile_completion_percent !== $percent) {
            $this->forceFill(['profile_completion_percent' => $percent])->save();
        }

        return $percent;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Straight-line distance in metres from this profile to a point. Mirrors
     * {@see distanceExpression()} in PHP so single-row lookups need no query.
     */
    public function distanceTo(float $latitude, float $longitude): ?float
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $lat1 = $this->latitude * self::DEG_TO_RAD;
        $lng1 = $this->longitude * self::DEG_TO_RAD;
        $lat2 = $latitude * self::DEG_TO_RAD;
        $lng2 = $longitude * self::DEG_TO_RAD;

        $a = sin(($lat1 - $lat2) / 2) ** 2
            + cos($lat2) * cos($lat1) * sin(($lng1 - $lng2) / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(sqrt(min(1.0, $a)));
    }
}
